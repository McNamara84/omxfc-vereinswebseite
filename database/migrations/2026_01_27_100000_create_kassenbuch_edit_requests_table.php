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
        Schema::create('kassenbuch_edit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kassenbuch_entry_id')->constrained('kassenbuch_entries')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason_type');
            $table->text('reason_text')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('rejection_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['kassenbuch_entry_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kassenbuch_edit_requests');
    }
};
