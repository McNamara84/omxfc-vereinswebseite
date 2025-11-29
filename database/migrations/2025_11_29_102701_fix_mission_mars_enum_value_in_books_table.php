<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL requires modifying the ENUM to include the new value
        // First, update any existing rows with the old value
        DB::statement("UPDATE books SET type = 'Mission Mars' WHERE type = 'Mission Mars'");
        
        // Then modify the column to use the correct ENUM values matching BookType enum
        DB::statement("ALTER TABLE books MODIFY COLUMN type ENUM(
            'Maddrax - Die dunkle Zukunft der Erde',
            'Maddrax-Hardcover',
            'Mission Mars-Heftromane',
            'Das Volk der Tiefe',
            '2012 - Das Jahr der Apokalypse',
            'Die Abenteurer'
        ) NOT NULL DEFAULT 'Maddrax - Die dunkle Zukunft der Erde'");
        
        // Update existing Mission Mars entries to the new enum value
        DB::statement("UPDATE books SET type = 'Mission Mars-Heftromane' WHERE type = 'Mission Mars'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to old enum value
        DB::statement("UPDATE books SET type = 'Mission Mars' WHERE type = 'Mission Mars-Heftromane'");
        
        DB::statement("ALTER TABLE books MODIFY COLUMN type ENUM(
            'Maddrax - Die dunkle Zukunft der Erde',
            'Maddrax-Hardcover',
            'Mission Mars',
            'Das Volk der Tiefe',
            '2012 - Das Jahr der Apokalypse',
            'Die Abenteurer'
        ) NOT NULL DEFAULT 'Maddrax - Die dunkle Zukunft der Erde'");
    }
};
