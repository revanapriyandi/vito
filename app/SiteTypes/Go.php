<?php

namespace App\SiteTypes;

use App\Actions\Worker\CreateWorker;
use App\Actions\Worker\ManageWorker;
use App\Models\Site;
use App\Models\Worker;
use App\SSH\OS\Git;
use Illuminate\Validation\Rule;

class Go extends AbstractSiteType
{
    public static function id(): string
    {
        return 'go';
    }

    public function language(): string
    {
        return 'go';
    }

    public function requiredServices(): array
    {
        return [
            'go',
            'webserver',
            'process_manager',
        ];
    }

    public static function make(): self
    {
        return new self(new Site(['type' => self::id()]));
    }

    public function createRules(array $input): array
    {
        // For Zip deployments, source_control/repository/branch are not provided
        $hasSourceControl = !empty($input['source_control'] ?? null);

        return [
            'source_control' => [
                $hasSourceControl ? 'required' : 'nullable',
                Rule::exists('source_controls', 'id'),
            ],
            'repository' => [
                $hasSourceControl ? 'required' : 'nullable',
            ],
            'branch' => [
                $hasSourceControl ? 'required' : 'nullable',
            ],
            'port' => [
                'required',
                'numeric',
                'between:1,65535',
            ],
            'start_command' => [
                'required',
                'string',
            ],
        ];
    }

    public function createFields(array $input): array
    {
        return [
            'source_control_id' => $input['source_control'] ?? null,
            'repository' => $input['repository'] ?? null,
            'branch' => $input['branch'] ?? null,
            'port' => $input['port'] ?? '',
            'go_version' => $input['go_version'] ?? '',
            'type_data' => [
                'install_command' => $input['install_command'] ?? 'go build -o app',
                'start_command' => $input['start_command'] ?? './app',
            ],
        ];
    }

    public function install(): void
    {
        $this->isolate();
        $this->site->webserver()->createVHost($this->site);
        $this->progress(15);
        $this->deployKey();
        $this->progress(30);
        app(Git::class)->clone($this->site);
        $this->writeInitialEnv();

        if (!empty($this->site->type_data['install_command'])) {
            $this->site->server->ssh($this->site->user)->exec(
                $this->site->type_data['install_command'],
                'install-dependencies',
                $this->site->id
            );
        }

        $this->progress(65);

        /** @var ?Worker $worker */
        $worker = $this->site->workers()->where('name', 'app')->first();
        if ($worker) {
            app(ManageWorker::class)->restart($worker);
        } else {
            app(CreateWorker::class)->create(
                $this->site->server,
                [
                    'name' => 'app',
                    'command' => $this->site->type_data['start_command'],
                    'user' => $this->site->user ?? $this->site->server->getSshUser(),
                    'auto_start' => true,
                    'auto_restart' => true,
                    'numprocs' => 1,
                ],
                $this->site,
            );
        }
        $this->progress(80);
    }

    public function vhost(string $webserver): string|\Illuminate\Contracts\View\View
    {
        if ($webserver === 'nginx') {
            return view('ssh.services.webserver.nginx.vhost', [
                'header' => [
                    view('ssh.services.webserver.nginx.vhost-blocks.force-ssl', ['site' => $this->site]),
                ],
                'main' => [
                    view('ssh.services.webserver.nginx.vhost-blocks.port', ['site' => $this->site]),
                    view('ssh.services.webserver.nginx.vhost-blocks.core', ['site' => $this->site]),
                    view('ssh.services.webserver.nginx.vhost-blocks.reverse-proxy', ['site' => $this->site]),
                    view('ssh.services.webserver.nginx.vhost-blocks.redirects', ['site' => $this->site]),
                ],
            ]);
        }

        return '';
    }
}
