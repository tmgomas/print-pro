<?php
// database/migrations/2025_07_30_000004_create_customers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomersTable extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            // Primary Key
            $table->id();
            
            // Company and Branch Relationships
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null');
            
            // Unique Customer Identifier
            $table->string('customer_code', 50)->unique();
            
            // Basic Customer Information
            $table->string('name', 255);
            $table->string('email', 255)->nullable()->unique();
            $table->string('phone', 20);
            
            // Address Information
            $table->text('billing_address');
            $table->text('shipping_address')->nullable();
            $table->string('city', 100);
            $table->string('postal_code', 10)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('province', 100)->nullable();
            
            // Tax and Financial Information
            $table->string('tax_number', 50)->nullable()->unique();
            $table->decimal('credit_limit', 12, 2)->default(0.00);
            $table->decimal('current_balance', 12, 2)->default(0.00);
            
            // Customer Status and Type
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->enum('customer_type', ['individual', 'business'])->default('individual');
            
            // Individual Customer Specific Fields
            $table->date('date_of_birth')->nullable();
            $table->tinyInteger('age')->nullable(); // Auto-calculated from DOB
            
            // Business Customer Specific Fields
            $table->string('company_name', 255)->nullable();
            $table->string('company_registration', 50)->nullable();
            $table->string('contact_person', 255)->nullable();
            $table->string('contact_person_phone', 20)->nullable();
            $table->string('contact_person_email', 255)->nullable();
            
            // Additional Information
            $table->text('notes')->nullable();
            $table->json('preferences')->nullable(); // Store customer preferences as JSON
            
            // Emergency Contact Information
            $table->string('emergency_contact_name', 255)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relationship', 100)->nullable();
            
            // Audit Trail
            $table->timestamps();
            $table->softDeletes();
            
            // Database Indexes for Performance
            $table->index(['company_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index(['customer_code']);
            $table->index(['phone']);
            $table->index(['email']);
            $table->index(['city', 'province']);
            $table->index(['customer_type', 'status']);
            $table->index(['created_at']);
            
            // Full-text search indexes
            $table->fullText(['name', 'company_name', 'notes']);
            
            // Composite indexes for common queries
            $table->index(['company_id', 'branch_id', 'status'], 'company_branch_status_idx');
            $table->index(['customer_type', 'credit_limit'], 'type_credit_idx');
            $table->index(['current_balance', 'credit_limit'], 'balance_credit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
}

