<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operators')) {
            Schema::table('operators', function (Blueprint $table): void {
                if (! Schema::hasColumn('operators', 'short_name')) {
                    $table->string('short_name')->nullable()->after('name');
                }

                if (! Schema::hasColumn('operators', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('remarks');
                }
            });
        }

        if (Schema::hasTable('flights')) {
            Schema::table('flights', function (Blueprint $table): void {
                if (! Schema::hasColumn('flights', 'operator_id')) {
                    $table->foreignId('operator_id')
                        ->nullable()
                        ->after('filed_by_user_id')
                        ->constrained()
                        ->nullOnDelete();
                }
            });
        }

        $this->backfillUserOperatorsFromPilotProfiles();
    }

    public function down(): void
    {
        if (Schema::hasTable('flights') && Schema::hasColumn('flights', 'operator_id')) {
            Schema::table('flights', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('operator_id');
            });
        }

        if (Schema::hasTable('operators')) {
            Schema::table('operators', function (Blueprint $table): void {
                if (Schema::hasColumn('operators', 'is_active')) {
                    $table->dropColumn('is_active');
                }

                if (Schema::hasColumn('operators', 'short_name')) {
                    $table->dropColumn('short_name');
                }
            });
        }
    }

    private function backfillUserOperatorsFromPilotProfiles(): void
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasTable('operators')
            || ! Schema::hasTable('pilot_profiles')
            || ! Schema::hasColumn('users', 'operator_id')
            || ! Schema::hasColumn('pilot_profiles', 'operator')
        ) {
            return;
        }

        $operatorMatches = $this->operatorMatchesByNormalizedName();

        DB::table('pilot_profiles')
            ->join('users', 'users.id', '=', 'pilot_profiles.user_id')
            ->whereNull('users.operator_id')
            ->whereNotNull('pilot_profiles.operator')
            ->select('users.id as user_id', 'pilot_profiles.operator')
            ->orderBy('users.id')
            ->each(function (object $row) use ($operatorMatches): void {
                $normalizedOperator = $this->normalizeOperatorName($row->operator);

                if ($normalizedOperator === null) {
                    return;
                }

                $operatorIds = $operatorMatches[$normalizedOperator] ?? [];

                if (count($operatorIds) !== 1) {
                    return;
                }

                DB::table('users')
                    ->where('id', $row->user_id)
                    ->whereNull('operator_id')
                    ->update(['operator_id' => $operatorIds[0]]);
            });
    }

    /**
     * @return array<string, array<int, int>>
     */
    private function operatorMatchesByNormalizedName(): array
    {
        $matches = [];
        $operatorColumns = ['id', 'name'];

        if (Schema::hasColumn('operators', 'short_name')) {
            $operatorColumns[] = 'short_name';
        }

        DB::table('operators')
            ->select($operatorColumns)
            ->orderBy('id')
            ->each(function (object $operator) use (&$matches): void {
                foreach (['name', 'short_name'] as $column) {
                    if (! property_exists($operator, $column)) {
                        continue;
                    }

                    $normalized = $this->normalizeOperatorName($operator->{$column});

                    if ($normalized === null) {
                        continue;
                    }

                    $matches[$normalized][] = (int) $operator->id;
                }
            });

        foreach ($this->operatorAliasRows() as $aliasRow) {
            $normalized = $this->normalizeOperatorName($aliasRow->alias);

            if ($normalized === null) {
                continue;
            }

            $matches[$normalized][] = (int) $aliasRow->operator_id;
        }

        return array_map(
            static fn (array $operatorIds): array => array_values(array_unique($operatorIds)),
            $matches,
        );
    }

    /**
     * @return iterable<object{operator_id: int, alias: mixed}>
     */
    private function operatorAliasRows(): iterable
    {
        if (! Schema::hasTable('operator_aliases') || ! Schema::hasColumn('operator_aliases', 'operator_id')) {
            return [];
        }

        foreach (['name', 'alias', 'short_name'] as $aliasColumn) {
            if (Schema::hasColumn('operator_aliases', $aliasColumn)) {
                return DB::table('operator_aliases')
                    ->select(['operator_id', "{$aliasColumn} as alias"])
                    ->whereNotNull($aliasColumn)
                    ->orderBy('operator_id')
                    ->get();
            }
        }

        return [];
    }

    private function normalizeOperatorName(mixed $value): ?string
    {
        $normalized = Str::of((string) ($value ?? ''))
            ->squish()
            ->lower()
            ->toString();

        return $normalized === '' ? null : $normalized;
    }
};
