<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix the Mission Mars ENUM value to match the BookType enum.
     * 
     * The database had 'Mission Mars' but BookType::MissionMars uses 'Mission Mars-Heftromane'.
     * This migration updates the ENUM to use the correct value.
     * 
     * Note: 'Das Volk der Tiefe' intentionally does NOT have the '-Heftromane' suffix
     * in the BookType enum, matching the established naming convention in this project.
     * The Wiki category URL uses 'Das_Volk_der_Tiefe-Heftromane' but the database value
     * is 'Das Volk der Tiefe' as defined in App\Enums\BookType::DasVolkDerTiefe.
     */
    public function up(): void
    {
        // Only run on MySQL/MariaDB - SQLite doesn't have ENUM type and uses TEXT instead
        if (DB::connection()->getDriverName() === 'mysql') {
            // First, modify the ENUM to include BOTH old and new values temporarily
            DB::statement("ALTER TABLE books MODIFY COLUMN type ENUM(
                'Maddrax - Die dunkle Zukunft der Erde',
                'Maddrax-Hardcover',
                'Mission Mars',
                'Mission Mars-Heftromane',
                'Das Volk der Tiefe',
                '2012 - Das Jahr der Apokalypse',
                'Die Abenteurer'
            ) NOT NULL DEFAULT 'Maddrax - Die dunkle Zukunft der Erde'");
            
            // Update existing Mission Mars entries to the new enum value
            DB::statement("UPDATE books SET type = 'Mission Mars-Heftromane' WHERE type = 'Mission Mars'");
            
            // Now remove the old value from the ENUM
            DB::statement("ALTER TABLE books MODIFY COLUMN type ENUM(
                'Maddrax - Die dunkle Zukunft der Erde',
                'Maddrax-Hardcover',
                'Mission Mars-Heftromane',
                'Das Volk der Tiefe',
                '2012 - Das Jahr der Apokalypse',
                'Die Abenteurer'
            ) NOT NULL DEFAULT 'Maddrax - Die dunkle Zukunft der Erde'");
        }
        // For SQLite: The schema already uses TEXT for type column, so just update existing values
        else {
            DB::table('books')
                ->where('type', 'Mission Mars')
                ->update(['type' => 'Mission Mars-Heftromane']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
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
        } else {
            DB::table('books')
                ->where('type', 'Mission Mars-Heftromane')
                ->update(['type' => 'Mission Mars']);
        }
    }
};
