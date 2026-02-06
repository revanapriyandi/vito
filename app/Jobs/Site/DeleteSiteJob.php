<?php

namespace App\Jobs\Site;

use App\Actions\Site\DeleteSite;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Site $site,
        public array $input = []
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(DeleteSite $deleteSite): void
    {
        try {
            Log::info("Starting background deletion for site: {$this->site->domain}");
            $deleteSite->delete($this->site, $this->input);
            Log::info("Successfully deleted site: {$this->site->domain}");
        } catch (Throwable $e) {
            Log::error("Failed to delete site background job: {$this->site->domain}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to ensure job fails in queue manager
            throw $e;
        }
    }
}
