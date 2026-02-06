<?php

namespace App\Actions\Site;

use App\Enums\SiteStatus;
use App\Exceptions\RepositoryNotFound;
use App\Exceptions\RepositoryPermissionDenied;
use App\Exceptions\SourceControlIsNotConnected;
use App\Jobs\Site\CreateJob;
use App\Models\Server;
use App\Models\Site;
use App\ValidationRules\DomainRule;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateSite
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws Throwable
     */
    public function create(Server $server, array $input): Site
    {
        $this->validate($server, $input);

        DB::beginTransaction();
        try {
            $user = $input['user'];
            $site = new Site([
                'server_id' => $server->id,
                'type' => $input['type'],
                'domain' => $input['domain'],
                'aliases' => $input['aliases'] ?? [],
                'user' => $user,
                'path' => '/home/' . $user . '/' . $input['domain'],
                'status' => SiteStatus::INSTALLING,
            ]);

            foreach ($site->type()->requiredServices() as $requiredService) {
                if (!$server->service($requiredService)) {
                    throw ValidationException::withMessages([
                        'type' => "The site type requires a {$requiredService} service to be installed.",
                    ]);
                }
            }

            // fields based on the type
            $site->fill($site->type()->createFields($input));

            // check has access to repository
            try {
                if ($site->sourceControl) {
                    $site->sourceControl->getRepo($site->repository);
                }
            } catch (SourceControlIsNotConnected) {
                throw ValidationException::withMessages([
                    'source_control' => 'Source control is not connected',
                ]);
            } catch (RepositoryPermissionDenied) {
                throw ValidationException::withMessages([
                    'repository' => 'You do not have permission to access this repository',
                ]);
            } catch (RepositoryNotFound) {
                throw ValidationException::withMessages([
                    'repository' => 'Repository not found',
                ]);
            }

            // set type data
            $site->type_data = $site->type()->data($input);
            if (isset($input['env']) && !empty($input['env'])) {
                $typeData = $site->type_data;
                $typeData['initial_env'] = $input['env'];
                $site->type_data = $typeData;
            }

            // save
            $site->save();

            // create base commands if any
            $site->commands()->createMany($site->type()->baseCommands());

            // Handle DNS if domain exists and has provider
            // Check for registered domain
            if (isset($input['selected_domain_id']) && !empty($input['selected_domain_id'])) {
                $domain = \App\Models\Domain::find($input['selected_domain_id']);

                if ($domain && $domain->dnsProvider) {
                    try {
                        $provider = $domain->dnsProvider->provider();
                        $records = $provider->getRecords($domain->provider_domain_id);

                        $subdomain = $input['subdomain_prefix'] ?? '@';
                        if (empty($subdomain)) {
                            $subdomain = '@';
                        }

                        // Check availability
                        $exists = collect($records)->contains(function ($record) use ($subdomain, $domain) {
                            $recordName = $record['name'];
                            // Cloudflare returns full name (e.g. sub.domain.com) or just name? 
                            // Usually full name. Let's compare carefully.
                            // If subdomain is @, it matches domain name.
                            // If subdomain is 'sub', it matches sub.domain.com

                            $targetName = $subdomain === '@' ? $domain->domain : $subdomain . '.' . $domain->domain;
                            return $recordName === $targetName && $record['type'] === 'A';
                        });

                        if ($exists) {
                            throw ValidationException::withMessages([
                                'domain' => "DNS Record for {$subdomain} already exists in " . $domain->dnsProvider->name,
                            ]);
                        }

                        $dnsInput = [
                            'type' => 'A',
                            'name' => $subdomain,
                            'content' => $server->ip,
                            'ttl' => 1, // Auto
                            'proxied' => true,
                        ];

                        app(\App\Actions\Domain\CreateDNSRecord::class)->create($domain, $dnsInput);
                    } catch (ValidationException $e) {
                        throw $e;
                    } catch (Throwable $e) {
                        // Log error but don't fail site creation logic unless it's validation
                    }
                }
            }

            // install site
            $zipPath = null;
            if (isset($input['zip_file']) && $input['zip_file'] instanceof \Illuminate\Http\UploadedFile) {
                $zipPath = $input['zip_file']->store('sites-zips');
            }

            dispatch(new CreateJob($site, $zipPath))->onQueue('ssh');

            DB::commit();

            return $site;
        } catch (Exception $e) {
            DB::rollBack();
            throw ValidationException::withMessages([
                'type' => $e->getMessage(),
            ]);
        }
    }

    private function validate(Server $server, array $input): void
    {
        $rules = [
            'type' => [
                'required',
                Rule::in(array_keys(config('site.types'))),
            ],
            'domain' => [
                'required',
                new DomainRule,
                Rule::unique('sites', 'domain')->where(fn($query) => $query->where('server_id', $server->id)),
            ],
            'aliases.*' => [
                new DomainRule,
            ],
            'user' => [
                'required',
                'regex:/^[a-z_][a-z0-9_-]*[a-z0-9]$/',
                'min:3',
                'max:32',
                Rule::unique('sites', 'user')->where('server_id', $server->id),
                Rule::notIn($server->getSshUsers()),
            ],
        ];

        Validator::make($input, array_merge($rules, $this->typeRules($server, $input)))->validate();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<string>>
     */
    private function typeRules(Server $server, array $input): array
    {
        if (!isset($input['type']) || !config('site.types.' . $input['type'])) {
            return [];
        }

        $site = new Site(
            [
                'server_id' => $server->id,
                'type' => $input['type'],
            ]
        );

        return $site->type()->createRules($input);
    }
}
