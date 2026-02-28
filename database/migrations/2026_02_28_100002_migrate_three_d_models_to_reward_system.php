<?php

use App\Models\Reward;
use App\Models\ThreeDModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        ThreeDModel::whereNull('reward_id')->chunkById(100, function ($models) {
            foreach ($models as $model) {
                $baseSlug = Str::slug($model->name);
                $slug = '3d-'.$baseSlug;
                $counter = 2;

                while (Reward::where('slug', $slug)->exists()) {
                    $slug = '3d-'.$baseSlug.'-'.$counter;
                    $counter++;
                }

                $reward = Reward::create([
                    'title' => $model->name,
                    'description' => Str::limit($model->description, 200),
                    'category' => '3D-Modelle',
                    'slug' => $slug,
                    'cost_baxx' => $model->required_baxx,
                    'is_active' => true,
                    'sort_order' => 0,
                ]);

                $model->update(['reward_id' => $reward->id]);
            }
        });
    }

    public function down(): void
    {
        // Verknüpfte Rewards der 3D-Modelle entfernen
        $rewardIds = ThreeDModel::whereNotNull('reward_id')->pluck('reward_id');
        ThreeDModel::whereNotNull('reward_id')->update(['reward_id' => null]);
        Reward::whereIn('id', $rewardIds)->delete();
    }
};
