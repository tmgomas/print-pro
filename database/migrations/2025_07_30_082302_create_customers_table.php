
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
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null');
            $table->string('customer_code')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->text('billing_address');
            $table->text('shipping_address')->nullable();
            $table->string('city');
            $table->string('postal_code')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->string('tax_number')->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->enum('customer_type', ['individual', 'business'])->default('individual');
            $table->date('date_of_birth')->nullable();
            $table->string('company_name')->nullable(); // For business customers
            $table->string('contact_person')->nullable(); // For business customers
            $table->text('notes')->nullable();
            $table->json('preferences')->nullable(); // Customer preferences as JSON
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['company_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index('customer_code');
            $table->index(['phone', 'email']);
            $table->fullText(['name', 'company_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
}