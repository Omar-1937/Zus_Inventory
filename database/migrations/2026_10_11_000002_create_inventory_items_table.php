<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Current stock is intentionally NOT stored here; it is always derived
     * from inventory_batches.remaining_quantity.
     */
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->increments('item_id');
            $table->unsignedInteger('category_id');
            $table->string('item_name', 150)->unique();
            $table->text('description')->nullable();
            $table->string('unit', 30);
            $table->decimal('minimum_stock', 12, 3)->default(0);
            $table->boolean('expiry_tracking')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->foreign('category_id')->references('category_id')->on('categories')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
