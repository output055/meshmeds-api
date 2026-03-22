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
        Schema::table('sale_reversals', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_reversals', 'amount')) {
                $table->decimal('amount', 12, 2)->default(0)->after('user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_reversals', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
};
