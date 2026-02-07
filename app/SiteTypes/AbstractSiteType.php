<?php

namespace App\SiteTypes;

use App\Exceptions\FailedToDeployGitKey;
use App\Exceptions\SSHError;
use App\Models\Service;
use App\Models\Site;
use App\Services\PHP\PHP;
use Illuminate\Support\Str;
use RuntimeException;

abstract class AbstractSiteType implements SiteType
{
    public function __construct(protected Site $site)
    {
    }

    abstract public static function make(): self;

    public function createRules(array $input): array
    {
        return [];
    }

    public function createFields(array $input): array
    {
        return [];
    }

    public function data(array $input): array
    {
        return [];
    }

    public function baseCommands(): array
    {
        return [];
    }

    protected function progress(int $percentage): void
    {
        $this->site->progress = $percentage;
        $this->site->save();
    }

    /**
     * @throws FailedToDeployGitKey
     * @throws SSHError
     */
    protected function deployKey(): void
    {
        // Skip deploy key for sites without repository (Zip uploads, manual deployments, etc)
        if (empty($this->site->repository) || empty($this->site->source_control_id)) {
            return;
        }

        $os = $this->site->server->os();
        $os->generateSSHKey($this->site->getSshKeyName(), $this->site);
        $this->site->ssh_key = $os->readSSHKey($this->site->getSshKeyName(), $this->site);
        $this->site->save();
        $keyId = $this->site->sourceControl?->provider()?->deployKey(
            $this->site->getDeployKeyName(),
            $this->site->repository,
            $this->site->ssh_key
        );
        $this->site->jsonUpdate('type_data', 'deploy_key_id', $keyId);
    }

    /**
     * @throws SSHError
     */
    protected function isolate(): void
    {
        if (!$this->site->isIsolated()) {
            return;
        }

        $this->site->server->os()->createIsolatedUser(
            $this->site->user,
            Str::random(15),
            $this->site->id
        );

        // Generate the FPM pool
        if ($this->site->php_version) {
            $service = $this->site->php();
            if (!$service instanceof Service) {
                throw new RuntimeException('PHP service not found');
            }
            /** @var PHP $php */
            $php = $service->handler();
            $php->createFpmPool(
                $this->site->user,
                $this->site->php_version
            );
        }
    }

    /**
     * @throws SSHError
     */
    protected function writeInitialEnv(): void
    {
        if (isset($this->site->type_data['initial_env'])) {
            $envPath = $this->site->type_data['env_path'] ?? $this->site->path . '/.env';
            $this->site->server->os()->write(
                $envPath,
                trim((string) $this->site->type_data['initial_env']),
                $this->site->user
            );

            // Cleanup initial_env from type_data to avoid clutter/confusion
            $typeData = $this->site->type_data;
            unset($typeData['initial_env']);
            $this->site->type_data = $typeData;
            $this->site->save();
        }
    }

    public function handleZip(string $zipPath): void
    {
        if (!file_exists($zipPath)) {
            return;
        }

        $remotePath = $this->site->path . '/source.zip';

        // Upload zip file
        $this->site->server->ssh()->upload(
            $zipPath,
            $remotePath,
            $this->site->user,
            'upload-zip-source',
            $this->site->id
        );

        // Unzip and set permissions
        // -o: overwrite existing files without prompting
        // -d: extract to directory
        $this->site->server->ssh()->exec(
            "sudo unzip -o $remotePath -d {$this->site->path}",
            'extract-zip-source',
            $this->site->id
        );

        // Remove zip file
        $this->site->server->ssh()->exec(
            "sudo rm $remotePath",
            'cleanup-zip-source',
            $this->site->id
        );

        // Fix permissions explicitly before checking structure
        $this->site->server->ssh()->exec(
            "sudo chown -R {$this->site->user}:{$this->site->user} {$this->site->path}",
            'set-permissions-initial',
            $this->site->id
        );

        // Smart Extraction: Check if there is only one directory and move contents up
        // We use a bash script to check this condition
        $flattenScript = <<<'BASH'
            cd %s
            # Count items including hidden ones, excluding . and ..
            count=$(ls -A | wc -l)
            if [ "$count" -eq 1 ]; then
                # Get the single item
                item=$(ls -A)
                if [ -d "$item" ]; then
                    echo "Flattening directory $item..."
                    # Move contents up
                    sudo mv "$item"/* .
                    sudo mv "$item"/.* . 2>/dev/null
                    sudo rmdir "$item"
                fi
            fi
        BASH;

        $this->site->server->ssh()->exec(
            sprintf($flattenScript, $this->site->path),
            'flatten-zip-structure',
            $this->site->id
        );

        // Fix permissions
        $this->site->server->ssh()->exec(
            "sudo chown -R {$this->site->user}:{$this->site->user} {$this->site->path}",
            'set-permissions',
            $this->site->id
        );

        // Check for Framework and run setup
        $this->setupFramework();

        // Delete local file
        @unlink($zipPath);
    }

    protected function setupFramework(): void
    {
        if (!in_array($this->site->type, ['php', 'laravel', 'codeigniter'])) {
            return;
        }

        // 1. Generic Composer Install (for Laravel, Symphony, CI4, Modern Native, etc)
        // Only run if not already explicitly requested via site configuration (to avoid double install)
        if (empty($this->site->type_data['composer'])) {
            $checkComposer = "test -f {$this->site->path}/composer.json && echo 'yes'";
            if (trim($this->site->server->ssh()->exec($checkComposer, 'check-composer', $this->site->id)) === 'yes') {
                $this->site->server->ssh($this->site->user)->exec(
                    "cd {$this->site->path} && composer install --no-dev --no-interaction --no-progress --optimize-autoloader",
                    'composer-install',
                    $this->site->id
                );
            }
        }

        // 2. Laravel Specific Setup
        $checkLaravel = "test -f {$this->site->path}/artisan && echo 'yes'";
        if (trim($this->site->server->ssh()->exec($checkLaravel, 'check-laravel', $this->site->id)) === 'yes') {

            // Permissions
            $this->site->server->ssh()->exec(
                "sudo chmod -R 775 {$this->site->path}/storage {$this->site->path}/bootstrap/cache",
                'laravel-permissions-mode',
                $this->site->id
            );

            // Environment File
            $checkEnv = "test -f {$this->site->path}/.env || echo 'missing'";
            if (trim($this->site->server->ssh()->exec($checkEnv, 'check-env', $this->site->id)) === 'missing') {
                $checkEnvExample = "test -f {$this->site->path}/.env.example && echo 'yes'";
                if (trim($this->site->server->ssh()->exec($checkEnvExample, 'check-env-example', $this->site->id)) === 'yes') {
                    $this->site->server->ssh($this->site->user)->exec("cd {$this->site->path} && cp .env.example .env", 'laravel-copy-env', $this->site->id);
                } else {
                    $this->site->server->ssh($this->site->user)->exec("cd {$this->site->path} && touch .env", 'laravel-create-env', $this->site->id);
                }

                $this->site->server->ssh($this->site->user)->exec("cd {$this->site->path} && php artisan key:generate --force", 'laravel-key-generate', $this->site->id);
            }

            // Commands
            $this->site->server->ssh($this->site->user)->exec("cd {$this->site->path} && php artisan migrate --force", 'laravel-migrate', $this->site->id);
            $this->site->server->ssh($this->site->user)->exec("cd {$this->site->path} && php artisan storage:link", 'laravel-storage-link', $this->site->id);
            $this->site->server->ssh($this->site->user)->exec("cd {$this->site->path} && php artisan optimize:clear", 'laravel-optimize', $this->site->id);
        }

        // 3. CodeIgniter 4 Specific Setup
        $checkCI4 = "test -f {$this->site->path}/spark && echo 'yes'";
        if (trim($this->site->server->ssh()->exec($checkCI4, 'check-ci4', $this->site->id)) === 'yes') {

            // Permissions: CI4 requires 'writable' to be writable by web server
            // Ensure writable exists first
            if (trim($this->site->server->ssh()->exec("test -d {$this->site->path}/writable && echo 'yes'", 'check-ci4-writable', $this->site->id)) === 'yes') {
                $this->site->server->ssh()->exec(
                    "sudo chmod -R 775 {$this->site->path}/writable",
                    'ci4-permissions-mode',
                    $this->site->id
                );
            }

            // Environment File
            $checkEnv = "test -f {$this->site->path}/.env || echo 'missing'";
            if (trim($this->site->server->ssh()->exec($checkEnv, 'check-env', $this->site->id)) === 'missing') {
                // CI4 uses 'env' file as example sometimes, or just .env.example? Usually 'env' in source root.
                // Standard CI4 comes with 'env'.
                if (trim($this->site->server->ssh()->exec("test -f {$this->site->path}/env && echo 'yes'", 'check-ci4-env-example', $this->site->id)) === 'yes') {
                    $this->site->server->ssh($this->site->user)->exec("cd {$this->site->path} && cp env .env", 'ci4-copy-env', $this->site->id);
                } elseif (trim($this->site->server->ssh()->exec("test -f {$this->site->path}/.env.example && echo 'yes'", 'check-env-example', $this->site->id)) === 'yes') {
                    $this->site->server->ssh($this->site->user)->exec("cd {$this->site->path} && cp .env.example .env", 'ci4-copy-env', $this->site->id);
                }

                // CI4 >= 4.2 has key:generate
                $this->site->server->ssh($this->site->user)->exec("cd {$this->site->path} && php spark key:generate", 'ci4-key-generate', $this->site->id);
            }

            $this->site->server->ssh($this->site->user)->exec("cd {$this->site->path} && php spark migrate --all", 'ci4-migrate', $this->site->id);
            $this->site->server->ssh($this->site->user)->exec("cd {$this->site->path} && php spark optimize", 'ci4-optimize', $this->site->id);
        }
    }
}
