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
        Schema::create('audiobook_episodes', function (Blueprint $table) {
            $table->id();
            $table->string('episode_number')->unique();
            $table->string('title');
            $table->string('author');
            $table->date('planned_release_date')->nullable();
            $table->enum('status', [
                'Skripterstellung',
                'Korrekturlesung',
                'Rollenbesetzung',
                'Aufnahmensammlung',
                'Musikerstellung',
                'Audiobearbeitung',
                'Videobearbeitung',
                'Grafiken',
                'Veröffentlichungsplanung',
                'Veröffentlichung',
            ]);
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audiobook_episodes');
    }
};
