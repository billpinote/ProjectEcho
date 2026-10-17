@props(['index', 'person' => [], 'editable' => true])
<div data-manifest-person class="grid gap-3 rounded-lg border border-echo-border p-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="flex items-center justify-between gap-2 sm:col-span-2 lg:col-span-4">
        <strong class="text-sm">Person <span data-person-number>{{ is_numeric($index) ? $index + 1 : '' }}</span></strong>
        @if($editable)
            <div class="flex gap-3 text-sm text-echo-primary">
                <button type="button" data-person-up aria-label="Move person up">Move up</button>
                <button type="button" data-person-down aria-label="Move person down">Move down</button>
                <button type="button" data-person-remove class="text-red-700" aria-label="Remove person">Remove</button>
            </div>
        @endif
    </div>
    <label class="flex flex-col gap-1 text-sm sm:col-span-2">
        Full name
        <input data-person-field="full_name" name="persons[{{ $index }}][full_name]" value="{{ $person['full_name'] ?? '' }}" maxlength="255" required class="rounded-lg border border-echo-border bg-white px-3 py-2 text-base disabled:bg-echo-background">
    </label>
    <label class="flex flex-col gap-1 text-sm">
        Nationality
        <input data-person-field="nationality" name="persons[{{ $index }}][nationality]" value="{{ $person['nationality'] ?? '' }}" maxlength="255" class="rounded-lg border border-echo-border bg-white px-3 py-2 text-base disabled:bg-echo-background">
    </label>
    <label class="flex flex-col gap-1 text-sm">
        Role
        <select data-person-field="classification" name="persons[{{ $index }}][classification]" class="rounded-lg border border-echo-border bg-white px-3 py-2 text-base disabled:bg-echo-background">
            @foreach(['pic' => 'Pilot-in-command', 'crew' => 'Crew', 'passenger' => 'Passenger'] as $value => $label)
                <option value="{{ $value }}" @selected(($person['classification'] ?? 'passenger') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
</div>
