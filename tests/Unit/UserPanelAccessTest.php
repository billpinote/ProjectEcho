<?php

namespace Tests\Unit;

use App\Domain\Users\Enums\UserRole;
use App\Models\User;
use Filament\Panel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserPanelAccessTest extends TestCase
{
    #[DataProvider('panelRoleProvider')]
    public function test_users_can_only_access_their_role_panel(string $panelId, UserRole $role): void
    {
        $user = new User([
            'is_active' => true,
            'role' => $role,
            'station' => $role === UserRole::Atmo ? 'RPUS' : null,
        ]);

        $this->assertTrue($user->canAccessPanel(Panel::make()->id($panelId)));

        foreach (['artisan', 'admin', 'pilot', 'atmo', 'dispatch', 'avsec', 'ats'] as $otherPanelId) {
            if ($otherPanelId === $panelId || $role === UserRole::Artisan) {
                continue;
            }

            $this->assertFalse($user->canAccessPanel(Panel::make()->id($otherPanelId)));
        }
    }

    public function test_inactive_users_cannot_access_any_panel(): void
    {
        $user = new User([
            'is_active' => false,
            'role' => UserRole::Admin,
        ]);

        foreach (['artisan', 'admin', 'pilot', 'atmo', 'dispatch', 'avsec', 'ats'] as $panelId) {
            $this->assertFalse($user->canAccessPanel(Panel::make()->id($panelId)));
        }
    }

    public function test_atmo_users_keep_the_existing_rpus_station_requirement(): void
    {
        $user = new User([
            'is_active' => true,
            'role' => UserRole::Atmo,
            'station' => 'RPLL',
        ]);

        $this->assertFalse($user->canAccessPanel(Panel::make()->id('atmo')));
    }

    public function test_artisan_users_can_access_every_panel(): void
    {
        $user = new User([
            'is_active' => true,
            'role' => UserRole::Artisan,
        ]);

        foreach (['artisan', 'admin', 'pilot', 'atmo', 'dispatch', 'avsec', 'ats'] as $panelId) {
            $this->assertTrue($user->canAccessPanel(Panel::make()->id($panelId)));
        }
    }

    /**
     * @return array<string, array{string, UserRole}>
     */
    public static function panelRoleProvider(): array
    {
        return [
            'artisan' => ['artisan', UserRole::Artisan],
            'admin' => ['admin', UserRole::Admin],
            'pilot' => ['pilot', UserRole::Pilot],
            'atmo' => ['atmo', UserRole::Atmo],
            'dispatch' => ['dispatch', UserRole::Dispatch],
            'avsec' => ['avsec', UserRole::Avsec],
            'ats' => ['ats', UserRole::AtsHq],
        ];
    }
}
