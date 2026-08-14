<?php

namespace Tests\Feature;

use App\Domain\Users\Enums\UserRole;
use App\Models\Operator;
use App\Models\OperatorStaffProfile;
use App\Models\User;
use Database\Seeders\OperatorStaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperatorStaffSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_staff_seeder_is_idempotent_and_links_pedro_to_alpha_aviation_group(): void
    {
        $this->seed(OperatorStaffSeeder::class);
        $this->seed(OperatorStaffSeeder::class);

        $operator = Operator::where('name', 'Alpha Aviation Group')->firstOrFail();
        $user = User::where('email', 'pedro.santos@example.com')->firstOrFail();
        $profile = OperatorStaffProfile::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(1, User::where('email', 'pedro.santos@example.com')->count());
        $this->assertSame(1, OperatorStaffProfile::where('user_id', $user->id)->count());
        $this->assertSame('Pedro Santos', $user->name);
        $this->assertSame('pedro.santos', $user->username);
        $this->assertSame(UserRole::OperatorStaff, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertTrue($user->operator->is($operator));
        $this->assertTrue($profile->user->is($user));
        $this->assertTrue($profile->operator->is($operator));
        $this->assertSame('Aircraft Mechanic', $profile->position_title);
        $this->assertSame('AAG-MECH-001', $profile->company_employee_id);
        $this->assertSame('AAG-FPL-REP-001', $profile->authorization_reference);
        $this->assertSame('2028-12-31', $profile->authorization_expiry_date?->toDateString());
        $this->assertTrue($profile->is_authorized);
        $this->assertSame('Authorized operator representative for flight plan preparation and filing.', $profile->remarks);
    }
}
