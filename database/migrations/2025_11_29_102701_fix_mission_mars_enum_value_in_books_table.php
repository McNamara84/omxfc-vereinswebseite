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
