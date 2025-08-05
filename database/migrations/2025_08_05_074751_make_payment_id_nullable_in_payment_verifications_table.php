<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_verifications', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['payment_id']);
            
            // Modify the column to be nullable
            $table->foreignId('payment_id')->nullable()->change();
            
            // Add the foreign key constraint back
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('payment_verifications', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->foreignId('payment_id')->nullable(false)->change();
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
        });
    }
};