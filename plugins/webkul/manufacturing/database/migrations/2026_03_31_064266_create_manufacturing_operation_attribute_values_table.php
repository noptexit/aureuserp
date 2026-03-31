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
        Schema::create('manufacturing_operation_attribute_values', function (Blueprint $table) {
            $table->foreignId('operation_id')
                ->constrained('manufacturing_operations')
                ->cascadeOnDelete();

            $table->foreignId('product_attribute_value_id')
                ->constrained('products_product_attribute_values')
                ->cascadeOnDelete();

            $table->unique(['operation_id', 'product_attribute_value_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manufacturing_operation_attribute_values');
    }
};
