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
        Schema::create('manufacturing_work_center_productivity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('loss_type')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->decimal('duration', 15, 4)->nullable();

            $table->foreignId('work_center_id')
                ->constrained('manufacturing_work_centers')
                ->restrictOnDelete();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->foreignId('work_order_id')
                ->nullable()
                ->constrained('manufacturing_work_orders')
                ->nullOnDelete();

            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('loss_id')
                ->constrained('manufacturing_work_center_productivity_losses')
                ->restrictOnDelete();

            $table->foreignId('creator_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manufacturing_work_center_productivity_logs');
    }
};
