<?php

namespace Tests\Feature;

use App\Auth\AuthenticationEvidence;
use App\Domain\Users\Enums\UserRole;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_filament_login_uses_the_password_auth_account(): void
    {
        $pilot = User::factory()->create(['role' => UserRole::Pilot]);
        $pilot->authAccounts()->create([
            'provider' => 'password', 'email' => $pilot->email,
            'password_hash' => Hash::make('account-password'),
        ]);
        Filament::setCurrentPanel(Filament::getPanel('pilot'));

        Livewire::test(Login::class)
            ->fillForm(['email' => $pilot->email, 'password' => 'account-password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($pilot);
        $this->get('/pilot')->assertOk();
        $this->assertSame([
            'user_id' => $pilot->id, 'method' => 'password_session', 'freshly_verified' => false,
        ], AuthenticationEvidence::fromSession(Auth::guard('web'))->auditValues());
    }

    public function test_legacy_user_password_still_authenticates_without_an_auth_account(): void
    {
        $pilot = User::factory()->create(['role' => UserRole::Pilot]);
        Filament::setCurrentPanel(Filament::getPanel('pilot'));

        Livewire::test(Login::class)
            ->fillForm(['email' => $pilot->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($pilot);
    }

    public function test_wrong_password_cannot_create_an_authenticated_session(): void
    {
        $pilot = User::factory()->create(['role' => UserRole::Pilot]);
        Filament::setCurrentPanel(Filament::getPanel('pilot'));

        Livewire::test(Login::class)
            ->fillForm(['email' => $pilot->email, 'password' => 'incorrect'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_password_login_still_enforces_role_and_active_panel_access(): void
    {
        $pilot = User::factory()->create(['role' => UserRole::Pilot]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Login::class)
            ->fillForm(['email' => $pilot->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
        $this->assertGuest();

        $pilot->update(['is_active' => false]);
        Filament::setCurrentPanel(Filament::getPanel('pilot'));
        Livewire::test(Login::class)
            ->fillForm(['email' => $pilot->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
        $this->assertGuest();
    }

    public function test_a_guest_cannot_supply_session_evidence(): void
    {
        $this->expectException(AuthorizationException::class);

        AuthenticationEvidence::fromSession(Auth::guard('web'));
    }

    public function test_an_inactive_session_cannot_supply_evidence(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => false]));
        $this->expectException(AuthorizationException::class);

        AuthenticationEvidence::fromSession(Auth::guard('web'));
    }
}
