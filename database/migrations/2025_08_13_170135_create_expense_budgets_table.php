<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained('expense_categories')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            
            $table->string('budget_name');
            $table->text('description')->nullable();
            $table->enum('budget_period', ['monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->decimal('budget_amount', 15, 2);
            $table->decimal('spent_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            
            $table->year('budget_year');
            $table->integer('budget_month')->nullable();
            $table->integer('budget_quarter')->nullable();
            
            $table->date('start_date');
            $table->date('end_date');
            
            $table->enum('status', ['active', 'inactive', 'exceeded', 'completed'])->default('active');
            $table->decimal('alert_threshold', 5, 2)->default(80.00);
            $table->boolean('send_alerts')->default(true);
            
            $table->json('alert_settings')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['company_id', 'branch_id', 'status']);
            $table->index(['category_id', 'budget_period']);
            $table->index(['budget_year', 'budget_month']);
            $table->index(['start_date', 'end_date']);
            $table->index(['status', 'alert_threshold']);
            
            // Unique constraint
            $table->unique([
                'company_id', 'branch_id', 'category_id', 'budget_period', 
                'budget_year', 'budget_month', 'budget_quarter'
            ], 'unique_budget_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_budgets');
    }
};