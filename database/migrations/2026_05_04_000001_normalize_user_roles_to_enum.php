<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::table('users')->whereRaw('UPPER(role) = ?', ['ADMIN'])->update(['role' => UserRole::Admin->value]);
        DB::table('users')->whereRaw('UPPER(role) = ?', ['ARTISAN'])->update(['role' => UserRole::Artisan->value]);
        DB::table('users')->whereRaw('UPPER(role) IN (?, ?)', ['ATC', 'ATMO'])->update(['role' => UserRole::Atmo->value]);
        DB::table('users')->whereRaw('UPPER(role) = ?', ['ATSHQ'])->update(['role' => UserRole::AtsHq->value]);
        DB::table('users')->whereRaw('UPPER(role) = ?', ['AVSEC'])->update(['role' => UserRole::Avsec->value]);
        DB::table('users')->whereRaw('UPPER(role) = ?', ['PILOT'])->update(['role' => UserRole::Pilot->value]);
        DB::table('users')
            ->whereNotIn('role', UserRole::values())
            ->orWhereNull('role')
            ->update(['role' => UserRole::Pilot->value]);

        if (DB::getDriverName() === 'mysql') {
            $values = implode("','", UserRole::values());

            DB::statement("ALTER TABLE users MODIFY role ENUM('{$values}') NOT NULL DEFAULT '".UserRole::Pilot->value."'");
        }
    }
};
