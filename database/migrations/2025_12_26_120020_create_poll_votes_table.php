<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('polls')->cascadeOnDelete();
            $table->foreignId('poll_option_id')->constrained('poll_options')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->string('voter_type'); // member|guest
            $table->timestamps();

            // Uniqueness rules (enforced in application logic as well):
            // - Internal polls: one vote per user (ip_hash is null)
            // - Public polls: one vote per ip_hash (user_id may be set when logged in)
            // Note: unique constraints with NULL columns behave slightly differently across engines.
            // The service layer ensures we always persist at least one identifier.
            $table->unique(['poll_id', 'user_id']);
            $table->unique(['poll_id', 'ip_hash']);

            $table->index(['poll_id', 'created_at']);
            $table->index(['poll_option_id', 'created_at']);
            $table->index(['poll_id', 'voter_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
    }
};
