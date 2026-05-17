<?php

use App\Enums\NewsletterAusgabeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_ausgaben', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->string('slug')->unique();
            $table->json('topics');
            $table->json('recipient_roles');
            $table->string('status')->default(NewsletterAusgabeStatus::Entwurf->value);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_ausgaben');
    }
};