<?php

use App\Enums\KassenbuchEditRequestType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kassenbuch_edit_requests', function (Blueprint $table) {
            $table->string('request_type')->default(KassenbuchEditRequestType::Edit->value)->after('reason_text');
        });

        DB::table('kassenbuch_edit_requests')
            ->whereNull('request_type')
            ->update(['request_type' => KassenbuchEditRequestType::Edit->value]);
    }

    public function down(): void
    {
        Schema::table('kassenbuch_edit_requests', function (Blueprint $table) {
            $table->dropColumn('request_type');
        });
    }
};