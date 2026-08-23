<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('registration_number')->unique();
            $table->string('vat_number')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('primary_contact_name');
            $table->string('primary_contact_email');
            $table->string('primary_contact_phone');
            $table->decimal('contract_value', 15, 2)->nullable();
            $table->string('payment_terms')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};