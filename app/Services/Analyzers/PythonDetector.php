<?php

namespace App\Services\Analyzers;

use App\DTOs\AnalysisResult;
use Closure;

class PythonDetector implements ProjectDetector
{
    public function analyze(Closure $fileGetter): ?AnalysisResult
    {
        // Check for requirements.txt or manage.py (Django) or wsgi.py
        if ($fileGetter('requirements.txt') || $fileGetter('manage.py') || $fileGetter('Pipfile')) {

            $startCommand = 'gunicorn app:app'; // Deafult guess
            if ($fileGetter('manage.py')) {
                $startCommand = 'gunicorn project.wsgi';
            }

            return new AnalysisResult(
                type: 'python',
                startCommand: $startCommand
            );
        }

        return null;
    }
}
