<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pilot_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('pilot_profiles', 'home_base')) {
                $table->renameColumn('home_base', 'operator');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pilot_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('pilot_profiles', 'operator')) {
                $table->renameColumn('operator', 'home_base');
            }
        });
    }
};
