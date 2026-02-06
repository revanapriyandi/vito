<?php

namespace App\Services\Analyzers;

use App\DTOs\AnalysisResult;
use Closure;

class LaravelDetector implements ProjectDetector
{
    public function analyze(Closure $fileGetter): ?AnalysisResult
    {
        // Check for artisan
        if (! $fileGetter('artisan')) {
            return null;
        }

        // Check for composer.json to find PHP version
        $composerJson = $fileGetter('composer.json');
        $phpVersion = '8.2'; // Default

        if ($composerJson) {
            $data = json_decode($composerJson, true);
            if (isset($data['require']['php'])) {
                // Parse PHP version (simple parsing)
                // e.g. "^8.1" -> "8.1"
                $versionConstraint = $data['require']['php'];
                $phpVersion = $this->parsePhpVersion($versionConstraint) ?? $phpVersion;
            }
        }

        return new AnalysisResult(
            type: 'laravel',
            phpVersion: $phpVersion,
            publicDirectory: '/public',
            buildCommand: 'npm run build',
            startCommand: 'php artisan serve', // Though normally handled by web server conf
            databaseRecommendation: 'mysql'
        );
    }

    protected function parsePhpVersion(string $constraint): ?string
    {
        // Remove ^, ~, >= etc
        $version = preg_replace('/[^0-9.]/', '', $constraint);
        // Take first two parts: 8.1.2 -> 8.1
        $parts = explode('.', $version);
        if (count($parts) >= 2) {
            return $parts[0].'.'.$parts[1];
        }

        return null;
    }
}
