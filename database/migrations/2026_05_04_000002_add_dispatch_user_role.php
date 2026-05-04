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

        if (DB::getDriverName() === 'mysql') {
            $values = implode("','", UserRole::values());

            DB::statement("ALTER TABLE users MODIFY role ENUM('{$values}') NOT NULL DEFAULT '".UserRole::Pilot->value."'");
        }
    }
};
