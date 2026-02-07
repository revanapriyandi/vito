<?php

namespace App\Actions\Site;

use App\Enums\SiteStatus;
use App\Jobs\Site\CreateJob;
use App\Models\Site;
use Illuminate\Validation\ValidationException;

class RebuildSite
{
    /**
     * @throws ValidationException
     */
    public function rebuild(Site $site): void
    {
        if ($site->status !== SiteStatus::INSTALLATION_FAILED) {
            throw ValidationException::withMessages([
                'status' => 'Site can only be rebuilt if installation failed.',
            ]);
        }

        $site->update([
            'status' => SiteStatus::INSTALLING,
            'progress' => 0,
        ]);

        dispatch(new CreateJob($site))->onQueue('ssh');
    }
}
