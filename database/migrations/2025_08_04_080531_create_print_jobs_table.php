<?php
// database/migrations/2025_08_04_000003_create_print_jobs_table.php

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
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->string('job_number')->unique();
            $table->enum('job_type', [
                'business_cards',
                'brochures', 
                'flyers',
                'posters',
                'banners',
                'stickers',
                'letterheads',
                'envelopes',
                'books',
                'magazines',
                'packaging',
                'labels',
                'custom'
            ])->default('custom');
            $table->json('specifications')->nullable(); // Design specs, colors, paper type, etc.
            $table->json('design_files')->nullable(); // File paths, URLs, etc.
            $table->enum('production_status', [
                'pending',
                'design_review',
                'design_approved',
                'pre_press',
                'printing',
                'finishing',
                'quality_check',
                'completed',
                'on_hold',
                'cancelled'
            ])->default('pending');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->integer('quantity')->default(1);
            $table->decimal('estimated_cost', 10, 2)->default(0.00);
            $table->decimal('actual_cost', 10, 2)->nullable();
            $table->datetime('estimated_completion')->nullable();
            $table->datetime('actual_completion')->nullable();
            $table->datetime('started_at')->nullable();
            $table->text('production_notes')->nullable();
            $table->json('quality_check_data')->nullable(); // QC checklist, issues, etc.
            $table->string('batch_number')->nullable(); // For bulk orders
            $table->integer('completion_percentage')->default(0); // 0-100%
            $table->text('special_instructions')->nullable();
            $table->json('material_requirements')->nullable(); // Paper, ink, etc.
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['invoice_id', 'production_status']);
            $table->index(['branch_id', 'production_status']);
            $table->index(['assigned_to', 'production_status']);
            $table->index(['job_type', 'production_status']);
            $table->index(['priority', 'production_status']);
            $table->index(['estimated_completion']);
            $table->index('job_number');
            $table->index('batch_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};