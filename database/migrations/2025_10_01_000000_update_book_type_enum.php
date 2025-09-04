<?php

use App\Enums\BookType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $values = array_map(fn ($case) => "'{$case->value}'", BookType::cases());
        $valuesString = implode(',', $values);
        DB::statement("ALTER TABLE books MODIFY COLUMN type ENUM($valuesString) NOT NULL DEFAULT '".BookType::MaddraxDieDunkleZukunftDerErde->value."'");
    }

    public function down(): void
    {
        $values = array_filter(BookType::cases(), fn ($case) => $case !== BookType::MissionMars);
        $values = array_map(fn ($case) => "'{$case->value}'", $values);
        $valuesString = implode(',', $values);
        DB::statement("ALTER TABLE books MODIFY COLUMN type ENUM($valuesString) NOT NULL DEFAULT '".BookType::MaddraxDieDunkleZukunftDerErde->value."'");
    }
};
