<?php

namespace App\Services\Go;

use App\Exceptions\SSHError;
use App\Services\AbstractService;
use Closure;
use Illuminate\Validation\Rule;

class Go extends AbstractService
{
    public static function id(): string
    {
        return 'go';
    }

    public static function type(): string
    {
        return 'go';
    }

    public function unit(): string
    {
        return '';
    }

    public function creationRules(array $input): array
    {
        return [
            'version' => [
                'required',
                Rule::in(config('service.services.go.versions')),
                Rule::unique('services', 'version')
                    ->where('type', 'go')
                    ->where('server_id', $this->service->server_id),
            ],
        ];
    }

    public function deletionRules(): array
    {
        return [
            'service' => [
                function (string $attribute, mixed $value, Closure $fail): void {
                    $hasSite = $this->service->server->sites()
                        ->where('go_version', $this->service->version)
                        ->exists();
                    if ($hasSite) {
                        $fail('Some sites are using this Go version.');
                    }
                },
            ],
        ];
    }

    /**
     * @throws SSHError
     */
    public function install(): void
    {
        $server = $this->service->server;
        $server->ssh()->exec(
            view('ssh.services.go.install-go', [
                'version' => $this->service->version,
            ]),
            'install-go-'.$this->service->version
        );
        event('service.installed', $this->service);
        $this->service->server->os()->cleanup();
    }

    /**
     * @throws SSHError
     */
    public function uninstall(): void
    {
        $this->service->server->ssh()->exec(
            view('ssh.services.go.uninstall-go', [
                'version' => $this->service->version,
            ]),
            'uninstall-go-'.$this->service->version
        );
        event('service.uninstalled', $this->service);
        $this->service->server->os()->cleanup();
    }

    public function version(): string
    {
        $version = $this->service->server->ssh()->exec(
            '/usr/local/go/bin/go version | awk \'{print $3}\' | sed \'s/go//\''
        );

        return trim($version);
    }
}
