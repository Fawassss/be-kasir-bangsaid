<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $configs = \DB::table('landing_configurations')->get();
        $fields = ['hero_image', 'about_image_1', 'about_image_2', 'about_image_3', 'about_image_4'];
        
        // We'll strip common URL prefixes to ensure we only have relative paths
        foreach ($configs as $config) {
            $updates = [];
            foreach ($fields as $field) {
                if ($config->$field) {
                    $val = $config->$field;
                    // If it contains /storage/, we'll take everything after it
                    if (str_contains($val, '/storage/')) {
                        $parts = explode('/storage/', $val);
                        $updates[$field] = end($parts);
                    }
                }
            }
            if (!empty($updates)) {
                \DB::table('landing_configurations')->where('id', $config->id)->update($updates);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
