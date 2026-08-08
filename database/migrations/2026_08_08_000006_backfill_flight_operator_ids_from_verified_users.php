<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flights') || ! Schema::hasTable('users') || ! Schema::hasTable('operators')) {
            return;
        }

        Schema::table('flights', function (Blueprint $table): void {
            if (! Schema::hasColumn('flights', 'operator_id')) {
                $table->foreignId('operator_id')
                    ->nullable()
                    ->after('filed_by_user_id')
                    ->constrained('operators')
                    ->nullOnDelete();
            }
        });

        $this->ensureOperatorIndex();
        $this->backfillUnambiguousFlightOperators();
        $this->reportUnresolvedFlights();
    }

    public function down(): void
    {
        //
    }

    private function ensureOperatorIndex(): void
    {
        if (! Schema::hasColumn('flights', 'operator_id') || $this->hasOperatorIdIndex()) {
            return;
        }

        Schema::table('flights', function (Blueprint $table): void {
            $table->index('operator_id', 'flights_operator_id_access_index');
        });
    }

    private function hasOperatorIdIndex(): bool
    {
        try {
            foreach (Schema::getIndexes('flights') as $index) {
                $columns = $index['columns'] ?? [];

                if (in_array('operator_id', $columns, true)) {
                    return true;
                }
            }
        } catch (Throwable) {
            return true;
        }

        return false;
    }

    private function backfillUnambiguousFlightOperators(): void
    {
        DB::table('flights')
            ->whereNull('operator_id')
            ->select(['id', 'filed_by_user_id', 'pilot_id'])
            ->orderBy('id')
            ->each(function (object $flight): void {
                $operatorIds = collect([
                    $this->operatorIdForUser($flight->filed_by_user_id),
                    $this->operatorIdForUser($flight->pilot_id),
                ])
                    ->filter()
                    ->unique()
                    ->values();

                if ($operatorIds->count() !== 1) {
                    return;
                }

                DB::table('flights')
                    ->where('id', $flight->id)
                    ->whereNull('operator_id')
                    ->update(['operator_id' => $operatorIds->first()]);
            });
    }

    private function operatorIdForUser(mixed $userId): ?int
    {
        if ($userId === null) {
            return null;
        }

        $operatorId = DB::table('users')
            ->where('id', $userId)
            ->value('operator_id');

        return $operatorId === null ? null : (int) $operatorId;
    }

    private function reportUnresolvedFlights(): void
    {
        $unresolved = DB::table('flights')
            ->whereNull('operator_id')
            ->orderBy('id')
            ->limit(50)
            ->pluck('id');

        $count = DB::table('flights')
            ->whereNull('operator_id')
            ->count();

        if ($count === 0) {
            return;
        }

        Log::warning('Flight operator_id backfill left unresolved records that require manual review.', [
            'unresolved_count' => $count,
            'sample_flight_ids' => $unresolved->all(),
        ]);
    }
};
