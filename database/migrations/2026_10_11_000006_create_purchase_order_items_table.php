<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * unit_price is copied from supplier_items at order time so that later
     * price changes never alter historical purchase orders.
     */
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->increments('po_item_id');
            $table->unsignedInteger('purchase_order_id');
            $table->unsignedInteger('supplier_item_id');
            $table->decimal('quantity_ordered', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 14, 2);

            $table->unique(['purchase_order_id', 'supplier_item_id']);
            $table->index('supplier_item_id');
            $table->foreign('purchase_order_id')->references('purchase_order_id')->on('purchase_orders')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('supplier_item_id')->references('supplier_item_id')->on('supplier_items')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
