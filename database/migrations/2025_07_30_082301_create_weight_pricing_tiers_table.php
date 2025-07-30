<?php
// database/migrations/2025_07_30_000003_create_weight_pricing_tiers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWeightPricingTiersTable extends Migration
{
    public function up(): void
    {
        Schema::create('weight_pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('tier_name');
            $table->decimal('min_weight', 8, 3); // Minimum weight in kg
            $table->decimal('max_weight', 8, 3)->nullable(); // Maximum weight in kg (null for unlimited)
            $table->decimal('base_price', 10, 2); // Base delivery price
            $table->decimal('price_per_kg', 10, 2)->default(0); // Additional price per kg
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['company_id', 'status']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_pricing_tiers');
    }
}
