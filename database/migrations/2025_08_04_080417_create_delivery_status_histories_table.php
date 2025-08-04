<?php
// database/migrations/2025_08_04_000002_create_delivery_status_history_table.php

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
        Schema::create('delivery_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
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
            ]);
            $table->text('notes')->nullable();
            $table->json('location_data')->nullable(); // GPS coordinates, address, etc.
            $table->string('image_path')->nullable(); // Status update photo
            $table->datetime('status_datetime')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            
            // Indexes
            $table->index(['delivery_id', 'status']);
            $table->index(['updated_by']);
            $table->index(['status_datetime']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_status_history');
    }
};