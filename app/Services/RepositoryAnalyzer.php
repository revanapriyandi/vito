<?php

namespace App\Services;

use App\DTOs\AnalysisResult;
use App\Models\SourceControl;
use App\Services\Analyzers\GoDetector;
use App\Services\Analyzers\LaravelDetector;
use App\Services\Analyzers\NodeDetector;
use App\Services\Analyzers\PHPSiteDetector;
use App\Services\Analyzers\ProjectDetector;
use App\Services\Analyzers\PythonDetector;
use App\Services\Analyzers\StaticHTMLDetector;
use App\Services\Analyzers\WordpressDetector;
use Closure;

class RepositoryAnalyzer
{
    /**
     * @var array<int, class-string<ProjectDetector>>
     */
    protected array $detectors = [
        LaravelDetector::class,
        WordpressDetector::class,
        PHPSiteDetector::class, // Generic PHP last among PHP-ish
        PythonDetector::class,
        GoDetector::class,
        NodeDetector::class,      // Check Node.js last before fallback
        StaticHTMLDetector::class,
    ];

    public function analyze(SourceControl $sourceControl, string $repo, string $branch): AnalysisResult
    {
        $getter = function (string $path) use ($sourceControl, $repo, $branch): ?string {
            return $sourceControl->provider()->getFileContent($repo, $branch, $path);
        };

        return $this->runDetectors($getter);
    }

    public function analyzeZip(string $extractedPath): AnalysisResult
    {
        $getter = function (string $path) use ($extractedPath): ?string {
            $fullPath = $extractedPath . '/' . $path;
            if (file_exists($fullPath) && !is_dir($fullPath)) {
                return file_get_contents($fullPath);
            }

            return null;
        };

        return $this->runDetectors($getter);
    }

    protected function runDetectors(Closure $getter): AnalysisResult
    {
        foreach ($this->detectors as $detectorClass) {
            $detector = app($detectorClass);
            $result = $detector->analyze($getter);

            if ($result) {
                // If detected, we can also run EnvAnalyzer here to enrich suggestions
                $this->enrichWithEnv($result, $getter);

                return $result;
            }
        }

        // Fallback or empty result
        return new AnalysisResult(type: 'custom');
    }

    protected function enrichWithEnv(AnalysisResult $result, Closure $getter): void
    {
        $envExample = $getter('.env.example') ?? $getter('.env.template') ?? $getter('.env'); // basic check

        if ($envExample) {
            $lines = explode("\n", $envExample);
            $keys = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line && !str_starts_with($line, '#') && str_contains($line, '=')) {
                    $key = explode('=', $line)[0];
                    if ($key) {
                        $keys[] = trim($key);
                    }
                }
            }
            $result->envSuggestions = $keys;
        }
    }
}
