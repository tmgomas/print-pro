<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('registration_number')->unique()->nullable();
            $table->text('address');
            $table->string('phone');
            $table->string('email')->unique();
            $table->string('logo')->nullable();
            $table->json('settings')->nullable(); // Company specific settings
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->string('tax_number')->nullable();
            $table->text('bank_details')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};