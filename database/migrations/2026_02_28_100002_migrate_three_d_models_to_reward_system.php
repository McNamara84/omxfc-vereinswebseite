<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('three_d_models')
            ->whereNull('reward_id')
            ->orderBy('id')
            ->chunkById(100, function ($models) {
                foreach ($models as $model) {
                    $baseSlug = Str::slug($model->name);
                    $slug = '3d-'.$baseSlug;
                    $counter = 2;

                    while (DB::table('rewards')->where('slug', $slug)->exists()) {
                        $slug = '3d-'.$baseSlug.'-'.$counter;
                        $counter++;
                    }

                    $rewardId = DB::table('rewards')->insertGetId([
                        'title' => $model->name,
                        'description' => Str::limit($model->description, 200),
                        'category' => '3D-Modelle',
                        'slug' => $slug,
                        'cost_baxx' => $model->required_baxx,
                        'is_active' => true,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('three_d_models')
                        ->where('id', $model->id)
                        ->update(['reward_id' => $rewardId]);
                }
            });
    }

    public function down(): void
    {
        // Nur reward_id zurücksetzen und Rewards löschen, die von dieser Migration stammen
        $rewardIds = DB::table('three_d_models')
            ->whereNotNull('reward_id')
            ->pluck('reward_id');

        DB::table('three_d_models')
            ->whereNotNull('reward_id')
            ->update(['reward_id' => null]);

        DB::table('rewards')
            ->whereIn('id', $rewardIds)
            ->where('category', '3D-Modelle')
            ->delete();
    }
};
