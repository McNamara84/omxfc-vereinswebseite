<?php

use App\Enums\TourAssignmentSource;
use App\Enums\TourAssignmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tour_key');
            $table->unsignedInteger('tour_version');
            $table->string('status')->default(TourAssignmentStatus::Pending->value);
            $table->string('assigned_via')->default(TourAssignmentSource::System->value);
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('next_prompt_at')->nullable();
            $table->string('current_step_key')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tour_key', 'tour_version'], 'tour_assignments_user_tour_version_unique');
            $table->index(['status', 'next_prompt_at']);
            $table->index(['tour_key', 'tour_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_assignments');
    }
};