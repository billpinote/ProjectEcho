    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('manifests.index') }}" class="text-sm font-semibold text-echo-primary">&larr; All manifests</a>
            <h1 class="mt-3 text-3xl font-semibold">Flight manifest</h1>
            <p class="mt-2 text-sm text-echo-text-secondary">{{ $flight->aircraft_identification }} · Flight #{{ $flight->id }} · {{ $flight->date_of_flight }}</p>
        </div>
        <a href="{{ route('flights.view', $flight) }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-echo-border bg-echo-card px-4 py-3 text-sm font-semibold">View flight plan</a>
    </header>
    @if(session('status'))
        <p role="status" class="rounded-lg border border-green-300 bg-green-50 p-4 text-sm text-green-900">{{ session('status') }}</p>
    @endif
    @if($errors->any())
        <div role="alert" class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-900">
            <p class="font-semibold">Your changes were not saved. Please review:</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif
    @if(!$editable)
        <p class="rounded-lg border border-echo-border bg-echo-card p-4 text-sm">{{ $flight->hasBeenAccepted() ? 'Accepted filing — this manifest is read-only. Create a new flight plan and manifest if changes are needed.' : 'This manifest is available for viewing only.' }}</p>
    @endif
    <section class="rounded-xl border border-echo-border bg-echo-card p-5 sm:p-6">
        <h2 class="text-lg font-semibold">Linked flight plan</h2>
        <p class="mt-1 text-sm text-echo-text-secondary">These details come from the flight plan. Saving this manifest does not change them.</p>
        <dl class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach(['aircraft_identification' => 'Aircraft identification', 'type_of_aircraft' => 'Aircraft type', 'date_of_flight' => 'Date of flight', 'departure_aerodrome' => 'Departure', 'destination_aerodrome' => 'Destination', 'pilot_in_command' => 'Pilot-in-command', 'pilot_license_no' => 'PIC license number', 'pilot_ratings' => 'PIC ratings', 'license_expiry_date' => 'PIC license expiry'] as $key => $label)
                <div><dt class="text-xs text-echo-text-secondary">{{ $label }}</dt><dd class="mt-1 text-sm font-semibold">{{ $flightData[$key] ?: 'Not supplied' }}</dd></div>
            @endforeach
        </dl>
    </section>
    <form method="POST" action="{{ route('flights.manifest.update', $flight) }}" data-manifest-form data-pob="{{ $flight->persons_on_board }}" class="flex flex-col gap-6">
        @csrf
        @method('PUT')
        <fieldset @disabled(!$editable) class="flex min-w-0 flex-col gap-6">
            <section class="rounded-xl border border-echo-border bg-echo-card p-5 sm:p-6">
                <h2 class="text-lg font-semibold">Aircraft owner and base</h2>
                <p class="mt-1 text-sm text-echo-text-secondary">All four details are needed before ATMO acceptance. You can save an incomplete draft.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    @foreach(['aircraft_owner_name' => ['Aircraft owner’s name', 'text'], 'aircraft_owner_contact_number' => ['Owner’s contact number', 'tel'], 'home_base_hangar' => ['Home base / hangar', 'text'], 'coa_expiry_date' => ['COA expiry date', 'date']] as $key => [$label, $type])
                        <label class="flex flex-col gap-1 text-sm">{{ $label }}
                            <input name="{{ $key }}" type="{{ $type }}" value="{{ old($key, $values[$key] ?? '') }}" maxlength="255" class="rounded-lg border border-echo-border bg-white px-3 py-2 text-base disabled:bg-echo-background">
                        </label>
                    @endforeach
                </div>
            </section>
            <section class="flex flex-col gap-4 rounded-xl border border-echo-border bg-echo-card p-5 sm:p-6">
                <div><h2 class="text-lg font-semibold">Crew and passengers</h2><p class="mt-1 text-sm text-echo-text-secondary">List the PIC first, followed by additional crew and passengers. Supply each person’s nationality before acceptance.</p></div>
                <p data-person-count role="status" aria-live="polite" class="text-sm font-semibold">Flight-plan persons on board: {{ $flight->persons_on_board ?? 'Not supplied' }}</p>
                <div data-manifest-persons class="flex flex-col gap-3">
                    @php($persons = old('persons', $values['persons'] ?? []))
                    @foreach(is_array($persons) ? $persons : [] as $index => $person)
                        <x-manifest-person :index="$index" :person="is_array($person) ? $person : []" :editable="$editable" />
                    @endforeach
                </div>
                @if($editable)
                    <button type="button" data-person-add class="w-fit rounded-lg border border-echo-primary px-4 py-2 text-sm font-semibold text-echo-primary">+ Add person</button>
                    <template data-person-template><x-manifest-person index="__INDEX__" /></template>
                @endif
            </section>
            <section class="rounded-xl border border-echo-border bg-echo-card p-5 sm:p-6">
                <h2 class="text-lg font-semibold">Cargo and remarks</h2>
                <p class="mt-1 text-sm text-echo-text-secondary">Enter 0 for no cargo. Cargo type and quantity are required when weight is above zero. Use kilograms for total weight.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    @foreach(['cargo_type' => 'Cargo type', 'cargo_quantity' => 'Cargo quantity', 'total_cargo_weight' => 'Total cargo weight (kg)'] as $key => $label)
                        <label class="flex flex-col gap-1 text-sm">{{ $label }}
                            <input name="{{ $key }}" type="{{ $key === 'total_cargo_weight' ? 'number' : 'text' }}" @if($key === 'total_cargo_weight') min="0" max="999999999.999" step="0.001" @else maxlength="255" @endif value="{{ old($key, $values[$key] ?? '') }}" class="rounded-lg border border-echo-border bg-white px-3 py-2 text-base disabled:bg-echo-background">
                        </label>
                    @endforeach
                </div>
                <label class="mt-4 flex flex-col gap-1 text-sm">Remarks
                    <textarea name="remarks" rows="3" maxlength="5000" class="rounded-lg border border-echo-border bg-white px-3 py-2 text-base disabled:bg-echo-background">{{ old('remarks', $values['remarks'] ?? '') }}</textarea>
                </label>
            </section>
        </fieldset>
        <div class="flex flex-col gap-4 rounded-xl border border-echo-border bg-echo-card p-5 sm:flex-row sm:items-start sm:justify-between">
            <div role="status" class="text-sm"><strong>{{ $complete ? 'Saved manifest: ready' : 'Saved manifest: incomplete' }}</strong><p class="mt-1 max-w-3xl text-echo-text-secondary">{{ $completionMessage }}</p><p data-unsaved-notice hidden class="mt-2 font-semibold">Unsaved changes — save to update the readiness check.</p></div>
            @if($editable)<button type="submit" class="shrink-0 rounded-lg bg-echo-primary px-5 py-3 text-sm font-semibold text-white hover:bg-echo-primary-dark">Save manifest</button>@endif
        </div>
    </form>
