<!DOCTYPE html>
<html lang="en-CA">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Plan Form</title>
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="{{ asset('css/flightplan.css') }}">
</head>
<body class="bg-gray-100">
    @include('flightplan.partials.navbar', ['activeNav' => 'flight-plan'])

    

    <div class="flightplan-card-scroll">
        <div class="flightplan-zoom-stage" data-flightplan-zoom-stage>
            <div class="flightplan-card bg-white rounded-lg shadow-lg mx-8 p-8" style="min-width: 1400px;">

        @php
            // Initialize prefilled if not provided
            $prefilled = $prefilled ?? [];

            // Helper to get prefilled value with old() priority and proper null handling
            $getValue = function($field, $default = null) use ($prefilled) {
                $oldValue = old($field);
                if ($oldValue !== null) return $oldValue;
                $prefilledValue = $prefilled[$field] ?? null;
                return $prefilledValue !== null ? $prefilledValue : $default;
            };

            // Helper for booleans - returns 0 or 1
            $getBoolValue = function($field) use ($getValue) {
                $val = $getValue($field, 1); // Default to 1 (not equipped/unchecked)
                return (int)$val;
            };

            $getCheckboxBool = function($field, $default = true) use ($getValue) {
                $value = $getValue($field, $default);

                if (is_bool($value)) {
                    return $value;
                }

                if (is_int($value)) {
                    return $value === 1;
                }

                $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                return $normalized ?? $default;
            };

            $isInvertedCheckboxChecked = function($field, $default = true) use ($getCheckboxBool) {
                return ! $getCheckboxBool($field, $default);
            };

            $getSelectValue = function($field, $default = '') use ($getValue) {
                $value = $getValue($field, $default);

                if ($value === null) {
                    return $default;
                }

                return strtoupper(trim((string) $value));
            };
        @endphp

        <p class="text-sm mb-2 text-gray-800">CAAP Form ATS 2019-1</p>
        <p class="text-md text-gray-700 text-center mb-0 max-w-5xl mx-auto" style="text-transform: none;">
            Republic of the Philippines
        </p>
        <p class="text-md font-bold text-gray-700 text-center mb-0 max-w-5xl mx-auto" style="text-transform: none;">
            CIVIL AVIATION AUTHORITY OF THE PHILIPPINES
        </p>
        <p class="text-md text-gray-700 text-center mb-5 max-w-5xl mx-auto" style="text-transform: none;">
            Old Mia Road, Pasay City, Metro Manila 1300
        </p>
        <p class="text-lg font-bold text-gray-700 text-center mt-5 mb-5 max-w-5xl mx-auto" style="text-transform: none;">
             FLIGHT PLAN
        </p>

        @if (session('status'))
            <div id="flightplan-success-alert" class="mb-6 flex items-start justify-between gap-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <div class="flex flex-col gap-2">
                    <span>{{ session('status') }}</span>
                    @if (session('pdf_download_url'))
                        <a href="{{ session('pdf_download_url') }}" class="inline-flex w-fit rounded border border-green-300 bg-white px-3 py-1 text-xs font-semibold text-green-800 hover:bg-green-100">
                            Download PDF
                        </a>
                    @endif
                </div>
                <button type="button" class="flightplan-alert-close text-lg leading-none text-green-700" data-alert-target="flightplan-success-alert" aria-label="Dismiss success message">
                    &times;
                </button>
            </div>
        @endif

        @if (session('discard_warning'))
            <div id="flightplan-discard-alert" class="mb-6 flex items-start justify-between gap-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <span>{{ session('discard_warning') }}</span>
                <button type="button" class="flightplan-alert-close text-lg leading-none text-amber-700" data-alert-target="flightplan-discard-alert" aria-label="Dismiss discard message">
                    &times;
                </button>
            </div>
        @endif

        @if ($errors->any())
            <div id="flightplan-error-alert" class="mb-6 flex items-start justify-between gap-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <div>
                    <p class="font-semibold mb-2">Please fix the following before submitting:</p>
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" class="flightplan-alert-close text-lg leading-none text-red-700" data-alert-target="flightplan-error-alert" aria-label="Dismiss error message">
                    &times;
                </button>
            </div>
        @endif

        <form id="flightplan-form" action="{{ route('flightplan.store') }}" method="POST" data-has-old-input="{{ session()->hasOldInput() ? 'true' : 'false' }}" data-has-prefilled-input="{{ ! empty($prefilled) ? 'true' : 'false' }}" style="display: inline-block; width: 100%;">
            @csrf

            <!-- Top Fields -->
            <div class="form-grid">
                <div class="form-group col-1">
                    <label class="block text-gray-700 font-medium mb-2 text-sm">Date of Filing</label>
                    <input type="date" name="date_of_filing" value="{{ now('UTC')->format('Y-m-d') }}" class="w-[80%] md:w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" disabled>
                </div>
                <div class="form-group col-1">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Date of Flight</label>
                        <input type="date" name="date_of_flight" value="{{ old('date_of_flight', ($prefilled['date_of_flight'] ?? null) ?: now('UTC')->format('Y-m-d')) }}" min="{{ now('UTC')->format('Y-m-d') }}" class="w-[80%] md:w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                <div class="form-group col-1">
                    <label class="block text-gray-700 font-medium mb-2 text-sm">Originator</label>
                    <input type="text" name="originator" class="w-[80%] md:w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" disabled>
                </div>
            </div>

            <!-- Flight Identification -->
            <div class="mb-8">
                <div class="pt-6 border-t border-gray-300" style="display: flex; justify-content: flex-end; gap: 1rem;"></div>
                <div class="form-grid">
                    <h2 class="text-3xl font-bold mb-2 text-gray-800">( FPL - </h2>
                    <div class="form-group col-1">                        
                        <input type="hidden" name="spaceonly">
                    </div>
                    <div class="form-group col-2">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Aircraft Identification</label>
                        <input type="text" name="aircraft_identification" value="{{ old('aircraft_identification', ($prefilled['aircraft_identification'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group col-1">                        
                        <input type="hidden" name="spaceonly">
                    </div>
                    <div class="form-group col-1">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Flight Rules</label>
                        <select name="flight_rules" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="" @selected($getSelectValue('flight_rules', '') === '')>---</option>
                            <option value="I" @selected($getSelectValue('flight_rules', '') === 'I')>I</option>
                            <option value="V" @selected($getSelectValue('flight_rules', '') === 'V')>V</option>
                            <option value="Y" @selected($getSelectValue('flight_rules', '') === 'Y')>Y</option>
                            <option value="Z" @selected($getSelectValue('flight_rules', '') === 'Z')>Z</option>
                        </select>
                    </div>
                    <div class="form-group col-1">                        
                        <input type="hidden" name="spaceonly">
                    </div>
                    <div class="form-group col-1">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Type of Flight</label>
                        <select name="type_of_flight" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="" @selected($getSelectValue('type_of_flight', '') === '')>---</option>
                            <option value="S" @selected($getSelectValue('type_of_flight', '') === 'S')>S</option>
                            <option value="N" @selected($getSelectValue('type_of_flight', '') === 'N')>N</option>
                            <option value="G" @selected($getSelectValue('type_of_flight', '') === 'G')>G</option>
                            <option value="M" @selected($getSelectValue('type_of_flight', '') === 'M')>M</option>
                            <option value="X" @selected($getSelectValue('type_of_flight', '') === 'X')>X</option>
                        </select>
                    </div>      
                </div>
            </div>   
            <div class="mb-8">
                <div class="form-grid">    
                    <div class="form-group col-1">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Number</label>
                        <input type="text" name="number" value="{{ old('number', ($prefilled['number'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group col-2">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Type of Aircraft</label>
                        <input type="text" name="type_of_aircraft" value="{{ old('type_of_aircraft', ($prefilled['type_of_aircraft'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group col-1">
                        <label class="block text-gray-700 font-medium mb-2 text-sm label-truncate" title="Wake Turbulence Category">Wake Turbulence Category</label>
                        <input type="text" name="wake_turbulence_cat" value="{{ old('wake_turbulence_cat', ($prefilled['wake_turbulence_cat'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group col-2">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">COM/NAV/Approach Equipment</label>
                        <input type="text" name="equipment_10a" value="{{ old('equipment_10a', ($prefilled['equipment_10a'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group col-2">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Surveillance Equipment</label>
                        <input type="text" name="equipment_10b" value="{{ old('equipment_10b', ($prefilled['equipment_10b'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>                    
                </div>
            </div>

            <!-- Departure -->
            <div class="mb-8">
                <div class="form-grid">
                    <div class="form-group col-1">                        
                        <input type="hidden" name="spaceonly">
                    </div>
                    <div class="form-group col-2">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Departure Aerodrome</label>
                        <input type="text" name="departure_aerodrome" value="{{ old('departure_aerodrome', ($prefilled['departure_aerodrome'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group col-1">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Proposed Time</label>
                        <input type="text" name="proposed_time" inputmode="numeric" maxlength="4" value="{{ old('proposed_time', ($prefilled['proposed_time'] ?? null) ?: '') }}" class="plain-time-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>                    
                </div>
            </div>

            <!-- Cruising -->
            <div class="mb-8">
                <div class="form-grid">
                    <div class="form-group col-1">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Cruising Speed</label>
                        <input type="text" name="cruising_speed" value="{{ old('cruising_speed', ($prefilled['cruising_speed'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group col-1">
                        <label class="block text-gray-700 font-medium mb-2 text-sm" title="Flight Level or Altitude">Level</label>
                        <input type="text" name="level" value="{{ old('level', ($prefilled['level'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Flight level: Use F for flight level (F100), A for altitude (A045), S for metric (S1130), or VFR">
                    </div>
                    <div class="form-group col-6">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Route</label>
                        <input type="text" name="route" value="{{ old('route', ($prefilled['route'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <!-- Destination -->
            <div class="mb-8">
                <div class="form-grid">
                    <div class="form-group col-1">
                        <input type="hidden" name="spaceonly">
                    </div>
                    <div class="form-group col-2">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Destination Aerodrome</label>
                        <input type="text" name="destination_aerodrome" value="{{ old('destination_aerodrome', ($prefilled['destination_aerodrome'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group col-1">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Total EET</label>
                        <input type="text" name="total_eet" inputmode="numeric" maxlength="4" value="{{ old('total_eet', ($prefilled['total_eet'] ?? null) ?: '') }}" class="plain-time-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group col-2">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Alternate Aerodrome 1</label>
                        <input type="text" name="altn_aerodrome_1" value="{{ old('altn_aerodrome_1', ($prefilled['altn_aerodrome_1'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group col-2">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Alternate Aerodrome 2</label>
                        <input type="text" name="altn_aerodrome_2" value="{{ old('altn_aerodrome_2', ($prefilled['altn_aerodrome_2'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <!-- Other Information -->
            <div class="mb-8">
                <div class="form-grid">
                    <div class="form-group col-8">
                        <label class="block text-gray-700 font-medium mb-2 text-sm" for="other_information">Other Information</label>
                        <textarea id="other_information" name="other_information" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('other_information', ($prefilled['other_information'] ?? null) ?: '') }}</textarea>
                        <p id="other-info-hint" class="text-xs text-gray-500 mt-2 hidden">
                            Other Information may include DEP/ or DEST/ tags when required.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Supplementary Information -->
            <div class="mb-8">
                <div class="pt-6 border-t border-gray-300" style="display: flex; justify-content: flex-end; gap: 1rem;"></div>
                <div class="form-grid">
                    <div class="form-group col-1">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Endurance</label>
                        <input type="text" name="endurance" inputmode="numeric" maxlength="4" value="{{ old('endurance', ($prefilled['endurance'] ?? null) ?: '') }}" class="plain-time-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group col-1">
                        <input type="hidden" name="spaceonly">
                    </div>
                    <div class="form-group col-1">
                        <label class="block text-gray-700 font-medium mb-2 text-sm label-truncate" title="Persons on Board">Persons on Board</label>
                        <input type="text" name="persons_on_board" inputmode="numeric" maxlength="3" value="{{ old('persons_on_board', ($prefilled['persons_on_board'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group col-1">                        
                        <input type="hidden" name="spaceonly">
                    </div>
                    <div class="form-group col-3">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Emergency Radio</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="emergency_radio_uhf" value="1">
                                <input type="checkbox" name="emergency_radio_uhf" value="0" class="inverted-checkbox" @checked($isInvertedCheckboxChecked('emergency_radio_uhf'))>
                                <span class="text-sm text-gray-700">UHF</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="emergency_radio_vhf" value="1">
                                <input type="checkbox" name="emergency_radio_vhf" value="0" class="inverted-checkbox" @checked($isInvertedCheckboxChecked('emergency_radio_vhf'))>
                                <span class="text-sm text-gray-700">VHF</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="emergency_radio_elt" value="1">
                                <input type="checkbox" name="emergency_radio_elt" value="0" class="inverted-checkbox" @checked($isInvertedCheckboxChecked('emergency_radio_elt'))>
                                <span class="text-sm text-gray-700">ELT</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Survival Equipment -->
            <div class="mb-8">
                <div class="form-grid">
                    <div class="form-group col-3">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Survival Equipment</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="survival_equipment_polar" value="1">
                                <input type="checkbox" name="survival_equipment_polar" value="0" class="inverted-checkbox" @checked($isInvertedCheckboxChecked('survival_equipment_polar'))>
                                <span class="text-sm text-gray-700">Polar</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="survival_equipment_desert" value="1">
                                <input type="checkbox" name="survival_equipment_desert" value="0" class="inverted-checkbox" @checked($isInvertedCheckboxChecked('survival_equipment_desert'))>
                                <span class="text-sm text-gray-700">Desert</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="survival_equipment_maritime" value="1">
                                <input type="checkbox" name="survival_equipment_maritime" value="0" class="inverted-checkbox" @checked($isInvertedCheckboxChecked('survival_equipment_maritime'))>
                                <span class="text-sm text-gray-700">Maritime</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="survival_equipment_jungle" value="1">
                                <input type="checkbox" name="survival_equipment_jungle" value="0" class="inverted-checkbox" @checked($isInvertedCheckboxChecked('survival_equipment_jungle'))>
                                <span class="text-sm text-gray-700">Jungle</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group col-1">
                        <input type="hidden" name="spaceonly">
                    </div>
                    <div class="form-group col-3">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Jackets</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="jackets_light" value="1">
                                <input type="checkbox" name="jackets_light" value="0" class="inverted-checkbox" @checked($isInvertedCheckboxChecked('jackets_light'))>
                                <span class="text-sm text-gray-700">Light</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="jackets_fluores" value="1">
                                <input type="checkbox" name="jackets_fluores" value="0" class="inverted-checkbox" @checked($isInvertedCheckboxChecked('jackets_fluores'))>
                                <span class="text-sm text-gray-700">Fluorescent</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="jackets_uhf" value="1">
                                <input type="checkbox" name="jackets_uhf" value="0" class="inverted-checkbox" @checked($isInvertedCheckboxChecked('jackets_uhf'))>
                                <span class="text-sm text-gray-700">UHF</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="jackets_vhf" value="1">
                                <input type="checkbox" name="jackets_vhf" value="0" class="inverted-checkbox" @checked($isInvertedCheckboxChecked('jackets_vhf'))>
                                <span class="text-sm text-gray-700">VHF</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Dinghies -->
            @php
                $dinghiesEnabled = filter_var(old('dinghies_enabled', ($prefilled['dinghies_enabled'] ?? null) ?: false), FILTER_VALIDATE_BOOLEAN);
            @endphp
            <div class="mb-8">
                <div class="form-grid">
                    <div class="form-group col-1">
                        <label class="block text-transparent font-medium mb-2 text-sm">.</label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="dinghies_enabled" value="1">
                            <input type="checkbox" id="dinghies-checkbox" name="dinghies_enabled" value="0" class="inverted-checkbox" @checked(! $dinghiesEnabled)>
                            <span class="block text-gray-700 font-medium mb-0 text-size:0.8rem">Dinghies</span>
                        </label>
                    </div>
                    <div class="form-group col-1">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Number</label>
                        <input type="text" name="dinghies_number" value="{{ old('dinghies_number', ($prefilled['dinghies_number'] ?? null) ?: '') }}" class="dinghies-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" @disabled(! $dinghiesEnabled)>
                    </div>
                    <div class="form-group col-1">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Capacity</label>
                        <input type="text" name="dinghies_capacity" value="{{ old('dinghies_capacity', ($prefilled['dinghies_capacity'] ?? null) ?: '') }}" class="dinghies-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" @disabled(! $dinghiesEnabled)>
                    </div>
                    <div class="form-group col-1">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">COVERED</label>
                        <select name="dinghies_cover" class="dinghies-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" @disabled(! $dinghiesEnabled)>
                            <option value="X" @selected((old('dinghies_cover', ($prefilled['dinghies_cover'] ?? null)) ?? 'X') === 'X')></option>
                            <option value="Y" @selected((old('dinghies_cover', ($prefilled['dinghies_cover'] ?? null)) ?? 'X') === 'Y')>YES</option>
                            <option value="N" @selected((old('dinghies_cover', ($prefilled['dinghies_cover'] ?? null)) ?? 'X') === 'N')>NO</option>
                        </select>
                    </div>
                    <div class="form-group col-2">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Color</label>
                        <input type="text" name="dinghies_color" value="{{ old('dinghies_color', ($prefilled['dinghies_color'] ?? null) ?: '') }}" class="dinghies-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" @disabled(! $dinghiesEnabled)>
                    </div>

                </div>
            </div>

            <!-- Aircraft Information -->
            <div class="mb-8">
                <div class="form-grid">
                    <div class="form-group col-8">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Aircraft Colour and Markings</label>
                        <input type="text" name="aircraft_colour_and_markings" value="{{ old('aircraft_colour_and_markings', ($prefilled['aircraft_colour_and_markings'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>
            <div class="mb-8">
                <div class="form-grid">
                    <div class="form-group col-8">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Remarks</label>
                        <input type="text" name="remarks" value="{{ old('remarks', ($prefilled['remarks'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>
            <div class="mb-8">
                <div class="form-grid">
                    <div class="form-group col-4">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Pilot In Command</label>
                        <input type="text" name="pilot_in_command" value="{{ old('pilot_in_command', ($prefilled['pilot_in_command'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group col-1">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Pilot License No.</label>
                        <input type="text" name="pilot_license_no" value="{{ old('pilot_license_no', ($prefilled['pilot_license_no'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group col-2">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Pilot Ratings</label>
                        <input type="text" name="pilot_ratings" value="{{ old('pilot_ratings', ($prefilled['pilot_ratings'] ?? null) ?: '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group col-1">
                        <label class="block text-gray-700 font-medium mb-2 text-sm">Expiry Date</label>
                        <input type="date" name="license_expiry_date" value="{{ old('license_expiry_date', ($prefilled['license_expiry_date'] ?? null) ?: '') }}" class="w-[80%] md:w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <!-- Authorized Representative -->
            @php
                $authorizedRepresentativeEnabled = filter_var(old('authorized_representative_enabled', ($prefilled['authorized_representative_enabled'] ?? null) ?: false), FILTER_VALIDATE_BOOLEAN);
            @endphp
            <div class="mb-8">
                <div id="authorized-rep-panel" class="rounded-lg p-0 {{ $authorizedRepresentativeEnabled ? '' : 'collapsible-disabled' }}">
                    <label for="authorized-rep-checkbox" class="dispatch-trigger cursor-pointer">
                        <input type="hidden" id="authorized-rep-enabled" name="authorized_representative_enabled" value="1">
                        <input type="checkbox" id="authorized-rep-checkbox" name="authorized_representative_enabled" value="0" class="inverted-checkbox" @checked(! $authorizedRepresentativeEnabled)>
                        <span>FILED BY DISPATCH / AUTHORIZE REPRESENTATIVE</span>
                    </label>

                    <div id="authorized-rep-content" class="{{ $authorizedRepresentativeEnabled ? '' : 'hidden' }} mt-4">
                        <div class="form-grid mb-0">
                            <div class="form-group col-4">
                                <label class="block text-gray-700 font-medium mb-2 text-sm">Representative Name</label>
                                <input type="text" name="authorized_representative_name" value="{{ old('authorized_representative_name', ($prefilled['authorized_representative_name'] ?? null) ?: '') }}" class="authorized-rep-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" @disabled(! $authorizedRepresentativeEnabled)>
                            </div>
                            <div class="form-group col-2">
                                <label class="block text-gray-700 font-medium mb-2 text-sm">Role</label>
                                <input type="text" name="authorized_representative_role" value="{{ old('authorized_representative_role', ($prefilled['authorized_representative_role'] ?? null) ?: '') }}" class="authorized-rep-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" @disabled(! $authorizedRepresentativeEnabled)>
                            </div>
                            <div class="form-group col-1">
                                <label class="block text-gray-700 font-medium mb-2 text-sm">ID/License</label>
                                <input type="text" name="authorized_representative_id_license" value="{{ old('authorized_representative_id_license', ($prefilled['authorized_representative_id_license'] ?? null) ?: '') }}" class="authorized-rep-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" @disabled(! $authorizedRepresentativeEnabled)>
                            </div>
                            <div class="form-group col-1">
                                <label class="block text-gray-700 font-medium mb-2 text-sm">Expiry Date</label>
                                <input type="date" name="authorized_representative_expiry_date" value="{{ old('authorized_representative_expiry_date', ($prefilled['authorized_representative_expiry_date'] ?? null) ?: '') }}" class="authorized-rep-field w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" @disabled(! $authorizedRepresentativeEnabled)>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Certification and Submit -->
            <div class="pt-6 border-t border-gray-300">
                <h3 class="text-md font-semibold text-gray-800 text-center mb-2" style="letter-spacing: 0.04em;">CERTIFICATION</h3>
                <p class="text-md text-gray-700 text-center mb-4 max-w-5xl mx-auto" style="text-transform: none;">
                    This is to certify that the above entries are true and correct and that, pilot-in-command of this aircraft, pledge not to fly over prohibited and restricted areas; will not willfully deviate from the filed flight plan, except when necessary in the interest of safety; will operate only in accordance with existing Civil and Military regulations; and will not operate in any manner inimical to the security of the Republic of the Philippines. The herein Pilot-in-Command is qualified to fly the route mentioned in this Flight Plan.
                </p>
                <div class="cert-signature-grid">
                    <div class="cert-signature-item">
                        <div class="cert-signature-line">
                            <span id="cert-pilot-signature-value" class="cert-signature-value"></span>
                        </div>
                        <div class="cert-signature-label">Pilot's Name and Signature</div>
                    </div>
                    <div class="cert-signature-item">
                        <div class="cert-signature-line">
                            <span id="cert-pilot-license-value" class="cert-signature-value"></span>
                        </div>
                        <div class="cert-signature-label">License No. / Rating / Expiry Date</div>
                    </div>
                    <div class="cert-signature-or">OR</div>
                    <div class="cert-signature-item">
                        <div class="cert-signature-line">
                            <span id="cert-auth-rep-value" class="cert-signature-value"></span>
                        </div>
                        <div class="cert-signature-label">Duly Authorized Representative</div>
                    </div>
                    <div class="cert-signature-item">
                        <div class="cert-signature-line">
                            <span id="cert-auth-license-value" class="cert-signature-value"></span>
                        </div>
                        <div class="cert-signature-label">License No. / Expiry Date</div>
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 1rem;">                    
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                        Preview Flight Plan
                    </button>
                </div>
            </div>
        </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        window.flightplanAircraftWtcMap = @json($aircraftWtcMap ?? []);
    </script>
    <script src="{{ asset('js/flightplan.js') }}"></script>
</body>
</html>
