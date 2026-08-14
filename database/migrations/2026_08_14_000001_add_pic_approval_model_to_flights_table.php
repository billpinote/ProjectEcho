<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->foreignId('prepared_by_user_id')
                ->nullable()
                ->after('filed_by_user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('prepared_by_name')
                ->nullable()
                ->after('prepared_by_user_id');

            $table->string('prepared_by_role')
                ->nullable()
                ->after('prepared_by_name');

            $table->foreignId('pilot_in_command_user_id')
                ->nullable()
                ->after('pilot_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('pic_authorized_by_user_id')
                ->nullable()
                ->after('license_expiry_date')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('pic_authorized_at')
                ->nullable()
                ->after('pic_authorized_by_user_id');

            $table->string('pic_authorization_method')
                ->nullable()
                ->after('pic_authorized_at');

            $table->string('pic_authorization_token')
                ->nullable()
                ->after('pic_authorization_method');

            $table->timestamp('pic_authorization_token_expires_at')
                ->nullable()
                ->after('pic_authorization_token');

            $table->unsignedInteger('revision_number')
                ->default(1)
                ->after('pic_authorization_token_expires_at');

            $table->unsignedInteger('pic_authorized_revision')
                ->nullable()
                ->after('revision_number');
        });
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('prepared_by_user_id');
            $table->dropColumn('prepared_by_name');
            $table->dropColumn('prepared_by_role');
            $table->dropConstrainedForeignId('pilot_in_command_user_id');
            $table->dropConstrainedForeignId('pic_authorized_by_user_id');
            $table->dropColumn('pic_authorized_at');
            $table->dropColumn('pic_authorization_method');
            $table->dropColumn('pic_authorization_token');
            $table->dropColumn('pic_authorization_token_expires_at');
            $table->dropColumn('revision_number');
            $table->dropColumn('pic_authorized_revision');
        });
    }
};
