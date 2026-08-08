<?php

namespace App\Filament\Panels\Admin\Resources\ProfileUpdateRequests;

use App\Domain\ProfileUpdateRequests\Enums\ProfileUpdateRequestStatus;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Admin\Resources\ProfileUpdateRequests\Pages\EditProfileUpdateRequest;
use App\Filament\Panels\Admin\Resources\ProfileUpdateRequests\Pages\ListProfileUpdateRequests;
use App\Models\ProfileUpdateRequest;
use App\Services\ProfileUpdates\ProfileFieldRegistry;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ProfileUpdateRequestResource extends Resource
{
    protected static ?string $model = ProfileUpdateRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Profile Update Requests';

    protected static string|UnitEnum|null $navigationGroup = 'Accounts';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request')
                ->columnSpanFull()
                ->schema([
                    Html::make(fn (?ProfileUpdateRequest $record): string => self::requestHtml($record)),
                ]),
            Section::make('Requested Changes')
                ->columnSpanFull()
                ->schema([
                    Html::make(fn (?ProfileUpdateRequest $record): string => self::changesHtml($record)),
                ]),
            Section::make('Supporting Documents')
                ->columnSpanFull()
                ->schema([
                    Html::make(fn (?ProfileUpdateRequest $record): string => self::documentsHtml($record)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Requester')->searchable()->sortable(),
                TextColumn::make('submitted_at')->dateTime()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ProfileUpdateRequestStatus|string|null $state): string => $state instanceof ProfileUpdateRequestStatus ? $state->label() : str((string) $state)->headline()),
                TextColumn::make('reviewedBy.name')->label('Reviewed By')->toggleable(),
                TextColumn::make('reviewed_at')->dateTime()->sortable()->toggleable(),
            ])
            ->recordActions([
                EditAction::make()->label('Review'),
            ])
            ->defaultSort('submitted_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return self::canReviewRequests();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return self::canReviewRequests();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'reviewedBy', 'documents']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProfileUpdateRequests::route('/'),
            'edit' => EditProfileUpdateRequest::route('/{record}/edit'),
        ];
    }

    public static function changesHtml(?ProfileUpdateRequest $record): string
    {
        if ($record === null || blank($record->requested_changes)) {
            return '<p>No changes requested.</p>';
        }

        $rows = collect($record->requested_changes)->map(function (array $change, string $field): string {
            $old = ProfileFieldRegistry::labelForValue($field, $change['old'] ?? null);
            $new = ProfileFieldRegistry::labelForValue($field, $change['new'] ?? null);

            return sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                e($change['label'] ?? $field),
                e($old ?? ''),
                e($new ?? ''),
            );
        })->implode('');

        return '<table class="fi-ta-table w-full text-sm"><thead><tr><th>Field</th><th>Old Value</th><th>Requested New Value</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    private static function requestHtml(?ProfileUpdateRequest $record): string
    {
        if ($record === null) {
            return '';
        }

        return sprintf(
            '<dl class="grid gap-4 md:grid-cols-2"><div><dt>Requester</dt><dd>%s</dd></div><div><dt>Status</dt><dd>%s</dd></div><div><dt>Submitted</dt><dd>%s</dd></div><div><dt>Reviewed</dt><dd>%s</dd></div><div class="md:col-span-2"><dt>Reason</dt><dd>%s</dd></div><div class="md:col-span-2"><dt>Reviewer Remarks</dt><dd>%s</dd></div></dl>',
            e($record->user?->name ?? ''),
            e($record->status?->label() ?? ''),
            e($record->submitted_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? ''),
            e($record->reviewed_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? ''),
            e($record->reason ?? ''),
            e($record->reviewer_remarks ?? $record->rejection_reason ?? ''),
        );
    }

    private static function documentsHtml(?ProfileUpdateRequest $record): string
    {
        if ($record === null || $record->documents->isEmpty()) {
            return '<p>No supporting documents attached.</p>';
        }

        $rows = $record->documents->map(fn ($document): string => sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td><td><a href="%s">Download</a></td></tr>',
            e($document->original_filename),
            e($document->mime_type ?? ''),
            e((string) ($document->file_size ?? '')),
            e(route('profile-update-request-documents.download', $document)),
        ))->implode('');

        return '<table class="fi-ta-table w-full text-sm"><thead><tr><th>Filename</th><th>MIME Type</th><th>Size</th><th>Access</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    private static function canReviewRequests(): bool
    {
        $user = Auth::user();

        return $user !== null
            && $user->is_active
            && in_array($user->role, [UserRole::Admin, UserRole::Artisan], true);
    }
}
