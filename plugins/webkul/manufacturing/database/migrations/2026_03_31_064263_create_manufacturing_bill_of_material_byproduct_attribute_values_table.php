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
        Schema::create('manufacturing_bill_of_material_byproduct_attribute_values', function (Blueprint $table) {
            $table->foreignId('byproduct_id')
                ->constrained('manufacturing_bill_of_material_byproducts')
                ->cascadeOnDelete();

            $table->foreignId('product_attribute_value_id')
                ->constrained('products_product_attribute_values')
                ->cascadeOnDelete();

            $table->unique(['byproduct_id', 'product_attribute_value_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manufacturing_bill_of_material_byproduct_attribute_values');
    }
};
