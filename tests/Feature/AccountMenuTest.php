<?php

namespace Tests\Feature;

use App\Domain\Users\Enums\UserRole;
use App\Models\Operator;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountMenuTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('accounts')]
    public function test_menu_order_links_and_own_profile(string $panel, UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role, 'station' => 'RPUS']);
        $other = User::factory()->create(['email' => 'other-account@example.test']);
        [$relationship, $field] = match ($role) {
            UserRole::Pilot => ['pilotProfile', 'license_number'],
            UserRole::Atmo, UserRole::AtsHq => ['atcProfile', 'facility'],
            UserRole::Dispatch => ['dispatchProfile', 'department'],
            UserRole::OperatorStaff => ['operatorStaffProfile', 'position_title'],
            UserRole::Avsec => ['avsecProfile', 'security_certification'],
            default => [null, null],
        };
        if ($relationship !== null) {
            $user->{$relationship}()->create([$field => 'OWN-ROLE-DETAIL']);
            $other->{$relationship}()->create([$field => 'OTHER-ROLE-DETAIL']);
        }
        $this->actingAs($user);
        Filament::setCurrentPanel($panel);
        $items = array_values(Filament::getUserMenuItems());
        $this->assertSame(['View Profile', 'Preferences', 'Security', 'Help', 'Sign out'],
            array_map(fn ($item) => $item->getLabel(), $items));
        foreach (['profile', 'preferences', 'security', 'help'] as $index => $slug) {
            $url = route("filament.{$panel}.pages.{$slug}");
            $this->assertSame($url, $items[$index]->getUrl());
            $this->get($url)->assertOk();
        }
        $this->assertSame(route("filament.{$panel}.auth.logout"), $items[4]->getUrl());
        $response = $this->get(route("filament.{$panel}.pages.profile", ['user' => $other->id, 'record' => $other->id]))
            ->assertOk()->assertSeeText($user->email)->assertDontSeeText($other->email);
        if ($relationship !== null) {
            $response->assertSeeText('OWN-ROLE-DETAIL')->assertDontSeeText('OTHER-ROLE-DETAIL');
        }
        $this->get("/{$panel}/profile/{$other->id}")->assertNotFound();
    }

    #[DataProvider('accounts')]
    public function test_preferences_only_update_own_display_name(string $panel, UserRole $role): void
    {
        $operator = Operator::factory()->create();
        $user = User::factory()->create(['role' => $role, 'station' => 'RPUS', 'operator_id' => $operator->id]);
        $other = User::factory()->create(['display_name' => 'Other Account', 'operator_id' => Operator::factory()->create()->id]);
        $original = $user->only(['first_name', 'last_name', 'email', 'operator_id', 'is_active']);
        $this->actingAs($user);
        Filament::setCurrentPanel($panel);
        $page = 'App\\Filament\\Panels\\'.ucfirst($panel).'\\Pages\\PreferencesPage';
        Livewire::test($page)->fillForm([
            'display_name' => 'My updated name', 'role' => UserRole::Artisan->value,
            'operator_id' => $other->operator_id, 'user_id' => $other->id,
        ])->call('save')->assertHasNoFormErrors()
            ->assertRedirect(route("filament.{$panel}.pages.profile"));
        $this->assertSame('My updated name', $user->fresh()->display_name);
        $this->assertSame($role, $user->fresh()->role);
        $this->assertSame($original, $user->fresh()->only(array_keys($original)));
        $this->assertSame('Other Account', $other->fresh()->display_name);
        $this->assertDatabaseCount('profile_update_requests', 0);
    }

    #[DataProvider('accounts')]
    public function test_pages_reject_guests_inactive_users_and_wrong_roles(string $panel, UserRole $role): void
    {
        foreach (['profile', 'preferences', 'security', 'help'] as $slug) {
            $this->get(route("filament.{$panel}.pages.{$slug}"))
                ->assertRedirect(route("filament.{$panel}.auth.login"));
        }
        $user = User::factory()->create(['role' => $role, 'is_active' => false, 'station' => 'RPUS']);
        foreach (['profile', 'preferences', 'security', 'help'] as $slug) {
            $this->actingAs($user)->get(route("filament.{$panel}.pages.{$slug}"))->assertForbidden();
        }
        $user = User::factory()->create(['role' => $panel === 'pilot' ? UserRole::Dispatch : UserRole::Pilot]);
        foreach (['profile', 'preferences', 'security', 'help'] as $slug) {
            $this->actingAs($user)->get(route("filament.{$panel}.pages.{$slug}"))->assertForbidden();
        }
    }

    #[DataProvider('artisanPanels')]
    public function test_artisan_sees_own_account_in_every_panel(string $panel): void
    {
        $artisan = User::factory()->create(['role' => UserRole::Artisan]);
        $this->actingAs($artisan);
        Filament::setCurrentPanel($panel);
        $this->get(route("filament.{$panel}.pages.profile"))->assertOk()->assertSeeText($artisan->email);
        $this->get(route("filament.{$panel}.pages.preferences"))->assertOk();
        $this->assertFalse($artisan->pilotProfile()->exists());
    }

    #[DataProvider('accounts')]
    public function test_logout_clears_session_rotates_tokens_and_blocks_protected_pages(string $panel, UserRole $role): void
    {
        $this->enforceCsrf();
        $user = User::factory()->create(['role' => $role, 'station' => 'RPUS']);
        $this->actingAs($user)->withSession(['_token' => 'old-token', 'account_marker' => 'remove-me']);
        $sessionId = session()->getId();
        $this->post(route("filament.{$panel}.auth.logout"), ['_token' => 'old-token'])
            ->assertRedirect(route('gateway'))->assertSessionMissing('account_marker');
        $this->assertGuest();
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame('old-token', session()->token());
        $this->get("/{$panel}")->assertRedirect(route("filament.{$panel}.auth.login"));
        $this->get(route("filament.{$panel}.pages.profile"))->assertRedirect(route("filament.{$panel}.auth.login"));
    }

    #[DataProvider('accounts')]
    public function test_expired_logout_with_stale_csrf_redirects_home(string $panel, UserRole $role): void
    {
        $this->enforceCsrf();
        $this->withSession(['_token' => 'new-session-token', 'account_marker' => 'remove-me'])
            ->post(route("filament.{$panel}.auth.logout"), ['_token' => 'expired-token'])
            ->assertRedirect(route('gateway'))->assertSessionMissing('account_marker');
        $this->assertGuest();
        $this->assertNotSame('new-session-token', session()->token());
    }

    public function test_authenticated_logout_still_requires_valid_csrf(): void
    {
        $this->enforceCsrf();
        $user = User::factory()->create(['role' => UserRole::Pilot]);
        $this->actingAs($user)->withSession(['_token' => 'valid-token'])
            ->post(route('filament.pilot.auth.logout'), ['_token' => 'wrong-token'])->assertStatus(419);
        $this->assertAuthenticatedAs($user);
    }

    private function enforceCsrf(): void
    {
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }

    public static function accounts(): array
    {
        return [
            'pilot' => ['pilot', UserRole::Pilot], 'admin' => ['admin', UserRole::Admin],
            'artisan' => ['artisan', UserRole::Artisan], 'atmo' => ['atmo', UserRole::Atmo],
            'ats' => ['ats', UserRole::AtsHq], 'avsec' => ['avsec', UserRole::Avsec],
            'dispatch' => ['dispatch', UserRole::Dispatch], 'operator staff' => ['dispatch', UserRole::OperatorStaff],
        ];
    }

    public static function artisanPanels(): array
    {
        return array_map(fn (string $panel): array => [$panel], ['pilot', 'admin', 'artisan', 'atmo', 'ats', 'avsec', 'dispatch']);
    }
}
