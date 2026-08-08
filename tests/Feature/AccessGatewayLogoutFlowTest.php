<?php

namespace Tests\Feature;

use App\Domain\Users\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessGatewayLogoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_loads_with_all_panel_links_and_public_flight_plan_link(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSeeText('Project Echo')
            ->assertSeeText('Select your access portal')
            ->assertSeeText('Operational Access')
            ->assertSeeText('System Administration')
            ->assertSee(route('filament.pilot.auth.login'), false)
            ->assertSee(route('filament.atmo.auth.login'), false)
            ->assertSee(route('filament.dispatch.auth.login'), false)
            ->assertSee(route('filament.avsec.auth.login'), false)
            ->assertSee(route('filament.ats.auth.login'), false)
            ->assertSee(route('filament.admin.auth.login'), false)
            ->assertSee(route('filament.artisan.auth.login'), false)
            ->assertSee(route('flightplan'), false);
    }

    public function test_authenticated_users_can_still_use_gateway(): void
    {
        $this->actingAs($this->user(UserRole::Admin))
            ->get('/')
            ->assertOk()
            ->assertSeeText('Select your access portal');
    }

    public function test_public_flight_plan_form_still_loads_at_flightplan(): void
    {
        $this->get('/flightplan')
            ->assertOk()
            ->assertSeeText('Flight Plan Tool');
    }

    public function test_signed_out_page_is_public_and_links_back_to_gateway(): void
    {
        $this->get('/signed-out')
            ->assertOk()
            ->assertSeeText('You have been signed out')
            ->assertSee(route('gateway'), false)
            ->assertSee(route('flightplan'), false);
    }

    #[DataProvider('panelLogoutProvider')]
    public function test_filament_logout_redirects_to_signed_out_and_ends_session(string $panel, UserRole $role): void
    {
        $user = $this->user($role, [
            'station' => $role === UserRole::Atmo ? 'RPUS' : null,
        ]);

        $this->actingAs($user)
            ->post(route("filament.{$panel}.auth.logout"))
            ->assertRedirect(route('signed-out'))
            ->assertStatus(302);

        $this->assertGuest();
    }

    public function test_protected_panel_access_and_role_authorization_remain_unchanged(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->actingAs($this->user(UserRole::Pilot))
            ->get('/dispatch')
            ->assertForbidden();

        $this->actingAs($this->user(UserRole::Atmo, ['station' => 'RPLL']))
            ->get('/atmo')
            ->assertForbidden();
    }

    public function test_artisan_access_to_other_panels_still_uses_existing_authorization(): void
    {
        $artisan = $this->user(UserRole::Artisan);

        foreach (['admin', 'pilot', 'atmo', 'dispatch', 'avsec', 'ats', 'artisan'] as $panel) {
            $this->actingAs($artisan)
                ->get("/{$panel}")
                ->assertOk();
        }
    }

    /**
     * @return array<string, array{string, UserRole}>
     */
    public static function panelLogoutProvider(): array
    {
        return [
            'pilot' => ['pilot', UserRole::Pilot],
            'atmo' => ['atmo', UserRole::Atmo],
            'dispatch' => ['dispatch', UserRole::Dispatch],
            'avsec' => ['avsec', UserRole::Avsec],
            'ats' => ['ats', UserRole::AtsHq],
            'admin' => ['admin', UserRole::Admin],
            'artisan' => ['artisan', UserRole::Artisan],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function user(UserRole $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'station' => $role === UserRole::Atmo ? 'RPUS' : null,
            'is_active' => true,
        ], $attributes));
    }
}
