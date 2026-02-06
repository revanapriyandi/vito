<?php

namespace App\Services\Analyzers;

use App\DTOs\AnalysisResult;
use Closure;

class NodeDetector implements ProjectDetector
{
    public function analyze(Closure $fileGetter): ?AnalysisResult
    {
        $packageJson = $fileGetter('package.json');
        if (!$packageJson) {
            return null;
        }

        $data = json_decode($packageJson, true);
        $nodeVersion = '20'; // Default

        // Check engines
        if (isset($data['engines']['node'])) {
            $nodeVersion = $this->parseNodeVersion($data['engines']['node']) ?? $nodeVersion;
        } else {
            // Check .nvmrc
            $nvmrc = $fileGetter('.nvmrc');
            if ($nvmrc) {
                $nodeVersion = $this->parseNodeVersion(trim($nvmrc)) ?? $nodeVersion;
            }
        }

        // Detect Framework
        $type = 'nodejs';
        $build = 'npm run build';
        $start = 'npm start';

        if ($fileGetter('next.config.js') || $fileGetter('next.config.mjs')) {
            // NextJS detected... usually handled as 'nodejs' or specific if system supports it
            // Assuming system treats all as 'nodejs' for now, but configured differently
            $type = 'nodejs'; // Or specific 'nextjs' if supported
            $start = 'npm start';
        }

        return new AnalysisResult(
            type: $type,
            nodeVersion: $nodeVersion,
            buildCommand: $build,
            startCommand: $start
        );
    }

    protected function parseNodeVersion(string $constraint): ?string
    {
        // Simple parser: remove non-numeric except dot, take major version mostly?
        // Node versions usually int: 18, 20.
        $version = preg_replace('/[^0-9.]/', '', $constraint);

        if (empty($version)) {
            return null;
        }

        $parts = explode('.', $version);

        return $parts[0];
    }
}
