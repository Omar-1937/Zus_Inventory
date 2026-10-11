<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->increments('transaction_id');
            $table->unsignedInteger('item_id');
            $table->unsignedInteger('batch_id');
            $table->unsignedInteger('user_id');
            $table->enum('transaction_type', ['IN', 'OUT', 'ADJUSTMENT', 'DISPOSAL']);
            $table->decimal('quantity_change', 12, 3);
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('item_id');
            $table->index('batch_id');
            $table->index('user_id');
            $table->index(['transaction_type', 'created_at']);
            $table->foreign('item_id')->references('item_id')->on('inventory_items')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('batch_id')->references('batch_id')->on('inventory_batches')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('user_id')->references('user_id')->on('users')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
