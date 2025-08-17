<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\BookType;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->enum('type', array_map(fn($case) => $case->value, BookType::cases()))
                ->default(BookType::MaddraxDieDunkleZukunftDerErde->value);
            $table->dropUnique('books_roman_number_unique');
            $table->unique(['roman_number', 'type'], 'books_roman_number_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropUnique('books_roman_number_type_unique');
            $table->unique('roman_number');
            $table->dropColumn('type');
        });
    }
};
