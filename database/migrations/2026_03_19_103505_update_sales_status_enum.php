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
        try {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE sales MODIFY status ENUM('Completed', 'voided', 'partially_refunded') DEFAULT 'Completed'");
        } catch (\Exception $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE sales MODIFY status ENUM('Completed', 'voided') DEFAULT 'Completed'");
    }
};
