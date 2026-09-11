<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turns the stock Laravel `users` table into a local mirror of the HRIS
     * directory plus the few columns DKM owns.
     *
     * The HRIS keeps people in three tables (`users`, `employee`,
     * `employee_detail`) and authenticates through AWS Cognito, so it stores no
     * password at all. DKM needs its own login and its own `is_mentor` flag, and
     * it must keep working when the HRIS is unreachable — hence a mirror rather
     * than a live foreign key.
     *
     * `hris_employee_id` is the sync key; rows created locally for outside
     * speakers (khatib tamu) simply leave it null.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // ---- Kunci sinkronisasi HRIS ----------------------------------
            $table->char('hris_user_id', 36)->nullable()->unique()->after('id');
            $table->char('hris_employee_id', 36)->nullable()->unique()->after('hris_user_id');
            $table->string('external_employee_id', 30)->nullable()->unique()->after('hris_employee_id');

            // ---- Profil hasil mirror --------------------------------------
            $table->string('phone', 24)->nullable()->after('email');
            $table->string('gender', 20)->nullable()->after('phone');
            $table->date('birth_date')->nullable()->after('gender');
            $table->date('join_date')->nullable()->after('birth_date');
            $table->string('position_name', 120)->nullable()->after('join_date');
            $table->string('job_level', 60)->nullable()->after('position_name');
            $table->string('department_name', 120)->nullable()->after('job_level');
            $table->string('team_name', 120)->nullable()->after('department_name');
            $table->string('building_name', 120)->nullable()->after('team_name');
            $table->boolean('is_supervisor')->default(false)->after('building_name');
            $table->string('avatar_url')->nullable()->after('is_supervisor');

            // ---- Kolom milik DKM ------------------------------------------
            $table->boolean('is_mentor')->default(false)->after('avatar_url');
            $table->boolean('is_active')->default(true)->after('is_mentor');
            $table->timestamp('hris_synced_at')->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('hris_synced_at');

            $table->softDeletes();

            $table->index(['is_active', 'is_mentor']);
            $table->index('department_name');
        });

        /*
         * Accounts pulled from the HRIS arrive without a password; they set one
         * on first activation. Kept as a separate statement because `change()`
         * cannot be combined with the column additions above.
         */
        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_active', 'is_mentor']);
            $table->dropIndex(['department_name']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'hris_user_id',
                'hris_employee_id',
                'external_employee_id',
                'phone',
                'gender',
                'birth_date',
                'join_date',
                'position_name',
                'job_level',
                'department_name',
                'team_name',
                'building_name',
                'is_supervisor',
                'avatar_url',
                'is_mentor',
                'is_active',
                'hris_synced_at',
                'last_login_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable(false)->change();
        });
    }
};
