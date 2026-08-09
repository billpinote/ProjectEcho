<?php

namespace App\Filament\Panels\Pilot\Pages;

use App\Filament\Panels\Pilot\Pages\Concerns\ResolvesPilotPanelProfileUser;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

class PreferencesPage extends Page
{
    use ResolvesPilotPanelProfileUser;

    protected static ?string $title = 'Preferences';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'preferences';

    protected string $view = 'filament.pages.preferences';

    public static string|Alignment $formActionsAlignment = Alignment::End;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'display_name' => $this->getProfileUser()->display_name,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->model($this->getProfileUser())
            ->operation('edit')
            ->statePath('data')
            ->columns(1);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Personalization')
                ->description('Choose how Echo addresses you in informal areas.')
                ->columns(1)
                ->schema([
                    TextInput::make('display_name')
                        ->label('Display Name')
                        ->maxLength(80)
                        ->helperText('Used for greetings and other informal areas of Echo. This does not change your verified name.'),
                    Html::make(fn (): string => sprintf(
                        '<div class="text-sm"><div class="font-medium text-gray-500">Verified name</div><div class="mt-1 font-semibold text-gray-950">%s</div></div>',
                        e($this->verifiedName()),
                    )),
                ]),
        ]);
    }

    public function formContainer(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([
                EmbeddedSchema::make('form'),
            ])
                ->id('preferences-form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('cancel')
                            ->label('Cancel')
                            ->color('gray')
                            ->url(MyProfilePage::getUrl(panel: 'pilot')),
                        Action::make('save')
                            ->label('Save Preferences')
                            ->submit('preferences-form'),
                    ])->alignEnd(),
                ]),
        ]);
    }

    public function save(): void
    {
        /** @var array<string, mixed> $data */
        $data = $this->form->getState();
        $displayName = $this->normalizeNullableString($data['display_name'] ?? null);

        $user = $this->getProfileUser();
        $user->forceFill([
            'display_name' => $displayName,
        ])->save();

        Notification::make()
            ->title('Preferences saved.')
            ->success()
            ->send();

        $this->redirect(MyProfilePage::getUrl(panel: 'pilot'), navigate: true);
    }

    private function verifiedName(): string
    {
        $user = $this->getProfileUser();

        return $user->fullName() ?: $user->name;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
