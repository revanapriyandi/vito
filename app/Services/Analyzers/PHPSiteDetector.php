<?php

namespace App\Services\Analyzers;

use App\DTOs\AnalysisResult;
use Closure;

class PHPSiteDetector implements ProjectDetector
{
    public function analyze(Closure $fileGetter): ?AnalysisResult
    {
        // If it's Laravel, it should have been caught by LaravelDetector earlier
        // but we double check or assume order matters in RepositoryAnalyzer.

        // Check for index.php or composer.json
        if ($fileGetter('index.php') || $fileGetter('public/index.php') || $fileGetter('composer.json')) {
            $phpVersion = '8.2'; // Default

            // Try to find version from composer.json
            $composerJson = $fileGetter('composer.json');
            if ($composerJson) {
                $data = json_decode($composerJson, true);
                if (isset($data['require']['php'])) {
                    $versionConstraint = $data['require']['php'];
                    // Reuse logic or duplicate simple parsing?
                    // For now simple parsing here or trait if we want to be clean.
                    $version = preg_replace('/[^0-9.]/', '', $versionConstraint);
                    $parts = explode('.', $version);
                    if (count($parts) >= 2) {
                        $phpVersion = $parts[0].'.'.$parts[1];
                    }
                }
            }

            // Decide public directory
            $publicDir = '/';
            if ($fileGetter('public/index.php')) {
                $publicDir = '/public';
            }

            return new AnalysisResult(
                type: 'php', // Maps to 'php_site' or similar? Need to check SiteTypes mapping
                phpVersion: $phpVersion,
                publicDirectory: $publicDir
            );
        }

        return null;
    }
}
