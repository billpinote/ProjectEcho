<?php

namespace App\Filament\Panels\Pilot\Pages;

use App\Filament\Panels\Pilot\Pages\Concerns\InteractsWithPilotProfileForm;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;

class EditMyProfilePage extends Page
{
    use InteractsWithPilotProfileForm;

    protected static ?string $title = 'Request Profile Update';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'profile/edit';

    protected string $view = 'filament.pages.edit-my-profile';

    public static string|Alignment $formActionsAlignment = Alignment::End;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->getPilotProfileFormData());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->model($this->getProfileUser())
            ->operation('edit')
            ->statePath('data')
            ->columns(2);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->getPilotProfileFormComponents());
    }

    public function formContainer(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([
                EmbeddedSchema::make('form'),
            ])
                ->id('profile-form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('cancel')
                            ->label('Cancel')
                            ->color('gray')
                            ->url(MyProfilePage::getUrl(panel: 'pilot')),
                        Action::make('save')
                            ->label('Submit Request')
                            ->submit('profile-form'),
                    ])->alignEnd(),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewProfile')
                ->label('Back to Profile')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(MyProfilePage::getUrl(panel: 'pilot')),
        ];
    }

    public function save(): void
    {
        /** @var array<string, mixed> $data */
        $data = $this->form->getState();

        $this->submitPilotProfileUpdateRequest($data);
        $this->sendProfileUpdateRequestedNotification();

        $this->redirect(MyProfilePage::getUrl(panel: 'pilot'), navigate: true);
    }
}
