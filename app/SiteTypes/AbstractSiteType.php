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
            count=$(find . -maxdepth 1 -not -path '*/.*' | wc -l)
            if [ "$count" -eq 1 ]; then
                # Get the single item
                item=$(find . -maxdepth 1 -not -path '*/.*' -printf "%%f")
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

        // Delete local file
        @unlink($zipPath);
    }
}
