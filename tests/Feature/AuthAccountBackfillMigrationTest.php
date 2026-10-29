<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthAccountBackfillMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.auth_backfill_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('auth_backfill_test');

        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_07_26_000001_create_auth_accounts_table.php'))->up();
    }

    protected function tearDown(): void
    {
        DB::purge('auth_backfill_test');

        parent::tearDown();
    }

    public function test_backfill_preserves_user_data_and_existing_accounts_across_chunks(): void
    {
        $this->travelTo(now()->setDateTime(2026, 7, 26, 12, 0, 0));

        $users = [];

        for ($id = 1; $id <= 205; $id++) {
            $users[] = [
                'id' => $id,
                'name' => 'Pilot '.$id,
                'email' => 'pilot'.$id.'@example.test',
                'password' => '$2y$10$existing.password.hash.'.$id,
                'email_verified_at' => $id % 2 === 0 ? '2026-07-01 10:20:00' : null,
                'last_login_at' => $id % 3 === 0 ? '2026-07-25 11:30:00' : null,
            ];
        }

        DB::table('users')->insert($users);
        DB::table('auth_accounts')->insert([
            'user_id' => 1,
            'provider' => 'external',
            'provider_user_id' => 'existing-provider-id',
            'email' => 'external@example.test',
        ]);

        $originalUsers = DB::table('users')->orderBy('id')->get()->toArray();
        $existingAccount = DB::table('auth_accounts')->first();

        $this->runBackfill();

        $this->assertEquals($originalUsers, DB::table('users')->orderBy('id')->get()->toArray());
        $this->assertEquals($existingAccount, DB::table('auth_accounts')->where('provider', 'external')->first());
        $this->assertSame(206, DB::table('auth_accounts')->count());

        $accounts = DB::table('auth_accounts')->where('provider', 'password')->orderBy('user_id')->get();
        $this->assertCount(205, $accounts);

        foreach ($accounts as $index => $account) {
            $user = $users[$index];

            $this->assertEquals([
                'user_id' => $user['id'],
                'provider' => 'password',
                'identifier' => $user['email'],
                'password_hash' => $user['password'],
                'provider_user_id' => null,
                'email' => $user['email'],
                'email_verified_at' => $user['email_verified_at'],
                'last_login_at' => $user['last_login_at'],
                'last_login_ip' => null,
                'created_at' => '2026-07-26 12:00:00',
                'updated_at' => '2026-07-26 12:00:00',
            ], array_diff_key((array) $account, ['id' => true]));
        }
    }

    public function test_backfill_handles_an_empty_users_table(): void
    {
        $this->runBackfill();

        $this->assertSame(0, DB::table('auth_accounts')->count());
    }

    private function runBackfill(): void
    {
        (require database_path('migrations/2026_07_26_000003_backfill_auth_accounts_from_users.php'))->up();
    }
}
