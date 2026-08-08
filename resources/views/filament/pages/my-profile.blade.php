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

    <x-filament::section
        heading="Profile Update Requests"
        description="Requests you submitted for Admin review."
    >
        @if (empty($profileData['update_requests']))
            <p class="text-sm text-gray-600">No profile update requests submitted.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-2 pr-4">Submitted</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Reason</th>
                            <th class="py-2 pr-4">Reviewer Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($profileData['update_requests'] as $request)
                            <tr class="border-t border-gray-200">
                                <td class="py-2 pr-4">{{ $request['submitted_at'] }}</td>
                                <td class="py-2 pr-4">{{ $request['status'] }}</td>
                                <td class="py-2 pr-4">{{ $request['reason'] }}</td>
                                <td class="py-2 pr-4">{{ $request['reviewer_remarks'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
