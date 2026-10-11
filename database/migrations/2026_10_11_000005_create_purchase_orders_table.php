<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->increments('purchase_order_id');
            $table->unsignedInteger('supplier_id');
            $table->unsignedInteger('created_by');
            $table->date('order_date')->index();
            $table->enum('status', ['PENDING', 'APPROVED', 'ORDERED', 'RECEIVED', 'CANCELLED'])
                ->default('PENDING')->index();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->unsignedInteger('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('supplier_id');
            $table->index('created_by');
            $table->foreign('supplier_id')->references('supplier_id')->on('suppliers')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by')->references('user_id')->on('users')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('approved_by')->references('user_id')->on('users')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('received_by')->references('user_id')->on('users')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
