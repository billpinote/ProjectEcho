<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username')->unique()->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'employee_id')) {
                $table->string('employee_id')->unique()->nullable()->after('username');
            }

            if (! Schema::hasColumn('users', 'wiresign')) {
                $table->string('wiresign')->unique()->nullable()->after('employee_id');
            }

            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('atc')->after('password');
            }

            if (! Schema::hasColumn('users', 'station')) {
                $table->string('station')->nullable()->after('role');
            }

            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('station');
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['username', 'employee_id', 'wiresign', 'role', 'station', 'is_active', 'last_login_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
