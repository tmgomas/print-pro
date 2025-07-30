<?php
// database/migrations/2025_07_30_000001_create_product_categories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductCategoriesTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->onDelete('cascade');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['company_id', 'status']);
            $table->index(['parent_id', 'status']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
}
