<?php

namespace App\Services\Analyzers;

use App\DTOs\AnalysisResult;
use Closure;

interface ProjectDetector
{
    /**
     * @param  Closure(string): ?string  $fileGetter
     */
    public function analyze(Closure $fileGetter): ?AnalysisResult;
}
