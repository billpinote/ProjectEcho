<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Pilot Record
                    </p>
                    <h2 class="mt-1 truncate text-xl font-semibold text-gray-950 dark:text-white">
                        {{ $profileData['identity']['name'] }}
                    </h2>

                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-gray-600 dark:text-gray-300">
                        @if (filled($profileData['identity']['operator']))
                            <span>
                                <span class="font-medium text-gray-800 dark:text-gray-100">Operator:</span>
                                {{ $profileData['identity']['operator'] }}
                            </span>
                        @endif

                    </div>
                </div>

                @if (! empty($profileData['identity']['badges']))
                    <div class="flex flex-wrap gap-2 md:justify-end">
                        @foreach ($profileData['identity']['badges'] as $badge)
                            <x-filament::badge :color="$badge['color']">
                                {{ $badge['label'] }}
                            </x-filament::badge>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-filament::section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(22rem,0.72fr)]">
            <div class="space-y-6">
                <x-filament::section heading="Personal Details">
                    @if (empty($profileData['personal_details']))
                        <p class="text-sm text-gray-600 dark:text-gray-400">No personal details are recorded.</p>
                    @else
                        <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                            @foreach ($profileData['personal_details'] as $field)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ $field['label'] }}
                                    </dt>
                                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">
                                        {{ $field['value'] }}
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </x-filament::section>

                <x-filament::section heading="Pilot Licence">
                    @if (empty($profileData['licence']))
                        <p class="text-sm text-gray-600 dark:text-gray-400">No pilot licence is recorded.</p>
                    @else
                        <dl class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($profileData['licence'] as $field)
                                <div class="grid gap-2 py-3 first:pt-0 last:pb-0 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
                                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-300">
                                        {{ $field['label'] }}
                                    </dt>
                                    <dd class="flex flex-wrap items-center gap-2 text-sm text-gray-950 dark:text-white">
                                        <span>{{ $field['value'] }}</span>

                                        @if (! empty($field['status']))
                                            <x-filament::badge :color="$field['status']['color']">
                                                {{ $field['status']['label'] }}
                                            </x-filament::badge>
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </x-filament::section>

                <x-filament::section heading="Medical">
                    @if (empty($profileData['medical']))
                        <p class="text-sm text-gray-600 dark:text-gray-400">No medical information is recorded.</p>
                    @else
                        <dl class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($profileData['medical'] as $field)
                                <div class="grid gap-2 py-3 first:pt-0 last:pb-0 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
                                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-300">
                                        {{ $field['label'] }}
                                    </dt>
                                    <dd class="flex flex-wrap items-center gap-2 text-sm text-gray-950 dark:text-white">
                                        <span>{{ $field['value'] }}</span>

                                        @if (! empty($field['status']))
                                            <x-filament::badge :color="$field['status']['color']">
                                                {{ $field['status']['label'] }}
                                            </x-filament::badge>
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </x-filament::section>

                <x-filament::section heading="Ratings & Endorsements">
                    @if (empty($profileData['qualifications']))
                        <p class="text-sm text-gray-600 dark:text-gray-400">No ratings or endorsements are recorded.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-gray-500 dark:text-gray-400">
                                        <th class="py-2 pr-4 font-medium">Category</th>
                                        <th class="py-2 pr-4 font-medium">Code</th>
                                        <th class="py-2 pr-4 font-medium">Description</th>
                                        <th class="py-2 pr-4 font-medium">Expiry</th>
                                        <th class="py-2 pr-4 font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($profileData['qualifications'] as $qualification)
                                        <tr class="border-t border-gray-200 dark:border-white/10">
                                            <td class="py-2 pr-4 text-gray-950 dark:text-white">{{ $qualification['category'] }}</td>
                                            <td class="py-2 pr-4 font-medium text-gray-950 dark:text-white">{{ $qualification['code'] }}</td>
                                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $qualification['description'] }}</td>
                                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $qualification['expiry'] }}</td>
                                            <td class="py-2 pr-4">
                                                <x-filament::badge :color="$qualification['status']['color']">
                                                    {{ $qualification['status']['label'] }}
                                                </x-filament::badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::section>

                @if (filled($profileData['remarks']))
                    <x-filament::section heading="Remarks">
                        <div class="whitespace-pre-line text-sm leading-6 text-gray-800 dark:text-gray-200">{{ $profileData['remarks'] }}</div>
                    </x-filament::section>
                @endif
            </div>

            <div class="space-y-6">
                <x-filament::section heading="Operator Assignment">
                    @if (empty($profileData['operator_assignment']))
                        <p class="text-sm text-gray-600 dark:text-gray-400">No operator assignment is recorded.</p>
                    @else
                        <dl class="space-y-4">
                            @foreach ($profileData['operator_assignment'] as $field)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ $field['label'] }}
                                    </dt>
                                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">
                                        {{ $field['value'] }}
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </x-filament::section>

                <x-filament::section heading="Verification Record">
                    <dl class="space-y-4">
                        @foreach ($profileData['verification_record'] as $field)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {{ $field['label'] }}
                                </dt>
                                <dd class="mt-1 flex flex-wrap items-center gap-2 text-sm font-medium text-gray-950 dark:text-white">
                                    <span>{{ $field['value'] }}</span>

                                    @if (! empty($field['status']) && $field['status']['label'] !== $field['value'])
                                        <x-filament::badge :color="$field['status']['color']">
                                            {{ $field['status']['label'] }}
                                        </x-filament::badge>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>

                    @if (! empty($profileData['kyc_documents']))
                        <div class="mt-5 border-t border-gray-200 pt-5 dark:border-white/10">
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">KYC / Supporting Documents</h3>
                            <div class="mt-3 space-y-3">
                                @foreach ($profileData['kyc_documents'] as $document)
                                    <div class="rounded-lg border border-gray-200 p-3 text-sm dark:border-white/10">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p class="font-medium text-gray-950 dark:text-white">{{ $document['type'] }}</p>

                                                @if (filled($document['reference']))
                                                    <p class="mt-1 text-gray-600 dark:text-gray-400">Reference {{ $document['reference'] }}</p>
                                                @endif
                                            </div>

                                            @if (filled($document['attachment_url']))
                                                <a
                                                    href="{{ $document['attachment_url'] }}"
                                                    class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                                                >
                                                    Download
                                                </a>
                                            @endif
                                        </div>

                                        <div class="mt-3 grid gap-2 text-gray-600 dark:text-gray-400 sm:grid-cols-2">
                                            @if (filled($document['verified_by']))
                                                <span>Verified by {{ $document['verified_by'] }}</span>
                                            @endif

                                            @if (filled($document['verified_at']))
                                                <span>{{ $document['verified_at'] }}</span>
                                            @endif
                                        </div>

                                        @if (filled($document['remarks']))
                                            <p class="mt-3 text-gray-700 dark:text-gray-300">{{ $document['remarks'] }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </x-filament::section>
            </div>
        </div>

        <x-filament::section
            heading="Account / Administration"
            description="Profile update requests submitted for review."
        >
            @if (empty($profileData['update_requests']))
                <p class="text-sm text-gray-600 dark:text-gray-400">No profile update requests submitted.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4 font-medium">Submitted</th>
                                <th class="py-2 pr-4 font-medium">Status</th>
                                <th class="py-2 pr-4 font-medium">Reason</th>
                                <th class="py-2 pr-4 font-medium">Reviewer Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($profileData['update_requests'] as $request)
                                <tr class="border-t border-gray-200 dark:border-white/10">
                                    <td class="py-2 pr-4 text-gray-950 dark:text-white">{{ $request['submitted_at'] }}</td>
                                    <td class="py-2 pr-4 text-gray-950 dark:text-white">{{ $request['status'] }}</td>
                                    <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $request['reason'] }}</td>
                                    <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $request['reviewer_remarks'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
