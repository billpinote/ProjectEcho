<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-base font-semibold text-gray-950">Technical Maintenance</h2>
            <p class="mt-2 text-sm text-gray-600">
                Artisan is reserved for developer and system-maintenance tools. Use the real operational panels when testing or supporting flight workflows.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-950">Future Diagnostics</h3>
                <p class="mt-2 text-sm text-gray-600">
                    Safe read-only checks can live here, such as application version, queue health, cache state, or QR signing-key status.
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-950">Operational Support</h3>
                <p class="mt-2 text-sm text-gray-600">
                    Open Admin, ATMO, ATS, Dispatch, AVSEC, or Pilot directly for operational workflows.
                </p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
