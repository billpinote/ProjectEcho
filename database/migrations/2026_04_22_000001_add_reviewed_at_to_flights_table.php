<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->timestamp('reviewed_at')->nullable()->after('status');
        });

        DB::table('flights')
            ->whereNull('reviewed_at')
            ->update([
                'reviewed_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->dropColumn('reviewed_at');
        });
    }
};
