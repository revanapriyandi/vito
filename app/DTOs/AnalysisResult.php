<?php

namespace App\DTOs;

use Illuminate\Contracts\Support\Arrayable;

class AnalysisResult implements Arrayable
{
    public function __construct(
        public ?string $type = null,
        public ?string $phpVersion = null,
        public ?string $nodeVersion = null,
        public ?string $publicDirectory = null,
        public ?string $buildCommand = null,
        public ?string $startCommand = null,
        public array $envSuggestions = [],
        public ?string $databaseRecommendation = null,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'php_version' => $this->phpVersion,
            'node_version' => $this->nodeVersion,
            'public_directory' => $this->publicDirectory,
            'build_command' => $this->buildCommand,
            'start_command' => $this->startCommand,
            'env_suggestions' => $this->envSuggestions,
            'database_recommendation' => $this->databaseRecommendation,
        ];
    }
}
