<?php

namespace App\Services\Analyzers;

use App\DTOs\AnalysisResult;
use Closure;

class WordpressDetector implements ProjectDetector
{
    public function analyze(Closure $fileGetter): ?AnalysisResult
    {
        if ($fileGetter('wp-config.php') || $fileGetter('wp-config-sample.php')) {
            return new AnalysisResult(
                type: 'wordpress',
                phpVersion: '8.2', // WP works well with 8.x
                databaseRecommendation: 'mysql'
            );
        }

        return null;
    }
}
