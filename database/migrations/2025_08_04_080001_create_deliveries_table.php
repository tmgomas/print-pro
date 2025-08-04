<?php
// database/migrations/2025_08_04_000001_create_deliveries_table.php

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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->string('tracking_number')->unique();
            $table->text('delivery_address');
            $table->string('contact_person');
            $table->string('contact_phone');
            $table->enum('delivery_method', ['internal', 'external', 'pickup', 'courier'])
                  ->default('internal');
            $table->string('external_tracking_id')->nullable();
            $table->string('delivery_provider')->nullable();
            $table->decimal('delivery_cost', 10, 2)->default(0.00);
            $table->enum('status', [
                'pending',
                'assigned', 
                'picked_up',
                'in_transit',
                'out_for_delivery',
                'delivered',
                'failed',
                'returned',
                'cancelled'
            ])->default('pending');
            $table->date('estimated_delivery_date')->nullable();
            $table->datetime('actual_delivery_datetime')->nullable();
            $table->datetime('pickup_datetime')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->json('delivery_proof')->nullable(); // Photos, signatures, etc.
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('delivery_image')->nullable(); // Proof of delivery photo
            $table->text('customer_feedback')->nullable();
            $table->integer('delivery_attempts')->default(0);
            $table->datetime('last_attempt_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['invoice_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['delivery_method', 'status']);
            $table->index(['estimated_delivery_date']);
            $table->index('tracking_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};