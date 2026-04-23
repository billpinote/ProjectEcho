<?php

namespace App\Filament\Resources\Flights\Schemas;

use App\Rules\IcaoAerodrome;
use App\Rules\IcaoAircraftIdentification;
use App\Rules\IcaoCruisingSpeed;
use App\Rules\IcaoFlightLevel;
use App\Rules\IcaoFlightRules;
use App\Rules\IcaoTypeOfFlight;
use App\Rules\IcaoWakeTurbulenceCategory;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class FlightForm
{
    /**
     * Fields rendered as plain 4-digit time inputs to match the public form.
     *
     * @var array<int, string>
     */
    public const TIME_FIELDS = [
        'proposed_time',
        'total_eet',
        'endurance',
        'time_start_up',
        'time_shutdown',
        'time_block_off',
        'time_block_on',
        'time_airborne',
        'time_touchdown',
        'received_time',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Html::make(self::styles()),

                Group::make()
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        Group::make()
                            ->columns(1)
                            ->extraAttributes(['class' => 'caap-flight-plan-shell'])
                            ->schema([
                                Html::make(self::header()),

                                Grid::make(8)
                                    ->extraAttributes(['class' => 'caap-flight-plan-grid'])
                                    ->schema([
                                        self::date('date_of_filing', 'Date of Filing', 1)
                                            ->default(now('UTC')->toDateString())
                                            ->readOnly(),
                                        self::date('date_of_flight', 'Date of Flight', 1)
                                            ->default(now('UTC')->toDateString())
                                            ->minDate(now('UTC')->toDateString())
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn (Get $get, Set $set, mixed $state): mixed => self::syncDofTag($get, $set, $state))
                                            ->partiallyRenderComponentsAfterStateUpdated(['certification-lines']),
                                        self::text('originator', 'Originator', 1)
                                            ->readOnly(),
                                        self::spacer(5),

                                        self::separator(),
                                        Html::make('<h2 class="caap-fpl-prefix">( FPL -</h2>')
                                            ->columnSpan(1),
                                        self::spacer(1),
                                        self::text('aircraft_identification', 'Aircraft Identification', 2)
                                            ->rule(new IcaoAircraftIdentification),
                                        self::spacer(1),
                                        self::select('flight_rules', 'Flight Rules', [
                                            'I' => 'I',
                                            'V' => 'V',
                                            'Y' => 'Y',
                                            'Z' => 'Z',
                                        ], 1)
                                            ->required()
                                            ->rule(new IcaoFlightRules),
                                        self::spacer(1),
                                        self::select('type_of_flight', 'Type of Flight', [
                                            'S' => 'S',
                                            'N' => 'N',
                                            'G' => 'G',
                                            'M' => 'M',
                                            'X' => 'X',
                                        ], 1)
                                            ->required()
                                            ->rule(new IcaoTypeOfFlight),

                                        self::text('number', 'Number', 1)
                                            ->maxLength(50),
                                        self::text('type_of_aircraft', 'Type of Aircraft', 2)
                                            ->live()
                                            ->afterStateUpdated(fn (Get $get, Set $set): mixed => self::syncRequiredOtherInfoTags($get, $set)),
                                        self::text('wake_turbulence_cat', 'WTC', 1)
                                            ->rule(new IcaoWakeTurbulenceCategory),
                                        self::text('equipment_10a', 'COM/NAV/Approach Equipment', 2),
                                        self::text('equipment_10b', 'Surveillance Equipment', 2),

                                        self::spacer(1),
                                        self::text('departure_aerodrome', 'Departure Aerodrome', 2)
                                            ->rule(new IcaoAerodrome)
                                            ->live()
                                            ->afterStateUpdated(fn (Get $get, Set $set): mixed => self::syncRequiredOtherInfoTags($get, $set)),
                                        self::timeText('proposed_time', 'Proposed Time', 1),
                                        self::spacer(4),

                                        self::text('cruising_speed', 'Cruising Speed', 1)
                                            ->rule(new IcaoCruisingSpeed),
                                        self::text('level', 'Level', 1)
                                            ->rule(new IcaoFlightLevel)
                                            ->extraInputAttributes([
                                                'aria-label' => 'Flight level: Use F for flight level (F100), A for altitude (A045), S for metric (S1130), or VFR',
                                            ], merge: true),
                                        self::text('route', 'Route', 6),

                                        self::spacer(1),
                                        self::text('destination_aerodrome', 'Destination Aerodrome', 2)
                                            ->rule(new IcaoAerodrome)
                                            ->live()
                                            ->afterStateUpdated(fn (Get $get, Set $set): mixed => self::syncRequiredOtherInfoTags($get, $set)),
                                        self::timeText('total_eet', 'Total EET', 1),
                                        self::text('altn_aerodrome_1', 'Alternate Aerodrome 1', 2)
                                            ->rule(new IcaoAerodrome)
                                            ->live()
                                            ->afterStateUpdated(fn (Get $get, Set $set): mixed => self::syncRequiredOtherInfoTags($get, $set)),
                                        self::text('altn_aerodrome_2', 'Alternate Aerodrome 2', 2)
                                            ->rule(new IcaoAerodrome)
                                            ->live()
                                            ->afterStateUpdated(fn (Get $get, Set $set): mixed => self::syncRequiredOtherInfoTags($get, $set)),

                                        self::textarea('other_information', 'Other Information', 8, rows: 4)
                                            ->live(onBlur: true)
                                            ->required(fn (Get $get): bool => self::requiresOtherInformation($get))
                                            ->helperText(fn (Get $get): ?string => self::otherInformationHint($get)),

                                        self::separator(),
                                        self::timeText('endurance', 'Endurance', 1),
                                        self::spacer(1),
                                        self::text('persons_on_board', 'POB', 1)
                                            ->rules(['nullable', 'regex:/^\d{1,3}$/'])
                                            ->extraInputAttributes([
                                                'inputmode' => 'numeric',
                                                'maxlength' => 3,
                                            ], merge: true),
                                        self::spacer(1),
                                        self::checkboxCluster('Emergency Radio', [
                                            self::unavailableCheckbox('emergency_radio_uhf', 'UHF'),
                                            self::unavailableCheckbox('emergency_radio_vhf', 'VHF'),
                                            self::unavailableCheckbox('emergency_radio_elt', 'ELT'),
                                        ], 3),
                                        self::spacer(1),

                                        self::checkboxCluster('Survival Equipment', [
                                            self::unavailableCheckbox('survival_equipment_polar', 'Polar'),
                                            self::unavailableCheckbox('survival_equipment_desert', 'Desert'),
                                            self::unavailableCheckbox('survival_equipment_maritime', 'Maritime'),
                                            self::unavailableCheckbox('survival_equipment_jungle', 'Jungle'),
                                        ], 3),
                                        self::spacer(1),
                                        self::checkboxCluster('Jackets', [
                                            self::unavailableCheckbox('jackets_light', 'Light'),
                                            self::unavailableCheckbox('jackets_fluores', 'Fluorescent'),
                                            self::unavailableCheckbox('jackets_uhf', 'UHF'),
                                            self::unavailableCheckbox('jackets_vhf', 'VHF'),
                                        ], 3),
                                        self::spacer(1),

                                        Checkbox::make('dinghies_enabled')
                                            ->label('DINGHIES')
                                            ->inline()
                                            ->default(false)
                                            ->formatStateUsing(fn (mixed $state): bool => ! (bool) ($state ?? false))
                                            ->dehydrateStateUsing(fn (mixed $state): bool => ! (bool) $state)
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                                if (! $state) {
                                                    return;
                                                }

                                                foreach (['dinghies_number', 'dinghies_capacity', 'dinghies_cover', 'dinghies_color'] as $field) {
                                                    $set($field, null);
                                                }
                                            })
                                            ->extraAttributes(['class' => 'caap-checkbox-field'])
                                            ->extraInputAttributes(['class' => 'caap-inverted-checkbox']),
                                        self::text('dinghies_number', 'Number', 1)
                                            ->disabled(fn (Get $get): bool => (bool) $get('dinghies_enabled'))
                                            ->rules(['nullable', 'integer', 'min:0'])
                                            ->extraInputAttributes(['inputmode' => 'numeric'], merge: true),
                                        self::text('dinghies_capacity', 'Capacity', 1)
                                            ->disabled(fn (Get $get): bool => (bool) $get('dinghies_enabled'))
                                            ->rules(['nullable', 'integer', 'min:0'])
                                            ->extraInputAttributes(['inputmode' => 'numeric'], merge: true),
                                        self::select('dinghies_cover', 'Covered', [
                                            'X' => '',
                                            'Y' => 'YES',
                                            'N' => 'NO',
                                        ], 1)
                                            ->disabled(fn (Get $get): bool => (bool) $get('dinghies_enabled')),
                                        self::text('dinghies_color', 'Color', 2)
                                            ->disabled(fn (Get $get): bool => (bool) $get('dinghies_enabled')),
                                        self::spacer(2),

                                        self::text('aircraft_colour_and_markings', 'Aircraft Colour and Markings', 8),
                                        self::text('remarks', 'Remarks', 8),

                                        self::text('pilot_in_command', 'Pilot In Command', 4)
                                            ->live(onBlur: true)
                                            ->partiallyRenderComponentsAfterStateUpdated(['certification-lines']),
                                        self::text('pilot_license_no', 'Lic. No.', 1)
                                            ->live(onBlur: true)
                                            ->partiallyRenderComponentsAfterStateUpdated(['certification-lines']),
                                        self::text('pilot_ratings', 'Pilot Ratings', 2)
                                            ->live(onBlur: true)
                                            ->partiallyRenderComponentsAfterStateUpdated(['certification-lines']),
                                        self::date('license_expiry_date', 'Expiry Date', 1)
                                            ->live()
                                            ->partiallyRenderComponentsAfterStateUpdated(['certification-lines']),
                                        
                                        Checkbox::make('authorized_representative_enabled')
                                            ->label('Filed by Dispatch / Authorized Representative')
                                            ->inline()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                                if ($state) {
                                                    return;
                                                }

                                                foreach (['authorized_representative_name', 'authorized_representative_role', 'authorized_representative_id_license', 'authorized_representative_expiry_date'] as $field) {
                                                    $set($field, null);
                                                }
                                            })
                                            ->extraAttributes(['class' => 'caap-dispatch-checkbox'])
                                            ->columnSpan(8),
                                        self::text('authorized_representative_name', 'Representative Name', 4)
                                            ->hidden(fn (Get $get): bool => ! (bool) $get('authorized_representative_enabled'))
                                            ->required(fn (Get $get): bool => (bool) $get('authorized_representative_enabled'))
                                            ->live(onBlur: true)
                                            ->partiallyRenderComponentsAfterStateUpdated(['certification-lines']),
                                        self::text('authorized_representative_role', 'Role', 2)
                                            ->hidden(fn (Get $get): bool => ! (bool) $get('authorized_representative_enabled')),
                                        self::text('authorized_representative_id_license', 'ID/License', 1)
                                            ->hidden(fn (Get $get): bool => ! (bool) $get('authorized_representative_enabled'))
                                            ->required(fn (Get $get): bool => (bool) $get('authorized_representative_enabled'))
                                            ->live(onBlur: true)
                                            ->partiallyRenderComponentsAfterStateUpdated(['certification-lines']),
                                        self::date('authorized_representative_expiry_date', 'Expiry Date', 1)
                                            ->hidden(fn (Get $get): bool => ! (bool) $get('authorized_representative_enabled'))
                                            ->required(fn (Get $get): bool => (bool) $get('authorized_representative_enabled'))
                                            ->live()
                                            ->partiallyRenderComponentsAfterStateUpdated(['certification-lines']),

                                        Html::make(fn (Get $get): string => self::certification($get))
                                            ->key('certification-lines')
                                            ->columnSpan(8),
                                    ]),

                                
                            ]),
                    ]),
            ]);
    }

    private static function text(string $name, string $label, int $span = 1): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->maxLength(255)
            ->columnSpan($span)
            ->extraAttributes(['class' => 'caap-field'])
            ->extraInputAttributes(['class' => 'caap-control']);
    }

    private static function timeText(string $name, string $label, int $span = 1): TextInput
    {
        return self::text($name, $label, $span)
            ->maxLength(4)
            ->rules(['nullable', 'regex:/^\d{4}$|^\d{2}:\d{2}(:\d{2})?$/'])
            ->formatStateUsing(fn (mixed $state): ?string => self::formatTimeForForm($state))
            ->dehydrateStateUsing(fn (mixed $state): ?string => self::normalizeTimeForStorage($state))
            ->extraInputAttributes([
                'inputmode' => 'numeric',
                'maxlength' => 4,
                'class' => 'caap-control caap-time-input',
            ]);
    }

    private static function date(string $name, string $label, int $span = 1): DatePicker
    {
        return DatePicker::make($name)
            ->label($label)
            ->native(false)
            ->columnSpan($span)
            ->extraAttributes(['class' => 'caap-field'])
            ->extraInputAttributes(['class' => 'caap-control']);
    }

    /**
     * @param  array<string, string>  $options
     */
    private static function select(string $name, string $label, array $options, int $span = 1): Select
    {
        return Select::make($name)
            ->label($label)
            ->placeholder('---')
            ->options($options)
            ->native()
            ->columnSpan($span)
            ->extraAttributes(['class' => 'caap-field'])
            ->extraInputAttributes(['class' => 'caap-control']);
    }

    private static function textarea(string $name, string $label, int $span, int $rows = 3): Textarea
    {
        return Textarea::make($name)
            ->label($label)
            ->rows($rows)
            ->columnSpan($span)
            ->extraAttributes(['class' => 'caap-field'])
            ->extraInputAttributes(['class' => 'caap-control']);
    }

    private static function unavailableCheckbox(string $name, string $label): Checkbox
    {
        return Checkbox::make($name)
            ->label($label)
            ->inline()
            ->default(true)
            ->formatStateUsing(fn (mixed $state): bool => ! (bool) ($state ?? true))
            ->dehydrateStateUsing(fn (mixed $state): bool => ! (bool) $state)
            ->extraAttributes(['class' => 'caap-checkbox-field'])
            ->extraInputAttributes(['class' => 'caap-inverted-checkbox']);
    }

    /**
     * @param  array<int, Checkbox>  $checkboxes
     */
    private static function checkboxCluster(string $label, array $checkboxes, int $span): Grid
    {
        return Grid::make(count($checkboxes))
            ->columnSpan($span)
            ->extraAttributes(['class' => 'caap-checkbox-cluster'])
            ->schema([
                Html::make('<div class="caap-cluster-label">'.e($label).'</div>')
                    ->columnSpanFull(),
                ...$checkboxes,
            ]);
    }

    private static function spacer(int $span = 1): Html
    {
        return Html::make('<span class="caap-spacer" aria-hidden="true"></span>')
            ->columnSpan($span);
    }

    private static function separator(): Html
    {
        return Html::make('<div class="caap-row-separator"></div>')
            ->columnSpan(8);
    }

    private static function syncDofTag(Get $get, Set $set, mixed $date): null
    {
        if (blank($date)) {
            return null;
        }

        $formattedDate = preg_replace('/\D/', '', (string) $date);

        if (strlen((string) $formattedDate) !== 8) {
            return null;
        }

        $dofTag = 'DOF/'.$formattedDate;
        $currentValue = trim((string) $get('other_information'));
        $updatedValue = preg_match('/DOF\/[^\s]*/i', $currentValue)
            ? preg_replace('/DOF\/[^\s]*/i', $dofTag, $currentValue)
            : trim($currentValue.' '.$dofTag);

        $set('other_information', trim((string) $updatedValue));

        return null;
    }

    private static function syncRequiredOtherInfoTags(Get $get, Set $set): null
    {
        $currentValue = trim((string) $get('other_information'));
        $tags = [];

        if (strtoupper(trim((string) $get('departure_aerodrome'))) === 'ZZZZ') {
            $tags[] = 'DEP/';
        }

        if (strtoupper(trim((string) $get('destination_aerodrome'))) === 'ZZZZ') {
            $tags[] = 'DEST/';
        }

        if (strtoupper(trim((string) $get('type_of_aircraft'))) === 'ZZZZ') {
            $tags[] = 'TYP/';
        }

        if (strtoupper(trim((string) $get('altn_aerodrome_1'))) === 'ZZZZ') {
            $tags[] = 'ALTN/';
        }

        if (strtoupper(trim((string) $get('altn_aerodrome_2'))) === 'ZZZZ') {
            $tags[] = 'ALTN2/';
        }

        foreach ($tags as $tag) {
            if (stripos($currentValue, $tag) !== false) {
                continue;
            }

            $currentValue = trim($currentValue.' '.$tag);
        }

        if ($currentValue !== trim((string) $get('other_information'))) {
            $set('other_information', $currentValue);
        }

        return null;
    }

    private static function requiresOtherInformation(Get $get): bool
    {
        foreach (['departure_aerodrome', 'destination_aerodrome', 'type_of_aircraft', 'altn_aerodrome_1', 'altn_aerodrome_2'] as $field) {
            if (strtoupper(trim((string) $get($field))) === 'ZZZZ') {
                return true;
            }
        }

        return false;
    }

    private static function otherInformationHint(Get $get): ?string
    {
        $messages = [];

        if (strtoupper(trim((string) $get('departure_aerodrome'))) === 'ZZZZ') {
            $messages[] = 'Include DEP/ in Other Information when departure aerodrome is ZZZZ.';
        }

        if (strtoupper(trim((string) $get('destination_aerodrome'))) === 'ZZZZ') {
            $messages[] = 'Include DEST/ in Other Information when destination aerodrome is ZZZZ.';
        }

        if (strtoupper(trim((string) $get('type_of_aircraft'))) === 'ZZZZ') {
            $messages[] = 'Include TYP/ in Other Information when Type of Aircraft is ZZZZ.';
        }

        if (strtoupper(trim((string) $get('altn_aerodrome_1'))) === 'ZZZZ') {
            $messages[] = 'Include ALTN/ in Other Information when alternate aerodrome is ZZZZ.';
        }

        if (strtoupper(trim((string) $get('altn_aerodrome_2'))) === 'ZZZZ') {
            $messages[] = 'Include ALTN2/ in Other Information when 2nd alternate aerodrome is ZZZZ.';
        }

        return $messages === [] ? null : implode(' ', $messages);
    }

    public static function formatTimeForForm(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value);

        if ($digits === null || strlen($digits) < 4) {
            return trim((string) $value);
        }

        return substr($digits, 0, 4);
    }

    public static function normalizeTimeForStorage(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', trim((string) $value));

        if ($digits === null || $digits === '') {
            return null;
        }

        if (strlen($digits) !== 4) {
            return trim((string) $value);
        }

        return substr($digits, 0, 2).':'.substr($digits, 2, 2);
    }

    private static function certification(Get $get): string
    {
        $pilotName = trim((string) $get('pilot_in_command'));
        $pilotLicense = self::joinParts([
            trim((string) $get('pilot_license_no')),
            trim((string) $get('pilot_ratings')),
            trim((string) $get('license_expiry_date')),
        ]);
        $representativeName = trim((string) $get('authorized_representative_name'));
        $representativeLicense = self::joinParts([
            trim((string) $get('authorized_representative_id_license')),
            trim((string) $get('authorized_representative_expiry_date')),
        ]);

        return '
            <div class="caap-certification">
                <h3>CERTIFICATION</h3>
                <p>This is to certify that the above entries are true and correct and that, pilot-in-command of this aircraft, pledge not to fly over prohibited and restricted areas; will not willfully deviate from the filed flight plan, except when necessary in the interest of safety; will operate only in accordance with existing Civil and Military regulations; and will not operate in any manner inimical to the security of the Republic of the Philippines. The herein Pilot-in-Command is qualified to fly the route mentioned in this Flight Plan.</p>
                <div class="caap-cert-signature-grid">
                    <div class="caap-cert-signature-item">
                        <div class="caap-cert-signature-line"><span>'.e($pilotName).'</span></div>
                        <div class="caap-cert-signature-label">Pilot\'s Name and Signature</div>
                    </div>
                    <div class="caap-cert-signature-item">
                        <div class="caap-cert-signature-line"><span>'.e($pilotLicense).'</span></div>
                        <div class="caap-cert-signature-label">License No. / Rating / Expiry Date</div>
                    </div>
                    <div class="caap-cert-signature-or">OR</div>
                    <div class="caap-cert-signature-item">
                        <div class="caap-cert-signature-line"><span>'.e($representativeName).'</span></div>
                        <div class="caap-cert-signature-label">Duly Authorized Representative</div>
                    </div>
                    <div class="caap-cert-signature-item">
                        <div class="caap-cert-signature-line"><span>'.e($representativeLicense).'</span></div>
                        <div class="caap-cert-signature-label">License No. / Expiry Date</div>
                    </div>
                </div>
            </div>
        ';
    }

    /**
     * @param  array<int, string>  $parts
     */
    private static function joinParts(array $parts): string
    {
        return implode(' / ', array_values(array_filter($parts, filled(...))));
    }

    private static function header(): string
    {
        return '
            <div class="caap-flight-plan-header">
                <p class="caap-form-number">CAAP Form ATS 2019-1</p>
                <p>Republic of the Philippines</p>
                <p class="caap-agency">CIVIL AVIATION AUTHORITY OF THE PHILIPPINES</p>
                <p>Old Mia Road, Pasay City, Metro Manila 1300</p>
                <p class="caap-title">FLIGHT PLAN</p>
            </div>
        ';
    }

    private static function styles(): string
    {
        return <<<'HTML'
            <style>
                .fi-main {
                    overflow-x: hidden;
                }

                .caap-flight-plan-shell {
                    display: block;
                    box-sizing: border-box;
                    width: 100%;
                    max-width: none;
                    min-width: 0;
                    margin: 0;
                    padding: clamp(1rem, 2vw, 2rem);
                    border: 1px solid var(--color-echo-border);
                    border-radius: 1rem;
                    background: var(--color-echo-card);
                    box-shadow: 0 12px 32px rgba(10, 63, 50, 0.08);
                }

                .caap-flight-plan-header {
                    color: var(--color-echo-text-secondary);
                    text-align: center;
                }

                .caap-flight-plan-header p {
                    margin: 0;
                    font-size: 1rem;
                    line-height: 1.5;
                    text-transform: none;
                }

                .caap-flight-plan-header .caap-form-number {
                    margin-bottom: 0.5rem;
                    color: var(--color-echo-text-primary);
                    text-align: left;
                    font-size: 0.875rem;
                }

                .caap-flight-plan-header .caap-agency,
                .caap-flight-plan-header .caap-title {
                    font-weight: 700;
                }

                .caap-flight-plan-header .caap-title {
                    margin: 1.25rem 0;
                    font-size: 1.125rem;
                }

                .caap-flight-plan-grid {
                    gap: clamp(0.5rem, 1vw, 1rem);
                }

                .caap-field {
                    min-width: 0;
                }

                .caap-field label,
                .caap-field .fi-fo-field-label,
                .caap-field .fi-fo-field-label-content {
                    display: block;
                    max-width: 100%;
                    min-width: 0;
                    overflow: hidden;
                    color: var(--color-echo-text-primary);
                    font-size: var(--text-echo-label);
                    font-weight: 600;
                    line-height: 1.2;
                    text-overflow: ellipsis;
                    text-transform: uppercase !important;
                    white-space: nowrap;
                }

                .caap-field .fi-fo-field-label-col,
                .caap-field .fi-fo-field-label-ctn {
                    min-width: 0;
                    max-width: 100%;
                }

                .caap-field .fi-fo-field-label {
                    display: flex;
                    min-width: 0;
                    max-width: 100%;
                    flex-wrap: nowrap;
                    align-items: center;
                    gap: 0.15rem;
                }

                .caap-field .fi-fo-field-label-content {
                    display: block;
                    min-width: 0;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    text-transform: uppercase !important;
                    white-space: nowrap;
                }

                .caap-control,
                .caap-flight-plan-shell input,
                .caap-flight-plan-shell select,
                .caap-flight-plan-shell textarea {
                    border-color: var(--color-echo-border) !important;
                    border-radius: 0.85rem !important;
                    color: var(--color-echo-text-primary);
                    font-size: var(--text-echo-body) !important;
                    font-weight: 400 !important;
                    font-family: var(--font-sans) !important;
                    text-transform: uppercase;
                }

                .caap-flight-plan-shell .fi-input-wrp,
                .caap-flight-plan-shell .fi-select-input,
                .caap-flight-plan-shell .fi-textarea {
                    min-width: 0;
                }

                .caap-flight-plan-shell .fi-input,
                .caap-flight-plan-shell .fi-select-input,
                .caap-flight-plan-shell .fi-textarea {
                    padding-inline: clamp(0.45rem, 0.8vw, 1rem);
                }

                .caap-flight-plan-shell input:focus,
                .caap-flight-plan-shell select:focus,
                .caap-flight-plan-shell textarea:focus {
                    border-color: var(--color-echo-primary) !important;
                    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-echo-primary) 20%, white) !important;
                    outline: none !important;
                }

                .caap-flight-plan-shell input[readonly],
                .caap-flight-plan-shell input:disabled,
                .caap-flight-plan-shell select:disabled,
                .caap-flight-plan-shell textarea:disabled {
                    background-color: color-mix(in srgb, var(--color-echo-background) 84%, white);
                    color: var(--color-echo-text-secondary);
                    cursor: not-allowed;
                    opacity: 1;
                }

                .caap-row-separator {
                    min-height: 1.5rem;
                    border-top: 1px solid var(--color-echo-border);
                }

                .caap-spacer {
                    min-height: 1px;
                }

                .caap-fpl-prefix {
                    margin: 0;
                    color: var(--color-echo-primary-dark);
                    font-size: 1.875rem;
                    font-weight: 700;
                    line-height: 1.15;
                    white-space: nowrap;
                }

                .caap-checkbox-cluster {
                    gap: 0.5rem 1rem;
                    align-items: start;
                }

                .caap-cluster-label {
                    margin-bottom: 0.1rem;
                    color: var(--color-echo-text-primary);
                    font-size: var(--text-echo-label);
                    font-weight: 600;
                    line-height: 1.2;
                    text-transform: uppercase;
                }

                .caap-checkbox-field .fi-fo-field-wrp,
                .caap-dispatch-checkbox .fi-fo-field-wrp {
                    gap: 0.4rem;
                }

                .caap-checkbox-field {
                    margin-bottom: 0.2rem;
                }

                .caap-checkbox-field .fi-fo-field-label-content,
                .caap-dispatch-checkbox .fi-fo-field-label-content,
                .caap-checkbox-field .fi-fo-field-label,
                .caap-dispatch-checkbox .fi-fo-field-label {
                    color: var(--color-echo-text-primary);
                    font-size: var(--text-echo-label);
                    font-weight: 600;
                    text-transform: uppercase !important;
                }

                .caap-inverted-checkbox {
                    appearance: none !important;
                    -webkit-appearance: none !important;
                    border: 2px solid var(--color-echo-border) !important;
                    border-radius: 0 !important;
                    width: 1.15rem !important;
                    height: 1.15rem !important;
                    min-width: 1.15rem !important;
                    min-height: 1.15rem !important;
                    background-color: #fff !important;
                    background-repeat: no-repeat !important;
                    background-position: center !important;
                    background-size: 0.7rem 0.7rem !important;
                    cursor: pointer;
                }

                .caap-inverted-checkbox:checked {
                    border-color: var(--color-echo-rejected) !important;
                    background-color: color-mix(in srgb, var(--color-echo-rejected) 16%, white) !important;
                    background-image:
                        linear-gradient(45deg,
                            transparent 42%,
                            var(--color-echo-rejected) 42%,
                            var(--color-echo-rejected) 58%,
                            transparent 58%),
                        linear-gradient(-45deg,
                            transparent 42%,
                            var(--color-echo-rejected) 42%,
                            var(--color-echo-rejected) 58%,
                            transparent 58%) !important;
                    color: var(--color-echo-rejected) !important;
                }

                .caap-authorized-trigger {
                    width: 100%;
                    padding: 0.5rem 0.25rem 0;
                    background: #fff;
                    color: var(--color-echo-primary-dark);
                    font-size: 0.82rem;
                    font-weight: 600;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                }

                .caap-dispatch-checkbox {
                    margin-top: 0.0rem;
                }

                .caap-dispatch-checkbox .fi-fo-checkbox {
                    align-items: center;
                    column-gap: 0.75rem;
                    flex-wrap: nowrap;
                }

                .caap-dispatch-checkbox .fi-fo-checkbox-label {
                    min-width: 0;
                    white-space: nowrap !important;
                }

                .caap-certification {
                    margin-top: 1.5rem;
                    padding-top: 1.5rem;
                    border-top: 1px solid var(--color-echo-border);
                }

                .caap-certification h3 {
                    margin: 0 0 0.5rem;
                    color: var(--color-echo-primary-dark);
                    font-size: 1rem;
                    font-weight: 600;
                    letter-spacing: 0.04em;
                    text-align: center;
                    text-transform: uppercase;
                }

                .caap-certification p {
                    max-width: 64rem;
                    margin: 0 auto 1rem;
                    color: var(--color-echo-text-secondary);
                    font-size: var(--text-echo-body);
                    line-height: 1.5;
                    text-align: center;
                    text-transform: none;
                }

                .caap-cert-signature-grid {
                    display: grid;
                    grid-template-columns: 1fr 1.2fr auto 1.2fr 1fr;
                    gap: clamp(0.5rem, 1vw, 1rem);
                    align-items: end;
                    margin-top: 4.25rem;
                    margin-bottom: 2.25rem;
                }

                .caap-cert-signature-item {
                    display: flex;
                    flex-direction: column;
                    gap: 0.35rem;
                }

                .caap-cert-signature-line {
                    display: flex;
                    min-height: 1.8rem;
                    align-items: flex-end;
                    overflow: hidden;
                    border-bottom: 2px solid var(--color-echo-text-secondary);
                    padding: 0 0.2rem 0.2rem;
                }

                .caap-cert-signature-line span {
                    width: 100%;
                    overflow: hidden;
                    color: var(--color-echo-text-primary);
                    font-size: 0.82rem;
                    font-weight: 700;
                    text-align: center;
                    text-overflow: ellipsis;
                    text-transform: uppercase;
                    white-space: nowrap;
                }

                .caap-cert-signature-label {
                    color: var(--color-echo-text-primary);
                    font-size: 0.7rem;
                    letter-spacing: 0.02em;
                    line-height: 1.2;
                    text-align: center;
                    text-transform: uppercase;
                }

                .caap-cert-signature-or {
                    padding-bottom: 0.3rem;
                    color: var(--color-echo-primary-dark);
                    font-size: 1.1rem;
                    font-weight: 500;
                }
            </style>
        HTML;
    }
}
