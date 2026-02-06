<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SourceControl;
use App\Services\RepositoryAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use ZipArchive;

#[Prefix('api/analysis')]
#[Middleware('auth:sanctum')]
class AnalysisController extends Controller
{
    public function __construct(protected RepositoryAnalyzer $analyzer)
    {
    }

    #[Post('git', name: 'api.analysis.git')]
    public function analyzeGit(Request $request): JsonResponse
    {
        $request->validate([
            'source_control_id' => 'required|exists:source_controls,id',
            'repository' => 'required|string',
            'branch' => 'required|string',
        ]);

        $sourceControl = SourceControl::findOrFail($request->input('source_control_id'));
        // Authorize? sourceControl->user_id == auth()->id()
        if ($sourceControl->user_id !== auth()->id()) {
            abort(403);
        }

        $result = $this->analyzer->analyze(
            $sourceControl,
            $request->input('repository'),
            $request->input('branch')
        );

        return response()->json($result->toArray());
    }

    #[Post('zip', name: 'api.analysis.zip')]
    public function analyzeZip(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:zip',
        ]);

        $file = $request->file('file');
        $path = $file->store('temp-analysis');
        $fullPath = Storage::path($path);
        $folderName = 'temp-analysis/extract-' . uniqid();
        $extractPath = Storage::path($folderName);

        mkdir($extractPath, 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($fullPath) === true) {
            $zip->extractTo($extractPath);
            $zip->close();
        } else {
            return response()->json(['error' => 'Failed to extract zip'], 400);
        }

        try {
            $result = $this->analyzer->analyzeZip($extractPath);

            return response()->json($result->toArray());
        } finally {
            Storage::deleteDirectory($folderName);
            Storage::delete($path);
        }
    }
}
