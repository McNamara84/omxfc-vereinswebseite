<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('zoom_url', 2048)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('time_from', 5)->nullable();
            $table->string('time_to', 5)->nullable();
            $table->string('rhythm_type');
            $table->unsignedTinyInteger('interval_weeks')->nullable();
            $table->date('starts_on')->nullable();
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->unsignedTinyInteger('week_of_month')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->text('rhythm_note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $timestamp = now();

        DB::table('meetings')->insert([
            [
                'title' => 'AG Maddraxikon',
                'slug' => 'maddraxikon',
                'zoom_url' => null,
                'is_active' => true,
                'sort_order' => 10,
                'time_from' => '20:00',
                'time_to' => '20:30',
                'rhythm_type' => 'monthly_nth_weekday',
                'interval_weeks' => null,
                'starts_on' => null,
                'weekday' => 1,
                'week_of_month' => 3,
                'day_of_month' => null,
                'rhythm_note' => null,
                'updated_by' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'title' => 'AG EARDRAX',
                'slug' => 'fanhoerbuch',
                'zoom_url' => null,
                'is_active' => true,
                'sort_order' => 20,
                'time_from' => '19:00',
                'time_to' => '19:30',
                'rhythm_type' => 'monthly_nth_weekday',
                'interval_weeks' => null,
                'starts_on' => null,
                'weekday' => 3,
                'week_of_month' => 2,
                'day_of_month' => null,
                'rhythm_note' => null,
                'updated_by' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'title' => 'AG MAPDRAX',
                'slug' => 'mapdrax',
                'zoom_url' => null,
                'is_active' => true,
                'sort_order' => 30,
                'time_from' => '20:00',
                'time_to' => '20:30',
                'rhythm_type' => 'monthly_nth_weekday',
                'interval_weeks' => null,
                'starts_on' => null,
                'weekday' => 3,
                'week_of_month' => 1,
                'day_of_month' => null,
                'rhythm_note' => null,
                'updated_by' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'title' => 'CHATDRAX 2.0 - Der MADDRAX-Online-Stammtisch',
                'slug' => 'stammtisch',
                'zoom_url' => null,
                'is_active' => true,
                'sort_order' => 40,
                'time_from' => '20:00',
                'time_to' => null,
                'rhythm_type' => 'note_only',
                'interval_weeks' => null,
                'starts_on' => null,
                'weekday' => null,
                'week_of_month' => null,
                'day_of_month' => null,
                'rhythm_note' => 'Jeden zweiten Dienstag nach einem Roman',
                'updated_by' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
