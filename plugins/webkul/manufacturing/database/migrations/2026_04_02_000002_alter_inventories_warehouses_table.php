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
        Schema::table('inventories_warehouses', function (Blueprint $table) {
            $table->foreignId('manufacture_pull_id')
                ->nullable()
                ->constrained('inventories_rules')
                ->nullOnDelete();

            $table->foreignId('manufacture_mto_pull_id')
                ->nullable()
                ->constrained('inventories_rules')
                ->nullOnDelete();

            $table->foreignId('pbm_mto_pull_id')
                ->nullable()
                ->constrained('inventories_rules')
                ->nullOnDelete();

            $table->foreignId('sam_rule_id')
                ->nullable()
                ->constrained('inventories_rules')
                ->nullOnDelete();

            $table->foreignId('manu_type_id')
                ->nullable()
                ->constrained('inventories_operation_types')
                ->nullOnDelete();

            $table->foreignId('pbm_type_id')
                ->nullable()
                ->constrained('inventories_operation_types')
                ->nullOnDelete();

            $table->foreignId('sam_type_id')
                ->nullable()
                ->constrained('inventories_operation_types')
                ->nullOnDelete();

            $table->foreignId('pbm_route_id')
                ->nullable()
                ->constrained('inventories_routes')
                ->restrictOnDelete();

            $table->foreignId('pbm_loc_id')
                ->nullable()
                ->constrained('inventories_locations')
                ->nullOnDelete();

            $table->foreignId('sam_loc_id')
                ->nullable()
                ->constrained('inventories_locations')
                ->nullOnDelete();

            $table->string('manufacture_steps')
                ->nullable()
                ->default('one_step');

            $table->boolean('manufacture_to_resupply')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories_warehouses', function (Blueprint $table) {
            $table->dropForeign(['manufacture_pull_id']);
            $table->dropColumn('manufacture_pull_id');

            $table->dropForeign(['manufacture_mto_pull_id']);
            $table->dropColumn('manufacture_mto_pull_id');

            $table->dropForeign(['pbm_mto_pull_id']);
            $table->dropColumn('pbm_mto_pull_id');

            $table->dropForeign(['sam_rule_id']);
            $table->dropColumn('sam_rule_id');

            $table->dropForeign(['manu_type_id']);
            $table->dropColumn('manu_type_id');

            $table->dropForeign(['pbm_type_id']);
            $table->dropColumn('pbm_type_id');

            $table->dropForeign(['sam_type_id']);
            $table->dropColumn('sam_type_id');

            $table->dropForeign(['pbm_route_id']);
            $table->dropColumn('pbm_route_id');

            $table->dropForeign(['pbm_loc_id']);
            $table->dropColumn('pbm_loc_id');

            $table->dropForeign(['sam_loc_id']);
            $table->dropColumn('sam_loc_id');

            $table->dropColumn('manufacture_steps');
            $table->dropColumn('manufacture_to_resupply');
        });
    }
};
