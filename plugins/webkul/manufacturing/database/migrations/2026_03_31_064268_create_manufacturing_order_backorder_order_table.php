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
        Schema::create('manufacturing_order_backorder_order', function (Blueprint $table) {
            $table->foreignId('order_backorder_id')
                ->constrained('manufacturing_order_backorders')
                ->cascadeOnDelete();

            $table->foreignId('manufacturing_order_id')
                ->constrained('manufacturing_orders')
                ->cascadeOnDelete();

            $table->unique(['order_backorder_id', 'manufacturing_order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manufacturing_order_backorder_order');
    }
};
