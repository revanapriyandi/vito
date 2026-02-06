<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;

#[Prefix('servers/{server}/sites/{site}/files')]
#[Middleware(['auth', 'has-project'])]
class SiteFileController extends Controller
{
    /**
     * List files in a directory.
     */
    #[Get('/', name: 'sites.files.index')]
    public function index(Request $request, Server $server, Site $site): JsonResponse
    {
        $this->authorize('view', [$site, $server]);

        $path = $request->get('path', '/');

        if (str_contains($path, '..')) {
            throw ValidationException::withMessages(['path' => 'Invalid path']);
        }

        $root = $site->path;
        // Clean up path handling
        $relativePath = $path;
        if (!str_starts_with($relativePath, '/')) {
            $relativePath = '/' . $relativePath;
        }
        $targetPath = $root . $relativePath;

        // PHP script to run on server to get clean JSON file list
        // Using scandir to include dotfiles
        $phpScript = <<<PHP
        \$path = '$targetPath';
        if (!is_dir(\$path)) { echo '[]'; exit; }
        \$files = scandir(\$path);
        \$result = [];
        foreach (\$files as \$f) {
            if (\$f === '.' || \$f === '..') continue;
            \$fullPath = \$path . '/' . \$f;
            \$result[] = [
                'name' => \$f,
                'path' => \$fullPath,
                'is_dir' => is_dir(\$fullPath),
                'size' => filesize(\$fullPath),
                'mtime' => filemtime(\$fullPath),
                'permissions' => substr(sprintf('%o', fileperms(\$fullPath)), -4),
            ];
        }
        echo json_encode(array_values(\$result));
        PHP;

        // Escape double quotes for shell execution
        $phpScript = str_replace('"', '\"', $phpScript);

        try {
            $output = $site->server->ssh($site->user)->exec("php -r \"$phpScript\"");
        } catch (\Exception $e) {
            // If the folder is empty or access denied, glob might act up or SSH might fail.
            // Return empty list if it fails safely.
            return response()->json([]);
        }

        $files = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($files)) {
            return response()->json([]);
        }

        // Filter and Format
        $formatted = collect($files)->map(function ($file) use ($root) {
            // Remove site root from path to make it relative (files API should return relative paths)
            $cleanPath = str_replace($root, '', $file['path']);
            // Allow for the glob trailing slash on dirs
            if (str_ends_with($file['path'], '/') && !str_ends_with($cleanPath, '/')) {
                $cleanPath .= '/';
            }

            return [
                'name' => $file['name'],
                'path' => $cleanPath,
                'is_dir' => $file['is_dir'],
                'size' => $file['size'],
                'last_modified' => $file['mtime'],
                'permissions' => $file['permissions'],
            ];
        })->sortBy([
                    ['is_dir', 'desc'],
                    ['name', 'asc'],
                ])->values();

        return response()->json($formatted);
    }

    /**
     * Get file content.
     */
    #[Get('/content', name: 'sites.files.show')]
    public function show(Request $request, Server $server, Site $site): JsonResponse
    {
        $this->authorize('view', [$site, $server]);

        $path = $request->get('path');
        if (!$path || str_contains($path, '..')) {
            abort(400, 'Invalid path');
        }

        $targetPath = $site->path . (str_starts_with($path, '/') ? $path : '/' . $path);

        try {
            $content = $site->server->ssh($site->user)->exec("cat '$targetPath'");
            return response()->json(['content' => $content]);
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['path' => 'Could not read file']);
        }
    }

    /**
     * Update file content.
     */
    #[Put('/', name: 'sites.files.update')]
    public function update(Request $request, Server $server, Site $site): JsonResponse
    {
        $this->authorize('update', [$site, $server]);

        $request->validate([
            'path' => 'required|string',
            'content' => 'nullable|string',
        ]);

        $path = $request->input('path');
        if (str_contains($path, '..')) {
            abort(400, 'Invalid path');
        }

        $targetPath = $site->path . (str_starts_with($path, '/') ? $path : '/' . $path);

        try {
            // Using existing SSH helper write method which handles ownership via sudo if needed
            // But since we want to write AS the site user often, passing site user is key.
            // The SSH helper's write method takes $owner.
            $site->server->ssh()->write($targetPath, $request->input('content', ''), $site->user);
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['content' => 'Failed to save file: ' . $e->getMessage()]);
        }

        return response()->json(['message' => 'File saved']);
    }
}
