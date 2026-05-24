@props(['flight', 'direction' => 'outbound'])

@php
    $acType = $flight->type_of_aircraft ?? 'N/A';
    $callsign = $flight->aircraft_identification ?? 'N/A';
    $level = $flight->level ?? 'N/A';
    $equipment = trim(($flight->equipment_10a ?? '') . '/' . ($flight->equipment_10b ?? ''));
    $depAero = trim(($flight->departure_aerodrome ?? '') . ' ' . ($flight->proposed_time ?? ''));
    $destAero = $flight->destination_aerodrome ?? 'N/A';
    $route = $flight->route ?? 'N/A';
    $proposedTime = $flight->proposed_time ?? 'N/A';
    $startupTime = $flight->time_start_up ?? 'N/A';

    $statusColor = match(true) {
        $flight->time_airborne !== null => 'border-orange-500 bg-orange-50',
        $flight->time_block_off !== null => 'border-yellow-500 bg-yellow-50',
        default => $direction === 'outbound' ? 'border-blue-500 bg-blue-50' : 'border-green-500 bg-green-50'
    };
@endphp

<div class="rounded-lg border-2 {{ $statusColor }} p-3 shadow-sm hover:shadow-md transition-shadow">
    <div class="grid grid-cols-3 gap-2 h-full">
        <!-- TOP ROW -->
        <div class="flex justify-between w-full gap-2">
            <!-- Departure Aerodrome -->
            <div class="flex items-center justify-center bg-white rounded p-2 border border-gray-200 min-h-[50px]">
                <span class="text-sm font-semibold text-blue-900">{{ $depAero }}</span>
            </div>
            <!-- Route -->
            <div class="flex items-center justify-center bg-white rounded p-2 border border-gray-200 min-h-[60px]">
                <span class="text-xs text-gray-700 text-center line-clamp-3 font-mono">{{ $route }}</span>
            </div>
            <!-- Destination Aerodrome -->
            <div class="flex items-center justify-center bg-white rounded p-2 border border-gray-200 min-h-[50px]">
                <span class="text-sm font-semibold text-green-900">{{ $destAero }}</span>
            </div>
        </div>  

        <!-- MID ROW -->
        <div class="flex flex-col gap-2">
            <!-- Callsign -->
            <div class="flex items-center justify-center bg-white rounded p-2 border border-gray-200 min-h-[60px]">
                <span class="text-lg font-bold text-gray-900 text-center">{{ $callsign }}</span>
            </div>
            
            <!-- Proposed Time -->
            <div class="flex items-center justify-center bg-white rounded p-2 border border-gray-200 min-h-[60px]">
                <span class="text-base font-bold text-gray-900">{{ $proposedTime }}</span>
            </div>
            
        </div>

        <!-- BOTTOM ROW -->
        <div class="flex justify-between w-full gap-2">
            <!-- Left aligned -->
            <div class="flex items-center justify-start bg-white rounded p-2 border border-gray-200 min-h-[50px] w-1/3">
                <span class="text-xs font-semibold text-gray-700">{{ $acType }}</span>
            </div>

            <!-- Center aligned -->
            <div class="flex items-center justify-center bg-white rounded p-2 border border-gray-200 min-h-[50px] w-1/3">
                <span class="text-xs text-gray-700">{{ $equipment ?: 'N/A' }}</span>
            </div>

            <!-- Right aligned -->
            <div class="flex items-center justify-end bg-white rounded p-2 border border-gray-200 min-h-[50px] w-1/3">
                <span class="text-xs text-gray-700">{{ $level }}</span>
            </div>
        </div>
    </div>
</div>
