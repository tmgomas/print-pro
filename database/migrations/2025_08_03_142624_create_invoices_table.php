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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            // Invoice Information
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->date('due_date');
            
            // Financial Information
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('weight_charge', 10, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->decimal('total_weight', 8, 3)->default(0.000);
            
            // Status Information
            $table->enum('status', ['draft', 'pending', 'processing', 'completed', 'cancelled'])->default('draft');
            $table->enum('payment_status', ['pending', 'partially_paid', 'paid', 'refunded'])->default('pending');
            
            // Additional Information
            $table->text('notes')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['company_id', 'status']);
            $table->index(['branch_id', 'invoice_date']);
            $table->index(['customer_id', 'payment_status']);
            $table->index(['created_by']);
            $table->index('invoice_number');
            $table->index('invoice_date');
            $table->index('due_date');
            $table->index(['payment_status', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};