<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained('expense_categories')->onDelete('restrict');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('restrict');
            
            $table->string('expense_number')->unique();
            $table->date('expense_date');
            $table->decimal('amount', 15, 2);
            $table->text('description');
            $table->string('vendor_name')->nullable();
            $table->text('vendor_address')->nullable();
            $table->string('vendor_phone')->nullable();
            $table->string('vendor_email')->nullable();
            
            $table->enum('payment_method', [
                'cash', 'bank_transfer', 'credit_card', 'debit_card', 
                'cheque', 'online_payment', 'petty_cash'
            ])->default('cash');
            
            $table->string('payment_reference')->nullable();
            $table->string('receipt_number')->nullable();
            $table->json('receipt_attachments')->nullable();
            
            $table->enum('status', [
                'draft', 'pending_approval', 'approved', 
                'rejected', 'paid', 'cancelled'
            ])->default('draft');
            
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurring_period', ['weekly', 'monthly', 'quarterly', 'yearly'])->nullable();
            $table->date('next_due_date')->nullable();
            
            $table->text('notes')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            $table->json('metadata')->nullable();
            $table->json('tax_details')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['company_id', 'branch_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index(['created_by', 'expense_date']);
            $table->index(['approved_by', 'approved_at']);
            $table->index(['expense_date', 'status']);
            $table->index(['status', 'priority']);
            $table->index('expense_number');
            $table->index(['is_recurring', 'next_due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
