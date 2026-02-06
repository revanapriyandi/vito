<?php

namespace App\Jobs\Site;

use App\Enums\SiteStatus;
use App\Facades\Notifier;
use App\Models\ServerLog;
use App\Models\Site;
use App\Notifications\SiteInstallationFailed;
use App\Notifications\SiteInstallationSucceed;
use App\Traits\UniqueQueue;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateJob implements ShouldQueue
{
    use Queueable;
    use UniqueQueue;

    public function __construct(protected Site $site, protected ?string $zipPath = null)
    {
    }

    public function handle(): void
    {
        $this->run("server-{$this->site->server_id}", function () {
            $type = $this->site->type();

            $type->install();

            if ($this->zipPath) {
                // Determine absolute path if relative
                $path = $this->zipPath;
                if (!str_starts_with($path, '/') && !str_starts_with($path, 'c:') && !str_starts_with($path, 'C:')) {
                    $path = storage_path('app/' . $this->zipPath);
                }
                $type->handleZip($path);

                // Trigger deployment to run build/start commands
                try {
                    app(\App\Actions\Site\Deploy::class)->run($this->site);
                } catch (Exception $e) {
                    // Log deployment failure but don't fail the creation job completely?
                    // Or shoud we? The site is created, just deploy failed.
                    // The Deploy action dispatches a job, so if it fails here, it's early validation.
                    ServerLog::log($this->site->server, 'initial-deploy-failed', $e->getMessage(), $this->site);
                }
            }

            $this->site->update([
                'status' => SiteStatus::READY,
                'progress' => 100,
            ]);
            Notifier::send($this->site, new SiteInstallationSucceed($this->site));
        });
    }

    public function failed(Exception $e): void
    {
        $this->site->status = SiteStatus::INSTALLATION_FAILED;
        $this->site->save();
        ServerLog::log(
            $this->site->server,
            'site-installation-failed',
            $e->getMessage(),
            $this->site
        );
        Notifier::send($this->site, new SiteInstallationFailed($this->site));
    }
}
