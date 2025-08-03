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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            
            // Item Information
            $table->string('item_description', 500);
            $table->decimal('quantity', 8, 2);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('unit_weight', 8, 3)->default(0.000);
            
            // Calculated Totals
            $table->decimal('line_total', 10, 2);
            $table->decimal('line_weight', 8, 3)->default(0.000);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            
            // Additional Information
            $table->json('specifications')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['invoice_id']);
            $table->index(['product_id']);
            $table->index(['invoice_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};