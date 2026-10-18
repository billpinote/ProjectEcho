<x-manifest-layout title="Guest flight manifest">
    <h1 class="text-3xl font-semibold">Guest flight manifest PDF</h1>
    <p>Prepare a CAAP manifest without an Echo flight plan. Enter the PIC once as the first person. Your entries are used only to return the PDF and are not saved by Echo.</p>
    <form method="POST" action="{{ route('manifests.guest.pdf') }}" target="_blank" autocomplete="off" data-manifest-form class="flex flex-col gap-6">
        <section class="grid gap-4 rounded-xl border border-echo-border bg-echo-card p-6 sm:grid-cols-2">
            @foreach(['aircraft_identification' => ['Aircraft identification', 'text', true], 'type_of_aircraft' => ['Aircraft type', 'text', true], 'date_of_flight' => ['Date of flight', 'date', true], 'home_base_hangar' => ['Aircraft home base / hangar', 'text', false], 'aircraft_owner_name' => ['Aircraft owner', 'text', false], 'aircraft_owner_contact_number' => ['Owner contact number', 'tel', false], 'coa_expiry_date' => ['COA expiry date', 'date', false], 'pilot_in_command' => ['PIC name', 'text', true], 'pilot_license_no' => ['PIC license number', 'text', false], 'pilot_ratings' => ['PIC ratings', 'text', false], 'license_expiry_date' => ['PIC license expiry date', 'date', false], 'cargo_type' => ['Cargo type', 'text', false], 'cargo_quantity' => ['Cargo quantity', 'text', false], 'total_cargo_weight' => ['Total cargo weight', 'number', false]] as $key => [$label, $type, $required])
                <label class="flex flex-col gap-1 text-sm">{{ $label }}{{ $required ? ' *' : '' }}
                    <input name="{{ $key }}" type="{{ $type }}" maxlength="255" @required($required) @if($type === 'number') min="0" step="0.001" @endif class="rounded-lg border border-echo-border bg-white px-3 py-2 text-base">
                </label>
            @endforeach
        </section>
        <section class="flex flex-col gap-4 rounded-xl border border-echo-border bg-echo-card p-6">
            <h2 class="text-lg font-semibold">Crew and passengers</h2>
            <p data-person-count aria-live="polite"></p>
            <div data-manifest-persons class="flex flex-col gap-3"><x-manifest-person :index="0" :person="['classification' => 'pic']" /></div>
            <button type="button" data-person-add class="w-fit font-semibold text-echo-primary">+ Add person</button>
            <template data-person-template><x-manifest-person index="__INDEX__" /></template>
        </section>
        <p data-unsaved-notice hidden>Your entries have not been sent.</p>
        <div class="flex flex-wrap gap-4">
            <button type="submit" class="rounded-lg bg-echo-primary px-4 py-3 font-semibold text-white">View / Print PDF</button>
            <button type="submit" formaction="{{ route('manifests.guest.pdf', ['download' => 1]) }}" class="rounded-lg border border-echo-primary px-4 py-3 font-semibold text-echo-primary">Download PDF</button>
        </div>
        <p class="text-sm text-echo-text-secondary">Up to 250 people. If validation fails, the new tab lists fields to correct; your entries stay in this tab. Leave the handwritten signature for the PIC.</p>
    </form>
</x-manifest-layout>
