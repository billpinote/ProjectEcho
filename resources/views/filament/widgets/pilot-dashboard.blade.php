@php
    $user = auth()->user();
    $profile = $user?->pilotProfile()->first();
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="text-sm font-semibold uppercase tracking-wide text-primary-700">Pilot Dashboard</div>
            <h2 class="mt-2 text-2xl font-semibold text-gray-900">Welcome, {{ $user?->fullName() ?: $user?->name }}</h2>
            <div class="mt-4 space-y-2 text-sm text-gray-700">
                <div><span class="font-semibold">License Number:</span> {{ $profile?->license_number ?: '—' }}</div>
                <div><span class="font-semibold">Medical Expiry:</span> {{ $profile?->medical_expiry_date?->format('M d, Y') ?: '—' }}</div>
            </div>
        </div>
    </div>
</div>
