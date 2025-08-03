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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('received_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null'); // Added for direct customer relationship
            
            // Payment Information
            $table->string('payment_reference')->unique();
            $table->decimal('amount', 12, 2);
            $table->datetime('payment_date');
            $table->string('payment_method', 50); // cash, bank_transfer, online, card, etc.
            $table->string('bank_name', 100)->nullable();
            $table->string('gateway_reference', 200)->nullable(); // For online payments
            $table->string('transaction_id', 100)->nullable(); // Bank/Gateway transaction ID
            $table->string('cheque_number', 50)->nullable(); // For cheque payments
            
            // Status Information
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            
            // Payment Details
            $table->text('notes')->nullable();
            $table->json('payment_metadata')->nullable(); // Gateway response, bank details, etc.
            $table->string('receipt_image')->nullable(); // Bank slip or receipt image
            $table->datetime('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('rejection_reason')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['invoice_id', 'status']);
            $table->index(['customer_id', 'payment_date']);
            $table->index(['branch_id', 'payment_date']);
            $table->index(['received_by']);
            $table->index(['verified_by']);
            $table->index(['verification_status']);
            $table->index('payment_reference');
            $table->index('payment_method');
            $table->index(['payment_date', 'status']);
            $table->index(['status', 'verification_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};