<?php

namespace App\SiteTypes;

use App\Actions\Worker\CreateWorker;
use App\Actions\Worker\ManageWorker;
use App\Models\Site;
use App\Models\Worker;
use App\SSH\OS\Git;
use Illuminate\Validation\Rule;

class Python extends AbstractSiteType
{
    public static function id(): string
    {
        return 'python';
    }

    public function language(): string
    {
        return 'python';
    }

    public function requiredServices(): array
    {
        return [
            'python',
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
        return [
            'source_control' => ['required', Rule::exists('source_controls', 'id')],
            'repository' => ['required'],
            'branch' => ['required'],
            'port' => ['required', 'numeric', 'between:1,65535'],
            'start_command' => ['required', 'string'],
        ];
    }

    public function createFields(array $input): array
    {
        return [
            'source_control_id' => $input['source_control'] ?? '',
            'repository' => $input['repository'] ?? '',
            'branch' => $input['branch'] ?? '',
            'port' => $input['port'] ?? '',
            'python_version' => $input['python_version'] ?? '',
            'type_data' => [
                'install_command' => $input['install_command'] ?? 'pip install -r requirements.txt',
                'start_command' => $input['start_command'] ?? '',
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

        if (! empty($this->site->type_data['install_command'])) {
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
