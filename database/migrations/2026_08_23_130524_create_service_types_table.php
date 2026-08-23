<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->string('service_code')->unique(); // PERMANENT, CONTRACTOR, TEMPORARY, INTERN
            $table->string('service_name');
            $table->boolean('has_benefits')->default(false);
            $table->boolean('has_leave_accrual')->default(false);
            $table->decimal('leave_accrual_rate', 8, 2)->nullable();
            $table->boolean('has_probation_period')->default(false);
            $table->integer('probation_days')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};