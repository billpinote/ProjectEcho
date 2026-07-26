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
        DB::table('users')->orderBy('id')->chunk(100, function (array $users): void {
            $rows = [];

            foreach ($users as $user) {
                $rows[] = [
                    'user_id' => $user->id,
                    'provider' => 'password',
                    'identifier' => $user->email,
                    'password_hash' => $user->password,
                    'provider_user_id' => null,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'last_login_at' => $user->last_login_at,
                    'last_login_ip' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('auth_accounts')->insert($rows);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('auth_accounts')->where('provider', 'password')->delete();
    }
};
