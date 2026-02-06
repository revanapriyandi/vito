<?php

namespace App\Services\Analyzers;

use App\DTOs\AnalysisResult;
use Closure;

class StaticHTMLDetector implements ProjectDetector
{
    public function analyze(Closure $fileGetter): ?AnalysisResult
    {
        // Check for index.html
        if ($fileGetter('index.html')) {
            return new AnalysisResult(
                type: 'static-html', // Check exact key in SiteTypes, likely 'static_html' or 'html'
                publicDirectory: '/'
            );
        }

        return null;
    }
}
