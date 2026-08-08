<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PROFILE_TABLES = [
        'pilot_profiles' => 'pilot_profiles_user_id_unique',
        'dispatch_profiles' => 'dispatch_profiles_user_id_unique',
        'atc_profiles' => 'atc_profiles_user_id_unique',
        'avsec_profiles' => 'avsec_profiles_user_id_unique',
    ];

    public function up(): void
    {
        foreach (self::PROFILE_TABLES as $tableName => $indexName) {
            if (
                ! Schema::hasTable($tableName)
                || ! Schema::hasColumn($tableName, 'user_id')
                || Schema::hasIndex($tableName, $indexName)
                || $this->hasDuplicateUserIds($tableName)
            ) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->unique('user_id', $indexName);
            });
        }
    }

    public function down(): void
    {
        foreach (self::PROFILE_TABLES as $tableName => $indexName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasIndex($tableName, $indexName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->dropUnique($indexName);
            });
        }
    }

    private function hasDuplicateUserIds(string $tableName): bool
    {
        return DB::table($tableName)
            ->select('user_id')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->havingRaw('count(*) > 1')
            ->exists();
    }
};
