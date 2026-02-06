<?php

use App\Models\Site;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $types = ['laravel', 'php'];

        foreach ($types as $type) {
            $path = resource_path('deployment-scripts/' . $type . '.sh');
            if (File::exists($path)) {
                $content = File::get($path);
                
                // Get all sites of this type
                Site::where('type', $type)->chunk(100, function ($sites) use ($content) {
                    foreach ($sites as $site) {
                        // Update default deployment script
                        $site->deploymentScript()->update([
                            'content' => $content
                        ]);
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed as we want to keep the improved scripts
    }
};