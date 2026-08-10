<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maddraxikon_reward_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status', 16)->default('draft');
            $table->timestamp('effective_from')->nullable();
            $table->unsignedBigInteger('effective_from_epoch')->nullable()->unique();
            $table->boolean('edit_sessions_enabled')->default(true);
            $table->boolean('new_articles_enabled')->default(true);
            $table->unsignedBigInteger('new_article_minimum_bytes')->nullable();
            $table->unsignedInteger('new_article_points')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_at_epoch')->nullable();
            $table->timestamps();

            $table->index(
                ['status', 'effective_from_epoch'],
                'maddraxikon_reward_policies_status_effective_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maddraxikon_reward_policies');
    }
};
