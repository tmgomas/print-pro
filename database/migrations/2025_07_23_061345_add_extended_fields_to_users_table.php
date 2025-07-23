<?php

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
        Schema::table('users', function (Blueprint $table) {
            $table->after('remember_token', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
                $table->foreignId('branch_id')->nullable()->constrained()->onDelete('cascade');
                $table->string('first_name')->after('name');
                $table->string('last_name')->after('first_name');
                $table->string('phone')->nullable();
                $table->string('avatar')->nullable();
                $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
                $table->json('preferences')->nullable(); // User preferences
                $table->timestamp('last_login_at')->nullable();
                $table->string('last_login_ip')->nullable();
                $table->softDeletes()->after('updated_at');
            });
            
            // Modify existing name column to be nullable since we're using first_name/last_name
            $table->string('name')->nullable()->change();
            
            $table->index(['company_id', 'branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['branch_id']);
            $table->dropColumn([
                'company_id', 'branch_id', 'first_name', 'last_name', 
                'phone', 'avatar', 'status', 'preferences', 
                'last_login_at', 'last_login_ip'
            ]);
            $table->dropSoftDeletes();
            $table->string('name')->nullable(false)->change();
        });
    }
};