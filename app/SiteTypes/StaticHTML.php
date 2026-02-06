<?php

namespace App\SiteTypes;

use App\Models\Site;
use App\SSH\OS\Git;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;

class StaticHTML extends AbstractSiteType
{
    public static function id(): string
    {
        return 'static-html';
    }

    public function language(): string
    {
        return 'html';
    }

    public function requiredServices(): array
    {
        return [
            'webserver',
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
        ];
    }

    public function createFields(array $input): array
    {
        return [
            'source_control_id' => $input['source_control'] ?? '',
            'repository' => $input['repository'] ?? '',
            'branch' => $input['branch'] ?? '',
            'web_directory' => $input['web_directory'] ?? '',
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
        $this->progress(100);
    }

    public function vhost(string $webserver): string|View
    {
        if ($webserver === 'nginx') {
            return view('ssh.services.webserver.nginx.vhost', [
                'header' => [
                    view('ssh.services.webserver.nginx.vhost-blocks.force-ssl', ['site' => $this->site]),
                ],
                'main' => [
                    view('ssh.services.webserver.nginx.vhost-blocks.static', ['site' => $this->site]),
                    view('ssh.services.webserver.nginx.vhost-blocks.redirects', ['site' => $this->site]),
                ],
            ]);
        }

        // Use default/proxy vhost if not nginx (fallback) or implement Caddy later
        return '';
    }
}
