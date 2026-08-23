<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_types', function (Blueprint $table) {
            $table->id();
            $table->string('type_code')->unique(); // PS, PR
            $table->string('type_name'); // Professional Services, Projects
            $table->enum('workflow_type', ['PS_EXTERNAL', 'PR_INTERNAL']);
            $table->boolean('has_external_approval')->default(false);
            $table->boolean('has_internal_approval')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_types');
    }
};