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
        Schema::create('payment_verifications', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            
            // Verification Information
            $table->string('verification_method', 50); // manual, automatic, bank_api
            $table->string('bank_reference', 100)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->text('verification_notes')->nullable();
            $table->string('bank_slip_image')->nullable();
            
            // Claimed Payment Details
            $table->decimal('claimed_amount', 12, 2);
            $table->datetime('payment_claimed_date');
            
            // Verification Results
            $table->datetime('verified_at')->nullable();
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index(['payment_id']);
            $table->index(['invoice_id']);
            $table->index(['customer_id']);
            $table->index(['verified_by']);
            $table->index(['verification_status']);
            $table->index(['payment_claimed_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_verifications');
    }
};