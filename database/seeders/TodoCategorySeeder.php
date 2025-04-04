<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\TodoCategory;

class TodoCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Allgemeines',
            'AG Maddraxikon',
            'AG Fanhörbücher',
            'AG MAPDRAX',
            'Fantreffen',
            'MaddraxCon',
        ];

        foreach ($categories as $categoryName) {
            TodoCategory::create([
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
            ]);
        }
    }
}
