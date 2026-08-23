<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_number')->unique()->nullable()->after('id');
            $table->string('id_number')->nullable()->after('last_name');
            $table->enum('employee_type', ['ps', 'pr'])->nullable()->after('role');
            $table->enum('service_type', ['permanent', 'contractor', 'temporary', 'intern'])->nullable()->after('employee_type');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete()->after('service_type');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete()->after('client_id');
            $table->date('hire_date')->nullable()->after('project_id');
            $table->date('termination_date')->nullable()->after('hire_date');
            $table->decimal('leave_balance_annual', 8, 2)->default(0)->after('termination_date');
            $table->decimal('leave_balance_sick', 8, 2)->default(0)->after('leave_balance_annual');
            $table->string('phone')->nullable()->after('email');
            $table->text('address')->nullable()->after('phone');
            $table->string('emergency_contact_name')->nullable()->after('address');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('profile_photo')->nullable()->after('emergency_contact_phone');
            $table->boolean('two_factor_enabled')->default(false)->after('profile_photo');
            $table->timestamp('last_login_at')->nullable()->after('two_factor_enabled');
        });

        // Add foreign key for manager_id
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropColumn([
                'employee_number', 'id_number', 'employee_type', 'service_type',
                'client_id', 'project_id', 'hire_date', 'termination_date',
                'leave_balance_annual', 'leave_balance_sick', 'phone', 'address',
                'emergency_contact_name', 'emergency_contact_phone', 'profile_photo',
                'two_factor_enabled', 'last_login_at'
            ]);
        });
    }
};