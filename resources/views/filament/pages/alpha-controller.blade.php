<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

    <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1 rounded-lg bg-blue-100 px-3 py-1 text-sm font-medium text-blue-900">
                <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-blue-600"></span>
                Live Auto-Refresh (10s)
            </span>
        </div>
        <button
            wire:click="refreshFlights"
            class="inline-flex items-center gap-2 rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 transition-colors"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Refresh Now
        </button>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2" wire:poll-10000="refreshFlights">
        <!-- OUTBOUND COLUMN -->
        <div class="space-y-4">
            <div class="sticky top-0 bg-gradient-to-r from-blue-600 to-blue-800 px-4 py-3 rounded-t-lg z-10">
                <h2 class="text-lg font-bold text-white">OUTBOUND</h2>
                <p class="text-sm text-blue-100">{{ count($outboundFlights) }} flights ready</p>
            </div>
            <div
                class="space-y-3 px-4 pb-4 sortable-list"
                data-type="outbound"
                wire:ignore
            >
                @forelse($outboundFlights as $flight)
                    <div
                        class="flight-strip cursor-move hover:shadow-lg transition-shadow"
                        data-id="{{ $flight->id }}"
                        wire:key="outbound-{{ $flight->id }}"
                    >
                        <x-flight-progress-strip :flight="$flight" direction="outbound" />
                    </div>
                @empty
                    <div class="rounded-lg border-2 border-dashed border-gray-300 p-8 text-center text-gray-500">
                        <p>No outbound flights</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- INBOUND COLUMN -->
        <div class="space-y-4">
            <div class="sticky top-0 bg-gradient-to-r from-green-600 to-green-800 px-4 py-3 rounded-t-lg z-10">
                <h2 class="text-lg font-bold text-white">INBOUND</h2>
                <p class="text-sm text-green-100">{{ count($inboundFlights) }} flights arriving</p>
            </div>
            <div
                class="space-y-3 px-4 pb-4 sortable-list"
                data-type="inbound"
                wire:ignore
            >
                @forelse($inboundFlights as $flight)
                    <div
                        class="flight-strip cursor-move hover:shadow-lg transition-shadow"
                        data-id="{{ $flight->id }}"
                        wire:key="inbound-{{ $flight->id }}"
                    >
                        <x-flight-progress-strip :flight="$flight" direction="inbound" />
                    </div>
                @empty
                    <div class="rounded-lg border-2 border-dashed border-gray-300 p-8 text-center text-gray-500">
                        <p>No inbound flights</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const lists = document.querySelectorAll('.sortable-list');

            lists.forEach(list => {
                const type = list.getAttribute('data-type');

                new Sortable(list, {
                    animation: 150,
                    ghostClass: 'opacity-50 bg-gray-100',
                    dragClass: 'dragging',
                    handle: '.flight-strip',
                    group: false,
                    onEnd: function(evt) {
                        const order = Array.from(list.querySelectorAll('[data-id]'))
                            .map(el => parseInt(el.getAttribute('data-id')));

                        if (type === 'outbound') {
                            Livewire.dispatch('update-outbound-order', { order });
                        } else {
                            Livewire.dispatch('update-inbound-order', { order });
                        }
                    }
                });
            });

            // Re-initialize on Livewire updates
            Livewire.on('flightsUpdated', () => {
                setTimeout(() => {
                    document.querySelectorAll('.sortable-list').forEach(list => {
                        const children = list.querySelectorAll('[data-id]');
                        if (children.length > 0) {
                            // Sortable already handles re-init
                        }
                    });
                }, 100);
            });
        });
    </script>

    <style>
        .dragging {
            opacity: 0.5;
        }

        .flight-strip {
            touch-action: none;
        }

        .sortable-list.sortable-ghost {
            background: #f3f4f6;
        }

        @media (prefers-reduced-motion: reduce) {
            .flight-strip {
                transition: none;
            }
        }
    </style>
</x-filament-panels::page>
