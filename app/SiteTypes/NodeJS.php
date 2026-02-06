<?php

namespace App\SiteTypes;

use App\Actions\Worker\CreateWorker;
use App\Actions\Worker\ManageWorker;
use App\Exceptions\FailedToDeployGitKey;
use App\Exceptions\SSHError;
use App\Models\Site;
use App\Models\Worker;
use App\SSH\OS\Git;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;

class NodeJS extends AbstractSiteType
{
    public static function id(): string
    {
        return 'nodejs';
    }

    public function language(): string
    {
        return 'nodejs';
    }

    public function requiredServices(): array
    {
        return [
            'nodejs',
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
            'nodejs_version' => [
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
            'nodejs_version' => $input['nodejs_version'] ?? '',
        ];
    }

    public function data(array $input): array
    {
        return [];
    }

    /**
     * @throws FailedToDeployGitKey
     * @throws SSHError
     */
    public function install(): void
    {
        $this->isolate();
        $this->site->webserver()->createVHost($this->site);
        $this->progress(15);

        // Only deploy key and clone if using git repository
        if (!empty($this->site->repository)) {
            $this->deployKey();
            $this->progress(30);
            app(Git::class)->clone($this->site);
        }

        $this->writeInitialEnv();
        $this->site->server->ssh($this->site->user)->exec(
            __('npm install --prefix=:path', [
                'path' => $this->site->path,
            ]),
            'install-npm-dependencies',
            $this->site->id
        );
        $this->site->server->ssh($this->site->user)->exec(
            __('npm run build --prefix=:path', [
                'path' => $this->site->path,
            ]),
            'npm-build',
            $this->site->id
        );
        $this->progress(65);
        $command = __('npm start --prefix=:path', [
            'path' => $this->site->path,
        ]);
        $this->progress(80);
        /** @var ?Worker $worker */
        $worker = $this->site->workers()->where('name', 'app')->first();
        if ($worker) {
            app(ManageWorker::class)->restart($worker);
        } else {
            app(CreateWorker::class)->create(
                $this->site->server,
                [
                    'name' => 'app',
                    'command' => $command,
                    'user' => $this->site->user ?? $this->site->server->getSshUser(),
                    'auto_start' => true,
                    'auto_restart' => true,
                    'numprocs' => 1,
                ],
                $this->site,
            );
        }
    }

    public function baseCommands(): array
    {
        return [
            [
                'name' => 'npm:install',
                'command' => 'npm install',
            ],
        ];
    }

    public function vhost(string $webserver): string|View
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

        if ($webserver === 'caddy') {
            return view('ssh.services.webserver.caddy.vhost', [
                'site' => $this->site,
                'main' => [
                    view('ssh.services.webserver.caddy.vhost-blocks.force-ssl', ['site' => $this->site]),
                    view('ssh.services.webserver.caddy.vhost-blocks.port', ['site' => $this->site]),
                    view('ssh.services.webserver.caddy.vhost-blocks.core', ['site' => $this->site]),
                    view('ssh.services.webserver.caddy.vhost-blocks.reverse-proxy', ['site' => $this->site]),
                    view('ssh.services.webserver.caddy.vhost-blocks.redirects', ['site' => $this->site]),
                ],
            ]);
        }

        return '';
    }
}
