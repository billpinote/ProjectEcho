<?php

namespace App\Filament\Pages;

use App\Models\Flight;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;

class AlphaController extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Alpha Controller';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Alpha Controller';

    protected string $view = 'filament.pages.alpha-controller';

    public Collection $outboundFlights;

    public Collection $inboundFlights;

    public int $refreshInterval = 10000;

    public array $outboundOrder = [];

    public array $inboundOrder = [];

    public function mount(): void
    {
        $this->loadFlights();
        $this->loadStoredOrder();
    }

    public function loadFlights(): void
    {
        $this->outboundFlights = Flight::query()
            ->accepted()
            ->whereIn('departure_aerodrome', ['RPUS', 'RPLW'])
            ->where(function ($query) {
                $query->whereNull('time_block_on')
                    ->orWhere('time_block_on', '');
            })
            ->orderBy('proposed_time')
            ->get();

        $this->inboundFlights = Flight::query()
            ->accepted()
            ->whereIn('departure_aerodrome', ['RPUS', 'RPLW'])
            ->where(function ($query) {
                $query->whereNotNull('time_touchdown')
                    ->orWhereNotNull('time_block_on');
            })
            ->where(function ($query) {
                $query->where('time_block_on', '<>', '')
                    ->orWhereNotNull('time_block_on');
            })
            ->orderBy('proposed_time')
            ->get();

        $this->applyStoredOrder();
    }

    public function refreshFlights(): void
    {
        $this->loadFlights();
    }

    private function loadStoredOrder(): void
    {
        $this->outboundOrder = session()->get('alpha_controller_outbound_order', []);
        $this->inboundOrder = session()->get('alpha_controller_inbound_order', []);
    }

    private function applyStoredOrder(): void
    {
        if (!empty($this->outboundOrder)) {
            $this->outboundFlights = $this->outboundFlights->sortBy(function ($flight) {
                return array_search($flight->id, $this->outboundOrder) !== false
                    ? array_search($flight->id, $this->outboundOrder)
                    : 999;
            })->values();
        }

        if (!empty($this->inboundOrder)) {
            $this->inboundFlights = $this->inboundFlights->sortBy(function ($flight) {
                return array_search($flight->id, $this->inboundOrder) !== false
                    ? array_search($flight->id, $this->inboundOrder)
                    : 999;
            })->values();
        }
    }

    #[On('update-outbound-order')]
    public function updateOutboundOrder($order): void
    {
        $this->outboundOrder = $order;
        session()->put('alpha_controller_outbound_order', $order);
    }

    #[On('update-inbound-order')]
    public function updateInboundOrder($order): void
    {
        $this->inboundOrder = $order;
        session()->put('alpha_controller_inbound_order', $order);
    }
}

