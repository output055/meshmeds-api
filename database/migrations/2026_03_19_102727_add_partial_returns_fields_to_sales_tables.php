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
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'refunded_amount')) {
                $table->decimal('refunded_amount', 10, 2)->default(0)->after('subtotal');
            }
        });

        Schema::table('sale_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_items', 'returned_quantity')) {
                $table->integer('returned_quantity')->default(0)->after('quantity');
            }
        });

        Schema::table('sale_reversals', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_reversals', 'returned_items')) {
                $table->json('returned_items')->nullable()->after('reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_reversals', function (Blueprint $table) {
            $table->dropColumn('returned_items');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('returned_quantity');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('refunded_amount');
        });
    }
};
