<?php

use App\Domain\Pilots\Enums\PilotQualificationCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pilot_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('pilot_profiles', 'license_type')) {
                $table->string('license_type', 16)->nullable()->after('user_id');
            }
        });

        Schema::create('pilot_qualifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pilot_profile_id')->constrained('pilot_profiles')->cascadeOnDelete();
            $table->string('category', 64);
            $table->string('code');
            $table->string('description')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['pilot_profile_id', 'category']);
            $table->index('expiry_date');
        });

        $this->preserveLegacyRatings();
    }

    public function down(): void
    {
        Schema::dropIfExists('pilot_qualifications');

        Schema::table('pilot_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('pilot_profiles', 'license_type')) {
                $table->dropColumn('license_type');
            }
        });
    }

    private function preserveLegacyRatings(): void
    {
        if (
            ! Schema::hasTable('pilot_profiles')
            || ! Schema::hasTable('pilot_qualifications')
            || ! Schema::hasColumn('pilot_profiles', 'ratings')
        ) {
            return;
        }

        DB::table('pilot_profiles')
            ->whereNotNull('ratings')
            ->whereRaw("TRIM(ratings) <> ''")
            ->orderBy('id')
            ->each(function (object $profile): void {
                DB::table('pilot_qualifications')->insert([
                    'pilot_profile_id' => $profile->id,
                    'category' => PilotQualificationCategory::Other->value,
                    'code' => 'LEGACY',
                    'description' => $profile->ratings,
                    'expiry_date' => null,
                    'remarks' => 'Imported from legacy pilot_profiles.ratings.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }
};
