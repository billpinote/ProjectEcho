<?php

use App\Domain\Users\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'role') && DB::getDriverName() === 'mysql') {
            $values = implode("','", UserRole::values());

            DB::statement("ALTER TABLE users MODIFY role ENUM('{$values}') NOT NULL DEFAULT '".UserRole::Pilot->value."'");
        }

        Schema::create('operator_staff_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained()->nullOnDelete();
            $table->string('position_title')->nullable();
            $table->string('company_employee_id')->nullable();
            $table->string('authorization_reference')->nullable();
            $table->date('authorization_expiry_date')->nullable();
            $table->boolean('is_authorized')->default(true);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_staff_profiles');
    }
};
