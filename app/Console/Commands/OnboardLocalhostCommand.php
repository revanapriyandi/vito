<?php

namespace App\Console\Commands;

use App\Enums\OperatingSystem;
use App\Enums\ServerStatus;
use App\Enums\ServiceStatus;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use App\ServerProviders\Custom;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OnboardLocalhostCommand extends Command
{
    protected $signature = 'vito:onboard-localhost {--user-id=1 : The user ID to assign the server to}';

    protected $description = 'Onboard the localhost server as a managed Vito server';

    public function handle(): int
    {
        $userId = $this->option('user-id');
        $user = User::find($userId);

        if (!$user) {
            $this->error("User with ID {$userId} not found.");

            return self::FAILURE;
        }

        if (Server::where('ip', '127.0.0.1')->exists()) {
            $this->error('Localhost server manually already exists.');

            return self::FAILURE;
        }

        $this->info('Onboarding localhost server...');

        DB::transaction(function () use ($user) {
            // 1. Create Server Record
            $server = Server::create([
                'user_id' => $user->id,
                'name' => 'Localhost',
                'ssh_user' => config('core.ssh_user', 'vito'),
                'ip' => '127.0.0.1',
                'port' => 22,
                'os' => OperatingSystem::UBUNTU24, // Assuming latest supported, or check via php_uname
                'provider' => Custom::id(),
                'status' => ServerStatus::READY,
                'authentication' => [
                    'user' => config('core.ssh_user', 'vito'),
                    'pass' => Str::random(15),
                    'root_pass' => Str::random(15),
                ],
                'progress' => 100,
                'progress_step' => 'Installed',
            ]);

            // 2. Generate SSH Keys
            $this->info('Generating SSH keys...');
            $keyPath = Storage::disk(config('core.key_pairs_disk'))->path((string) $server->id);
            $pubKeyPath = $keyPath . '.pub';

            // Ensure directory exists
            if (!File::exists(dirname($keyPath))) {
                File::makeDirectory(dirname($keyPath), 0755, true);
            }

            // Generate keys without passphrase
            Process::run(['ssh-keygen', '-t', 'rsa', '-b', '4096', '-f', $keyPath, '-N', ''])->throw();

            // Store keys in Storage (if disk uses different path structure, ensure they are in the right place)
            // The storage disk might be S3 or local. If local, we just acted on it.
            // But to be safe with Storage abstraction, let's read and write if needed,
            // but for keys usually we want local access for SSH.
            // Assuming 'key-pairs' disk points to a local path as per config.

            $publicKey = File::get($pubKeyPath);

            // 3. Add to authorized_keys
            $this->info('Adding public key to authorized_keys...');
            $authorizedKeysPath = getenv('HOME') . '/.ssh/authorized_keys';

            if (!File::exists(dirname($authorizedKeysPath))) {
                File::makeDirectory(dirname($authorizedKeysPath), 0700, true);
            }

            File::append($authorizedKeysPath, "\n" . $publicKey);

            // 4. Create Services
            $this->info('Registering services...');
            $services = [
                ['type' => 'webserver', 'name' => 'nginx', 'version' => 'latest', 'status' => ServiceStatus::READY, 'is_default' => true],
                ['type' => 'database', 'name' => 'mysql', 'version' => '8.0', 'status' => ServiceStatus::READY, 'is_default' => true],
                ['type' => 'php', 'name' => 'php', 'version' => '8.4', 'status' => ServiceStatus::READY, 'is_default' => true],
                ['type' => 'memory_database', 'name' => 'redis', 'version' => 'latest', 'status' => ServiceStatus::READY, 'is_default' => true],
                ['type' => 'process_manager', 'name' => 'supervisor', 'version' => 'latest', 'status' => ServiceStatus::READY, 'is_default' => true],
                ['type' => 'firewall', 'name' => 'ufw', 'version' => 'latest', 'status' => ServiceStatus::READY, 'is_default' => true],
                ['type' => 'nodejs', 'name' => 'nodejs', 'version' => '20', 'status' => ServiceStatus::READY, 'is_default' => true],
            ];

            foreach ($services as $data) {
                $server->services()->create($data);
            }

            $this->info("Server 'Localhost' (ID: {$server->id}) onboarded successfully.");
        });

        return self::SUCCESS;
    }
}
