<?php
// database/migrations/2025_08_04_000004_create_production_stages_table.php

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
        Schema::create('production_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_job_id')->constrained()->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('stage_name', [
                'design_review',
                'customer_approval',
                'pre_press_setup',
                'material_preparation',
                'printing_setup',
                'printing_process',
                'color_matching',
                'first_proof',
                'customer_proof_approval',
                'production_run',
                'cutting',
                'folding',
                'binding',
                'laminating',
                'coating',
                'die_cutting',
                'embossing',
                'foil_stamping',
                'quality_inspection',
                'packaging',
                'final_review',
                'ready_for_delivery'
            ]);
            $table->enum('stage_status', [
                'pending',
                'in_progress',
                'completed',
                'on_hold',
                'requires_approval',
                'rejected',
                'skipped'
            ])->default('pending');
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('stage_data')->nullable(); // Stage-specific data like measurements, colors, etc.
            $table->string('approval_status')->nullable(); // For customer approvals
            $table->text('rejection_reason')->nullable();
            $table->json('attachments')->nullable(); // Photos, proofs, documents
            $table->decimal('stage_cost', 8, 2)->nullable(); // Cost for this specific stage
            $table->integer('estimated_duration')->nullable(); // in minutes
            $table->integer('actual_duration')->nullable(); // in minutes
            $table->integer('stage_order')->default(0); // Order of stages
            $table->boolean('requires_customer_approval')->default(false);
            $table->datetime('customer_approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            // Indexes
            $table->index(['print_job_id', 'stage_status']);
            $table->index(['print_job_id', 'stage_order']);
            $table->index(['updated_by']);
            $table->index(['stage_name', 'stage_status']);
            $table->index(['started_at']);
            $table->index(['completed_at']);
            $table->index(['requires_customer_approval']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_stages');
    }
};