<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE production_stages MODIFY COLUMN stage_status ENUM(
            'pending',
            'ready',
            'in_progress',
            'completed',
            'on_hold',
            'requires_approval',
            'rejected',
            'skipped'
        ) DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE production_stages MODIFY COLUMN stage_status ENUM(
            'pending',
            'in_progress',
            'completed',
            'on_hold',
            'requires_approval',
            'rejected',
            'skipped'
        ) DEFAULT 'pending'");
    }
};