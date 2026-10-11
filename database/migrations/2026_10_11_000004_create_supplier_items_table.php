<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_items', function (Blueprint $table) {
            $table->increments('supplier_item_id');
            $table->unsignedInteger('supplier_id');
            $table->unsignedInteger('item_id');
            $table->decimal('unit_price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamp('updated_at')->nullable()->useCurrent();

            $table->unique(['supplier_id', 'item_id']);
            $table->index('item_id');
            $table->foreign('supplier_id')->references('supplier_id')->on('suppliers')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('item_id')->references('item_id')->on('inventory_items')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_items');
    }
};
