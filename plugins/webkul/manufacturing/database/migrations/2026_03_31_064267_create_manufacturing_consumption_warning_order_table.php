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
        Schema::create('manufacturing_consumption_warning_order', function (Blueprint $table) {
            $table->foreignId('consumption_warning_id')
                ->constrained('manufacturing_consumption_warnings')
                ->cascadeOnDelete();

            $table->foreignId('manufacturing_order_id')
                ->constrained('manufacturing_orders')
                ->cascadeOnDelete();

            $table->unique(['consumption_warning_id', 'manufacturing_order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manufacturing_consumption_warning_order');
    }
};
