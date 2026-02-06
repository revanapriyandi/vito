<?php

namespace App\Services\Python;

use App\Exceptions\SSHError;
use App\Services\AbstractService;
use Closure;
use Illuminate\Validation\Rule;

class Python extends AbstractService
{
    public static function id(): string
    {
        return 'python';
    }

    public static function type(): string
    {
        return 'python';
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
                Rule::in(config('service.services.python.versions')),
                Rule::unique('services', 'version')
                    ->where('type', 'python')
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
                        ->where('python_version', $this->service->version)
                        ->exists();
                    if ($hasSite) {
                        $fail('Some sites are using this Python version.');
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
            view('ssh.services.python.install-python', [
                'version' => $this->service->version,
            ]),
            'install-python-'.$this->service->version
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
            view('ssh.services.python.uninstall-python', [
                'version' => $this->service->version,
            ]),
            'uninstall-python-'.$this->service->version
        );
        event('service.uninstalled', $this->service);
        $this->service->server->os()->cleanup();
    }

    public function version(): string
    {
        $version = $this->service->server->ssh()->exec(
            'python'.$this->service->version.' --version 2>&1 | awk \'{print $2}\''
        );

        return trim($version);
    }
}
