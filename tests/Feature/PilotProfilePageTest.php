<?php

namespace Tests\Feature;

use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Pilot\Pages\EditMyProfilePage;
use App\Filament\Panels\Pilot\Pages\HelpPage;
use App\Filament\Panels\Pilot\Pages\MyProfilePage;
use App\Filament\Panels\Pilot\Pages\PreferencesPage;
use App\Filament\Panels\Pilot\Pages\SecurityPage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PilotProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_pilot_can_access_pilot_panel(): void
    {
        $pilot = $this->pilot();

        $this->actingAs($pilot)
            ->get('/pilot')
            ->assertOk();
    }

    public function test_active_artisan_can_access_pilot_panel(): void
    {
        $artisan = $this->artisanUser();

        $this->actingAs($artisan)
            ->get('/pilot')
            ->assertOk();
    }

    public function test_profile_pages_require_authentication(): void
    {
        foreach ([
            MyProfilePage::getUrl(panel: 'pilot'),
            EditMyProfilePage::getUrl(panel: 'pilot'),
            PreferencesPage::getUrl(panel: 'pilot'),
            SecurityPage::getUrl(panel: 'pilot'),
            HelpPage::getUrl(panel: 'pilot'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('filament.pilot.auth.login'));
        }
    }

    public function test_pilot_sees_their_own_profile_information(): void
    {
        $pilot = $this->pilot([
            'first_name' => 'Bill',
            'middle_name' => 'Q',
            'last_name' => 'Pilot',
            'email' => 'bill@example.test',
            'station' => 'RPUS',
        ]);

        $pilot->pilotProfile()->create([
            'license_number' => 'LIC-7788',
            'ratings' => 'IR, ME',
            'license_expiry_date' => '2026-11-15',
            'medical_expiry_date' => '2026-10-20',
            'operator' => 'RPUS',
            'remarks' => 'Ready for review.',
        ]);

        $this->actingAs($pilot)
            ->get(MyProfilePage::getUrl(panel: 'pilot'))
            ->assertOk()
            ->assertSeeText('Bill Q Pilot')
            ->assertSeeText('bill@example.test')
            ->assertSeeText('LIC-7788')
            ->assertSeeText('IR, ME')
            ->assertSeeText('November 15, 2026')
            ->assertSeeText('October 20, 2026')
            ->assertSeeText('RPUS')
            ->assertSeeText('Ready for review.');
    }

    public function test_artisan_cannot_view_or_edit_pilot_profile_pages(): void
    {
        $artisan = $this->artisanUser();

        $this->actingAs($artisan)
            ->get(MyProfilePage::getUrl(panel: 'pilot'))
            ->assertForbidden();

        $this->actingAs($artisan)
            ->get(EditMyProfilePage::getUrl(panel: 'pilot'))
            ->assertForbidden();
    }

    public function test_artisan_profile_access_attempt_does_not_create_pilot_profile(): void
    {
        $artisan = $this->artisanUser();

        $this->assertDatabaseCount('pilot_profiles', 0);

        $this->actingAs($artisan)
            ->get(EditMyProfilePage::getUrl(panel: 'pilot'))
            ->assertForbidden();

        $this->assertDatabaseCount('pilot_profiles', 0);
        $this->assertFalse($artisan->pilotProfile()->exists());
    }

    public function test_view_profile_page_links_to_the_edit_page(): void
    {
        $pilot = $this->pilot();

        $this->actingAs($pilot)
            ->get(MyProfilePage::getUrl(panel: 'pilot'))
            ->assertOk()
            ->assertSee(EditMyProfilePage::getUrl(panel: 'pilot'), escape: false)
            ->assertSeeText('Update Profile');
    }

    public function test_updating_the_profile_persists_changes_and_redirects_to_view_profile(): void
    {
        $pilot = $this->pilot([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'old@example.test',
            'suffix' => null,
        ]);

        $pilot->pilotProfile()->create([
            'license_number' => 'OLD-100',
        ]);

        Livewire::actingAs($pilot)
            ->test(EditMyProfilePage::class)
            ->fillForm([
                'first_name' => 'New',
                'middle_name' => 'M',
                'last_name' => 'Pilot',
                'email' => 'new@example.test',
                'license_number' => 'NEW-900',
                'ratings' => 'ATPL',
                'license_expiry_date' => '2027-01-04',
                'medical_expiry_date' => '2027-02-05',
                'operator' => 'RPLL',
                'remarks' => 'Updated profile',
            ])
            ->call('save')
            ->assertRedirect(MyProfilePage::getUrl(panel: 'pilot'));

        $pilot->refresh();
        $pilot->load('pilotProfile');

        $this->assertSame('New', $pilot->first_name);
        $this->assertSame('M', $pilot->middle_name);
        $this->assertSame('Pilot', $pilot->last_name);
        $this->assertSame('new@example.test', $pilot->email);
        $this->assertSame('New M Pilot', $pilot->name);
        $this->assertSame('NEW-900', $pilot->pilotProfile?->license_number);
        $this->assertSame('ATPL', $pilot->pilotProfile?->ratings);
        $this->assertSame('2027-01-04', $pilot->pilotProfile?->license_expiry_date?->toDateString());
        $this->assertSame('2027-02-05', $pilot->pilotProfile?->medical_expiry_date?->toDateString());
        $this->assertSame('RPLL', $pilot->pilotProfile?->operator);
        $this->assertSame('Updated profile', $pilot->pilotProfile?->remarks);
    }

    public function test_edit_profile_page_renders_a_livewire_save_form(): void
    {
        $pilot = $this->pilot();

        $this->actingAs($pilot)
            ->get(EditMyProfilePage::getUrl(panel: 'pilot'))
            ->assertOk()
            ->assertSee('id="profile-form"', escape: false)
            ->assertSee('wire:submit="save"', escape: false)
            ->assertSeeText('Save Profile');
    }

    public function test_profile_and_placeholder_pages_do_not_register_in_main_navigation(): void
    {
        $this->assertFalse(MyProfilePage::shouldRegisterNavigation());
        $this->assertFalse(EditMyProfilePage::shouldRegisterNavigation());
        $this->assertFalse(PreferencesPage::shouldRegisterNavigation());
        $this->assertFalse(SecurityPage::shouldRegisterNavigation());
        $this->assertFalse(HelpPage::shouldRegisterNavigation());
    }

    public function test_pilot_panel_user_menu_contains_profile_preferences_security_and_help_items(): void
    {
        $pilot = $this->pilot();

        $this->actingAs($pilot);
        Filament::setCurrentPanel('pilot');

        $items = Filament::getUserMenuItems();
        $labels = array_map(fn ($item): string => (string) $item->getLabel(), $items);
        $urls = array_map(fn ($item): ?string => $item->getUrl(), $items);

        $this->assertContains('View Profile', $labels);
        $this->assertContains('Preferences', $labels);
        $this->assertContains('Security', $labels);
        $this->assertContains('Help', $labels);
        $this->assertContains('Sign out', $labels);
        $this->assertContains(MyProfilePage::getUrl(panel: 'pilot'), $urls);
        $this->assertContains(PreferencesPage::getUrl(panel: 'pilot'), $urls);
        $this->assertContains(SecurityPage::getUrl(panel: 'pilot'), $urls);
        $this->assertContains(HelpPage::getUrl(panel: 'pilot'), $urls);
    }

    public function test_pilot_panel_user_menu_hides_profile_item_for_artisan(): void
    {
        $artisan = $this->artisanUser();

        $this->actingAs($artisan);
        Filament::setCurrentPanel('pilot');

        $items = Filament::getUserMenuItems();
        $labels = array_map(fn ($item): string => (string) $item->getLabel(), $items);
        $urls = array_map(fn ($item): ?string => $item->getUrl(), $items);

        $this->assertNotContains('View Profile', $labels);
        $this->assertNotContains(MyProfilePage::getUrl(panel: 'pilot'), $urls);
        $this->assertContains('Preferences', $labels);
        $this->assertContains('Security', $labels);
        $this->assertContains('Help', $labels);
    }

    public function test_active_artisan_can_access_generic_pilot_support_pages(): void
    {
        $artisan = $this->artisanUser();

        foreach ([
            PreferencesPage::getUrl(panel: 'pilot'),
            SecurityPage::getUrl(panel: 'pilot'),
            HelpPage::getUrl(panel: 'pilot'),
        ] as $url) {
            $this->actingAs($artisan)
                ->get($url)
                ->assertOk();
        }
    }

    public function test_normal_role_restrictions_for_pilot_panel_remain_unchanged(): void
    {
        foreach ([
            UserRole::Admin,
            UserRole::Atmo,
            UserRole::Dispatch,
            UserRole::Avsec,
            UserRole::AtsHq,
        ] as $role) {
            $user = $this->user($role, [
                'station' => $role === UserRole::Atmo ? 'RPUS' : null,
            ]);

            $this->actingAs($user)
                ->get('/pilot')
                ->assertForbidden();
        }
    }

    public function test_artisan_can_still_access_other_authorized_panels(): void
    {
        $artisan = $this->artisanUser();

        foreach (['/admin', '/atmo', '/ats', '/dispatch', '/avsec', '/pilot', '/artisan'] as $path) {
            $this->actingAs($artisan)
                ->get($path)
                ->assertOk();
        }
    }

    public function test_users_cannot_view_or_modify_another_users_profile(): void
    {
        $owner = $this->pilot([
            'first_name' => 'Owner',
            'middle_name' => null,
            'last_name' => 'Pilot',
            'email' => 'owner@example.test',
        ]);
        $owner->pilotProfile()->create([
            'license_number' => 'OWNER-1',
            'remarks' => 'Owner only',
        ]);

        $intruder = $this->pilot([
            'first_name' => 'Intruder',
            'middle_name' => null,
            'last_name' => 'Pilot',
            'email' => 'intruder@example.test',
        ]);
        $intruder->pilotProfile()->create([
            'license_number' => 'INTRUDER-1',
            'remarks' => 'Intruder only',
        ]);

        $this->actingAs($intruder)
            ->get(MyProfilePage::getUrl(panel: 'pilot'))
            ->assertOk()
            ->assertSeeText('Intruder Pilot')
            ->assertSeeText('intruder@example.test')
            ->assertSeeText('INTRUDER-1')
            ->assertDontSeeText('Owner Pilot')
            ->assertDontSeeText('owner@example.test')
            ->assertDontSeeText('OWNER-1');

        Livewire::actingAs($intruder)
            ->test(EditMyProfilePage::class)
            ->fillForm([
                'first_name' => 'Still',
                'middle_name' => null,
                'last_name' => 'Intruder',
                'email' => 'still-intruder@example.test',
                'license_number' => 'INTRUDER-2',
                'ratings' => 'IR',
                'license_expiry_date' => null,
                'medical_expiry_date' => null,
                'operator' => null,
                'remarks' => 'Changed intruder only',
            ])
            ->call('save')
            ->assertRedirect(MyProfilePage::getUrl(panel: 'pilot'));

        $owner->refresh();
        $owner->load('pilotProfile');
        $intruder->refresh();
        $intruder->load('pilotProfile');

        $this->assertSame('Owner', $owner->first_name);
        $this->assertSame('owner@example.test', $owner->email);
        $this->assertSame('OWNER-1', $owner->pilotProfile?->license_number);

        $this->assertSame('Still', $intruder->first_name);
        $this->assertSame('still-intruder@example.test', $intruder->email);
        $this->assertSame('INTRUDER-2', $intruder->pilotProfile?->license_number);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pilot(array $attributes = []): User
    {
        return $this->user(UserRole::Pilot, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function artisanUser(array $attributes = []): User
    {
        return $this->user(UserRole::Artisan, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function user(UserRole $role, array $attributes = []): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            ...$attributes,
        ]);
    }
}

