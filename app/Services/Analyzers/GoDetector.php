<?php

namespace App\Services\Analyzers;

use App\DTOs\AnalysisResult;
use Closure;

class GoDetector implements ProjectDetector
{
    public function analyze(Closure $fileGetter): ?AnalysisResult
    {
        if ($fileGetter('go.mod')) {
            return new AnalysisResult(
                type: 'go',
                buildCommand: 'go build -o main .',
                startCommand: './main'
            );
        }

        return null;
    }
}
