<?php

use App\Enums\BookType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('type')->default(BookType::MaddraxDieDunkleZukunftDerErde->value);
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
