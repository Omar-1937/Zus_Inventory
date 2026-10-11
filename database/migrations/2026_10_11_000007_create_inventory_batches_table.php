<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->increments('batch_id');
            $table->unsignedInteger('item_id');
            $table->unsignedInteger('po_item_id')->nullable();
            $table->string('batch_number', 50);
            $table->date('received_date');
            $table->date('expiration_date')->nullable()->index();
            $table->decimal('initial_quantity', 12, 3);
            $table->decimal('remaining_quantity', 12, 3);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['item_id', 'batch_number']);
            $table->index(['item_id', 'expiration_date']);
            $table->index('po_item_id');
            $table->foreign('item_id')->references('item_id')->on('inventory_items')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('po_item_id')->references('po_item_id')->on('purchase_order_items')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        // Last line of defence against negative stock, even if application code is bypassed.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE inventory_batches ADD CONSTRAINT inventory_batches_quantities_check CHECK (remaining_quantity >= 0 AND initial_quantity > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_batches');
    }
};
