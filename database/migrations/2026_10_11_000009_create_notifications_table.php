<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * active_key is a generated column that only has a value while an alert is
     * UNREAD or READ. Its unique index guarantees the database can never hold
     * two active alerts for the same type + item + batch.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->increments('notification_id');
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedInteger('batch_id')->nullable();
            $table->enum('notification_type', ['LOW_STOCK', 'EXPIRING_SOON', 'EXPIRED']);
            $table->string('message', 500);
            $table->enum('status', ['UNREAD', 'READ', 'RESOLVED'])->default('UNREAD');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();

            $table->index('item_id');
            $table->index('batch_id');
            $table->index(['status', 'created_at']);
            // RESTRICT (not SET NULL / CASCADE): MySQL forbids referential actions on
            // columns used by the stored generated active_key column. Items and
            // batches are never deleted anyway; they are deactivated or depleted.
            $table->foreign('item_id')->references('item_id')->on('inventory_items')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('batch_id')->references('batch_id')->on('inventory_batches')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE notifications ADD COLUMN active_key VARCHAR(64) GENERATED ALWAYS AS (IF(status = 'RESOLVED', NULL, CONCAT(notification_type, ':', IFNULL(item_id, 0), ':', IFNULL(batch_id, 0)))) STORED");
            DB::statement('ALTER TABLE notifications ADD UNIQUE INDEX notifications_active_key_unique (active_key)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
