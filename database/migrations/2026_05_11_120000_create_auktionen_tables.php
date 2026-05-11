<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auktionen', function (Blueprint $table): void {
            $table->id();
            $table->string('titel');
            $table->text('beschreibung_markdown')->nullable();
            $table->unsignedInteger('startbetrag_cent');
            $table->unsignedInteger('mindestschritt_cent');
            $table->string('status')->default('laufend');
            $table->foreignId('verkauft_an_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('verkauft_gebot_id')->nullable();
            $table->timestamp('verkauft_at')->nullable();
            $table->timestamps();
        });

        Schema::create('auktion_gebote', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('auktion_id')->constrained('auktionen')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('bieter_name');
            $table->unsignedInteger('betrag_cent');
            $table->timestamps();

            $table->index(['auktion_id', 'created_at']);
        });

        Schema::table('auktionen', function (Blueprint $table): void {
            $table->foreign('verkauft_gebot_id')
                ->references('id')
                ->on('auktion_gebote')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('auktionen', function (Blueprint $table): void {
            $table->dropForeign(['verkauft_gebot_id']);
        });

        Schema::dropIfExists('auktion_gebote');
        Schema::dropIfExists('auktionen');
    }
};
