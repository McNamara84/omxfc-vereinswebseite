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
        Schema::create('fantreffen_anmeldungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('vorname')->nullable();
            $table->string('nachname')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->boolean('tshirt_bestellt')->default(false);
            $table->enum('tshirt_groesse', ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'])->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'free'])->default('pending');
            $table->decimal('payment_amount', 8, 2)->nullable();
            $table->boolean('tshirt_fertig')->default(false);
            $table->boolean('zahlungseingang')->default(false);
            $table->string('paypal_transaction_id')->nullable();
            $table->boolean('ist_mitglied')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fantreffen_anmeldungen');
    }
};
