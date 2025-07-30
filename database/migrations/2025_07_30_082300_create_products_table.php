<?php
// database/migrations/2025_07_30_000002_create_products_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained('product_categories')->onDelete('cascade');
            $table->string('product_code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('base_price', 10, 2);
            $table->string('unit_type')->default('piece'); // piece, sheet, roll, etc.
            $table->decimal('weight_per_unit', 8, 3)->default(0); // Weight in kg
            $table->string('weight_unit')->default('kg');
            $table->decimal('tax_rate', 5, 2)->default(0); // Percentage
            $table->string('image')->nullable();
            $table->json('specifications')->nullable(); // Product specifications as JSON
            $table->json('pricing_tiers')->nullable(); // Custom pricing tiers for this product
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('minimum_quantity')->default(1);
            $table->integer('maximum_quantity')->nullable();
            $table->boolean('requires_customization')->default(false);
            $table->text('customization_options')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['company_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index('product_code');
            $table->fullText(['name', 'description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
}