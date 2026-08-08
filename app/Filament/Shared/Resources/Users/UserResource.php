<?php

namespace App\Filament\Shared\Resources\Users;

use App\Domain\Users\Enums\UserRole;
use App\Filament\Shared\Resources\Users\Pages\CreateUser;
use App\Filament\Shared\Resources\Users\Pages\EditUser;
use App\Filament\Shared\Resources\Users\Pages\ListUsers;
use App\Models\Operator;
use App\Models\User;
use App\Models\UserKycDocument;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Users';

    protected static string|UnitEnum|null $navigationGroup = 'Accounts';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Account')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('first_name')
                        ->label('First Name')
                        ->maxLength(255),
                    TextInput::make('middle_name')
                        ->label('Middle Name')
                        ->maxLength(255),
                    TextInput::make('last_name')
                        ->label('Last Name')
                        ->maxLength(255),
                    TextInput::make('suffix')
                        ->label('Suffix')
                        ->maxLength(255),
                    TextInput::make('display_name')
                        ->label('Display Name')
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(User::class, 'email', ignoreRecord: true),
                    TextInput::make('username')
                        ->label('Username')
                        ->maxLength(255)
                        ->unique(User::class, 'username', ignoreRecord: true),
                    TextInput::make('employee_id')
                        ->label('Employee ID')
                        ->maxLength(255)
                        ->unique(User::class, 'employee_id', ignoreRecord: true),
                    TextInput::make('wiresign')
                        ->label('Wiresign')
                        ->maxLength(255)
                        ->unique(User::class, 'wiresign', ignoreRecord: true),
                    TextInput::make('station')
                        ->label('Station')
                        ->maxLength(255),
                    Select::make('role')
                        ->label('Role')
                        ->options(self::roleOptions())
                        ->required()
                        ->live(),
                    Select::make('operator_id')
                        ->label('Operator')
                        ->options(fn (): array => Operator::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->nullable(),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                    TextInput::make('password')
                        ->label(fn (?User $record): string => $record === null ? 'Password' : 'New Password')
                        ->password()
                        ->revealable()
                        ->required(fn (?User $record): bool => $record === null)
                        ->rules([Password::defaults()])
                        ->dehydrated(fn (?string $state): bool => filled($state)),
                ]),
            Section::make('Pilot Profile')
                ->columns(2)
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => $get('role') === UserRole::Pilot->value)
                ->schema([
                    TextInput::make('pilot_license_number')
                        ->label('License Number')
                        ->maxLength(255),
                    TextInput::make('pilot_ratings')
                        ->label('Ratings')
                        ->maxLength(255),
                    DatePicker::make('pilot_license_expiry_date')
                        ->label('License Expiry Date')
                        ->native(false)
                        ->displayFormat('F j, Y'),
                    DatePicker::make('pilot_medical_expiry_date')
                        ->label('Medical Expiry Date')
                        ->native(false)
                        ->displayFormat('F j, Y'),
                    Textarea::make('pilot_remarks')
                        ->label('Remarks')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Dispatch Profile')
                ->columns(2)
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => $get('role') === UserRole::Dispatch->value)
                ->schema([
                    TextInput::make('dispatch_dispatcher_license_number')
                        ->label('Dispatcher License Number')
                        ->maxLength(255),
                    TextInput::make('dispatch_dispatcher_certificate')
                        ->label('Dispatcher Certificate')
                        ->maxLength(255),
                    TextInput::make('dispatch_department')
                        ->label('Department')
                        ->maxLength(255),
                    TextInput::make('dispatch_position')
                        ->label('Position')
                        ->maxLength(255),
                    TextInput::make('dispatch_office_phone')
                        ->label('Office Phone')
                        ->maxLength(255),
                    TextInput::make('dispatch_mobile_number')
                        ->label('Mobile Number')
                        ->maxLength(255),
                    TextInput::make('dispatch_shift')
                        ->label('Shift')
                        ->maxLength(255),
                    Textarea::make('dispatch_remarks')
                        ->label('Remarks')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('ATC Profile')
                ->columns(2)
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => in_array($get('role'), [UserRole::Atmo->value, UserRole::AtsHq->value], true))
                ->schema([
                    TextInput::make('atc_wiresign')
                        ->label('Profile Wiresign')
                        ->maxLength(255),
                    TextInput::make('atc_facility')
                        ->label('Facility')
                        ->maxLength(255),
                    TextInput::make('atc_position')
                        ->label('Position')
                        ->maxLength(255),
                    TextInput::make('atc_endorsements')
                        ->label('Endorsements')
                        ->maxLength(255),
                    Textarea::make('atc_remarks')
                        ->label('Remarks')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('AVSEC Profile')
                ->columns(2)
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => $get('role') === UserRole::Avsec->value)
                ->schema([
                    TextInput::make('avsec_security_certification')
                        ->label('Security Certification')
                        ->maxLength(255),
                    DatePicker::make('avsec_certification_expiry')
                        ->label('Certification Expiry')
                        ->native(false)
                        ->displayFormat('F j, Y'),
                    TextInput::make('avsec_security_clearance_level')
                        ->label('Security Clearance Level')
                        ->maxLength(255),
                    TextInput::make('avsec_position')
                        ->label('Position')
                        ->maxLength(255),
                    Textarea::make('avsec_remarks')
                        ->label('Remarks')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Identity / KYC Verification')
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    Repeater::make('kyc_documents')
                        ->label('KYC Documents')
                        ->schema([
                            Select::make('document_type')
                                ->label('Type')
                                ->options(UserKycDocument::DOCUMENT_TYPES)
                                ->required(),
                            TextInput::make('document_identifier')
                                ->label('Reference')
                                ->maxLength(255),
                            FileUpload::make('file_path')
                                ->label('Attachment')
                                ->disk('local')
                                ->directory('kyc-documents')
                                ->visibility('private')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize(5120),
                            Textarea::make('remarks')
                                ->label('Remarks')
                                ->rows(2),
                        ])
                        ->columns(2)
                        ->default([])
                        ->addActionLabel('Add Document')
                        ->reorderable(false),
                ]),
            Section::make('KYC / Documents')
                ->columnSpanFull()
                ->visible(fn (?User $record): bool => $record !== null)
                ->schema([
                    Html::make(fn (?User $record): string => self::kycDocumentsHtml($record)),
                ]),
            Section::make('Account History')
                ->columnSpanFull()
                ->visible(fn (?User $record): bool => $record !== null)
                ->schema([
                    Html::make(fn (?User $record): string => self::auditHistoryHtml($record)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name')->sortable()->searchable(),
            TextColumn::make('email')->sortable()->searchable(),
            TextColumn::make('role')->badge()->formatStateUsing(fn (UserRole|string|null $state): string => UserRole::normalize($state)?->label() ?? ''),
            TextColumn::make('operator.name')->label('Operator')->sortable()->searchable(),
            TextColumn::make('station')->sortable(),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ]);
    }

    public static function canViewAny(): bool
    {
        return self::canManageUsers();
    }

    public static function canCreate(): bool
    {
        return self::canManageUsers();
    }

    public static function canEdit($record): bool
    {
        return self::canManageUsers();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['operator']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function roleOptions(): array
    {
        return collect(UserRole::cases())
            ->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->label()])
            ->all();
    }

    private static function canManageUsers(): bool
    {
        $user = Auth::user();

        return $user !== null
            && $user->is_active
            && in_array($user->role, [UserRole::Admin, UserRole::Artisan], true);
    }

    private static function kycDocumentsHtml(?User $record): string
    {
        if ($record === null) {
            return '';
        }

        $documents = $record->kycDocuments()
            ->with('verifiedBy')
            ->latest('verified_at')
            ->latest('id')
            ->get();

        if ($documents->isEmpty()) {
            return '<p>No KYC documents recorded.</p>';
        }

        $rows = $documents->map(function (UserKycDocument $document): string {
            $attachment = filled($document->file_path) ? 'Stored privately' : 'None';

            return sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                e($document->documentTypeLabel()),
                e($document->maskedIdentifier() ?? ''),
                e($document->verifiedBy?->name ?? 'System'),
                e($document->verified_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? ''),
                e($document->remarks ?? ''),
                e($attachment),
            );
        })->implode('');

        return '<table class="fi-ta-table w-full text-sm"><thead><tr><th>Document Type</th><th>Reference</th><th>Verified By</th><th>Verified At</th><th>Remarks</th><th>Attachment</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    private static function auditHistoryHtml(?User $record): string
    {
        if ($record === null) {
            return '';
        }

        $logs = $record->auditLogs()
            ->with('performedBy')
            ->latest('created_at')
            ->latest('id')
            ->limit(50)
            ->get();

        if ($logs->isEmpty()) {
            return '<p>No account history recorded.</p>';
        }

        $rows = $logs->map(function ($log): string {
            return sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                e($log->created_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? ''),
                e(str((string) $log->action)->replace('_', ' ')->headline()->toString()),
                e($log->performedBy?->name ?? 'System'),
                e(class_basename((string) ($log->auditable_type ?: User::class))),
                e($log->description ?: self::auditChangesSummary($log->changes ?? [])),
            );
        })->implode('');

        return '<table class="fi-ta-table w-full text-sm"><thead><tr><th>Date / Time</th><th>Action</th><th>Performed By</th><th>Record</th><th>Changes / Details</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private static function auditChangesSummary(array $changes): string
    {
        if ($changes === []) {
            return '';
        }

        return collect($changes)
            ->map(function (mixed $change, string $field): string {
                if (! is_array($change)) {
                    return str($field)->headline()->toString();
                }

                return sprintf(
                    '%s: %s -> %s',
                    str($field)->replace('_', ' ')->headline()->toString(),
                    $change['old_label'] ?? $change['old'] ?? '',
                    $change['new_label'] ?? $change['new'] ?? '',
                );
            })
            ->implode('; ');
    }
}
