<x-filament-panels::page>
    <x-filament::section
        heading="Pilot Profile"
        description="Your pilot account details are shown here."
    >
        <dl class="grid gap-6 md:grid-cols-2">
            @foreach ([
                'Full name' => $profileData['full_name'],
                'Email' => $profileData['email'],
                'Pilot license number' => $profileData['pilot_license_number'],
                'Ratings' => $profileData['ratings'],
                'License expiry date' => $profileData['license_expiry_date'],
                'Medical expiry date' => $profileData['medical_expiry_date'],
                'Home base' => $profileData['home_base'],
                'Operator' => $profileData['operator'],
                'Remarks' => $profileData['remarks'],
            ] as $label => $value)
                <div class="{{ $label === 'Remarks' ? 'md:col-span-2' : '' }}">
                    <dt class="text-sm font-medium text-gray-500">
                        {{ $label }}
                    </dt>
                    <dd class="mt-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900">
                        {{ $value }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>
</x-filament-panels::page>
