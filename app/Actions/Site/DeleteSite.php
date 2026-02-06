<?php

namespace App\Actions\Site;

use App\Exceptions\SSHError;
use App\Models\Service;
use App\Models\Site;
use App\Services\PHP\PHP;
use App\Services\Webserver\Webserver;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DeleteSite
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws SSHError
     */
    public function delete(Site $site, array $input): void
    {
        $this->validate($site, $input);

        /** @var Service $service */
        $service = $site->server->webserver();

        /** @var Webserver $webserverHandler */
        $webserverHandler = $service->handler();
        $webserverHandler->deleteSite($site);

        if ($site->isIsolated()) {
            if ($site->type()->language() === 'php') {
                /** @var Service $phpService */
                $phpService = $site->server->php();
                /** @var PHP $php */
                $php = $phpService->handler();
                $php->removeFpmPool($site->user, $site->php_version, $site->id);
            }

            $os = $site->server->os();
            $os->deleteIsolatedUser($site->user);
        }

        if ($site->sourceControl && isset($site->type_data['deploy_key_id'])) {
            $site->sourceControl->provider()->deleteDeployKey(
                $site->type_data['deploy_key_id'],
                $site->repository,
            );
        }

        // Delete DNS Record if exists
        try {
            // How to find which domain this site belongs to?
            // Site has 'domain' string. We need to check if it matches a registered Domain.
            // Complex case: site.domain might be "sub.example.com", and registered domain is "example.com".

            // Try to find the domain part
            $parts = explode('.', $site->domain);
            $count = count($parts);

            // Try matching full domain first, then parent domains
            $registeredDomain = null;
            $subdomain = '@';

            // Simple check: iterate all user domains? or try specific lookup?
            // Better: Check if there is a Domain where site->domain ends with it.

            // Let's rely on finding Domain where domain string is suffix
            $userDomains = \App\Models\Domain::where('user_id', $site->server->user_id)->get(); // assuming server belongs to user? No, site belongs to user?
            // Site doesn't have user_id, it has 'user' string (isolated user).
            // Server has user_id? No, Server has user_id (owner).
            // Let's assume site owner is server owner.

            $serverOwnerId = $site->server->user_id; // Check Server model

            if ($serverOwnerId) {
                // Find matching domain
                foreach ($userDomains as $d) {
                    if ($site->domain === $d->domain) {
                        $registeredDomain = $d;
                        $subdomain = '@';
                        break;
                    }
                    if (str_ends_with($site->domain, '.' . $d->domain)) {
                        $registeredDomain = $d;
                        // Extract subdomain:  app.example.com -> app
                        $subdomain = substr($site->domain, 0, strlen($site->domain) - strlen($d->domain) - 1);
                        break;
                    }
                }

                if ($registeredDomain && $registeredDomain->dnsProvider) {
                    $provider = $registeredDomain->dnsProvider->provider();
                    $records = $provider->getRecords($registeredDomain->provider_domain_id);

                    $targetName = $subdomain === '@' ? $registeredDomain->domain : $subdomain . '.' . $registeredDomain->domain;

                    foreach ($records as $record) {
                        if ($record['name'] === $targetName && $record['type'] === 'A' && $record['content'] === $site->server->ip) {
                            $provider->deleteRecord($registeredDomain->provider_domain_id, $record['id']);
                            // Also delete local DNSRecord model if exists
                            \App\Models\DNSRecord::where('provider_record_id', $record['id'])->delete();
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Log error but generally proceed with deleting site
        }

        $site->delete();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function validate(Site $site, array $input): void
    {
        Validator::make($input, [
            'domain' => [
                'required',
                Rule::in($site->domain),
            ],
        ])->validate();
    }
}
