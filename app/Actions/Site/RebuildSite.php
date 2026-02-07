<?php

namespace App\Actions\Site;

use App\Enums\SiteStatus;
use App\Exceptions\SSHError;
use App\Jobs\Site\CreateJob;
use App\Models\Service;
use App\Models\Site;
use App\Services\PHP\PHP;
use App\Services\Webserver\Webserver;
use Illuminate\Validation\ValidationException;

class RebuildSite
{
    /**
     * @throws ValidationException
     * @throws SSHError
     */
    public function rebuild(Site $site): void
    {
        if ($site->status !== SiteStatus::INSTALLATION_FAILED) {
            throw ValidationException::withMessages([
                'status' => 'Site can only be rebuilt if installation failed.',
            ]);
        }

        // 1. Cleanup System Artifacts (Logic adapted from DeleteSite)
        
        /** @var Service $service */
        $service = $site->server->webserver();

        /** @var Webserver $webserverHandler */
        $webserverHandler = $service->handler();
        $webserverHandler->deleteSite($site);

        if ($site->isIsolated()) {
            // Remove PHP-FPM Pool
            if ($site->type()->language() === 'php') {
                /** @var Service $phpService */
                $phpService = $site->server->php();
                /** @var PHP $php */
                $php = $phpService->handler();
                $php->removeFpmPool($site->user, $site->php_version, $site->id);
            }

            // Remove Isolated User (This removes home directory and files)
            $os = $site->server->os();
            $os->deleteIsolatedUser($site->user);
        }

        // We do NOT delete the deploy key from source control or DNS records
        // because we are rebuilding the same site, so we want to keep external associations active.
        // However, since we deleted the user, the local SSH key is gone.
        // The 'install()' method in CreateJob will handle re-generating the local key.
        // If the public key changes, the deploy key on GitHub/GitLab might be invalid.
        // BUT: CreateSite->install() calls deployKey().
        // If the key on the provider already exists, deployKey() might fail or duplicate.
        // Ideally, we should rotate the key on the provider too, OR reuse the existing key if we hadn't deleted it.
        // Since we deleted the user, we deleted ~/.ssh/id_rsa. We MUST generate a new one.
        // So we should probably remove the old deploy key from the provider to avoid conflict/stale keys.
        
        if ($site->sourceControl && isset($site->type_data['deploy_key_id'])) {
            try {
                $site->sourceControl->provider()->deleteDeployKey(
                    $site->type_data['deploy_key_id'],
                    $site->repository,
                );
            } catch (\Throwable $e) {
                // Ignore errors if key doesn't exist or permission denied, 
                // just try to proceed. New install will try to add a new key.
            }
            
            // Clear the key ID so install() knows to add a new one
            $typeData = $site->type_data;
            unset($typeData['deploy_key_id']);
            $site->type_data = $typeData;
            $site->save();
        }

        // 2. Reset Status
        $site->update([
            'status' => SiteStatus::INSTALLING,
            'progress' => 0,
        ]);

        // 3. Dispatch Install Job
        dispatch(new CreateJob($site))->onQueue('ssh');
    }
}
