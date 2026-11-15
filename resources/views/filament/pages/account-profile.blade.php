<x-filament-panels::page>
    <div class="space-y-6">
        @foreach ($profileData['sections'] as $heading => $fields)
            <x-filament::section :heading="$heading">
                @if (empty($fields))
                    <p class="text-sm text-gray-600 dark:text-gray-400">No additional details are recorded.</p>
                @else
                    <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                        @foreach ($fields as $field)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $field['label'] }}</dt>
                                <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $field['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
