<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('expense_categories')->onDelete('cascade');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_system_category')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['company_id', 'status']);
            $table->index(['parent_id', 'status']);
            $table->index('sort_order');
            $table->index('is_system_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
