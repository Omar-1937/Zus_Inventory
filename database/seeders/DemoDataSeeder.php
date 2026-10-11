<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\StockTransaction;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\User;
use App\Modules\DashboardReports\Services\StockAlertService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\PurchaseOrders\Services\PurchaseOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * OPTIONAL fictional demonstration data. Never run automatically.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Sets up the presentation scenario: Oat Milk has 4 L usable stock against a
 * minimum of 8 L (Low Stock), and "Supplier A" sells Oat Milk.
 *
 * Demo logins (fictional): admin@zus-demo.test / Admin@1234
 *                          staff@zus-demo.test / Staff@1234
 */
class DemoDataSeeder extends Seeder
{
    public function run(StockService $stock, PurchaseOrderService $orders, StockAlertService $alerts): void
    {
        if (InventoryItem::query()->where('item_name', 'Oat Milk')->exists()) {
            $this->command?->warn('Demo data already present (Oat Milk exists). Nothing was inserted.');

            return;
        }

        DB::transaction(function () use ($stock, $orders): void {
            $admin = User::query()->firstOrCreate(
                ['email' => 'admin@zus-demo.test'],
                ['full_name' => 'Aina Admin', 'password' => 'Admin@1234', 'role' => UserRole::Admin, 'is_active' => true],
            );
            $staff = User::query()->firstOrCreate(
                ['email' => 'staff@zus-demo.test'],
                ['full_name' => 'Hafiz Staff', 'password' => 'Staff@1234', 'role' => UserRole::Staff, 'is_active' => true],
            );

            $categories = collect([
                'Coffee' => 'Coffee beans and espresso blends',
                'Dairy & Alternatives' => 'Fresh milk and plant-based milk',
                'Syrups & Sauces' => 'Flavouring syrups',
                'Powders' => 'Matcha, chocolate and other powders',
                'Packaging' => 'Cups, lids, straws and takeaway packaging',
            ])->map(fn (string $description, string $name): Category => Category::query()->firstOrCreate(['category_name' => $name], ['description' => $description]));

            $itemDefinitions = [
                // name, category, unit, minimum, tracks expiry
                ['Coffee Beans', 'Coffee', 'kg', '5', true],
                ['Fresh Milk', 'Dairy & Alternatives', 'L', '10', true],
                ['Oat Milk', 'Dairy & Alternatives', 'L', '8', true],
                ['Vanilla Syrup', 'Syrups & Sauces', 'bottle', '4', true],
                ['Caramel Syrup', 'Syrups & Sauces', 'bottle', '4', true],
                ['Matcha Powder', 'Powders', 'kg', '2', true],
                ['Chocolate Powder', 'Powders', 'kg', '2', true],
                ['Coffee Cups', 'Packaging', 'pcs', '500', false],
                ['Cup Lids', 'Packaging', 'pcs', '500', false],
                ['Paper Straws', 'Packaging', 'pcs', '300', false],
            ];

            $items = collect($itemDefinitions)->mapWithKeys(fn (array $definition): array => [
                $definition[0] => InventoryItem::query()->create([
                    'item_name' => $definition[0],
                    'category_id' => $categories[$definition[1]]->category_id,
                    'unit' => $definition[2],
                    'minimum_stock' => $definition[3],
                    'expiry_tracking' => $definition[4],
                    'description' => 'Demo item',
                    'is_active' => true,
                ]),
            ]);

            $supplierA = Supplier::query()->create([
                'supplier_name' => 'Supplier A - Lembah Dairy Sdn Bhd',
                'contact_person' => 'Mei Ling Tan',
                'phone' => '03-5550 1001',
                'email' => 'orders@lembahdairy.example',
                'address' => "12 Jalan Susu 3\n47100 Puchong, Selangor",
                'is_active' => true,
            ]);
            $supplierB = Supplier::query()->create([
                'supplier_name' => 'Supplier B - Biji Kopi Roasters',
                'contact_person' => 'Arif Rahman',
                'phone' => '03-5550 2002',
                'email' => 'sales@bijikopi.example',
                'address' => "8 Lorong Kopi\n50450 Kuala Lumpur",
                'is_active' => true,
            ]);
            $supplierC = Supplier::query()->create([
                'supplier_name' => 'Supplier C - PackRight Packaging',
                'contact_person' => 'Kavitha Nair',
                'phone' => '03-5550 3003',
                'email' => 'hello@packright.example',
                'address' => "Lot 5, Kawasan Perindustrian\n40000 Shah Alam, Selangor",
                'is_active' => true,
            ]);

            $prices = [
                [$supplierA, 'Fresh Milk', '7.20'],
                [$supplierA, 'Oat Milk', '12.50'],
                [$supplierB, 'Oat Milk', '13.90'],
                [$supplierB, 'Coffee Beans', '68.00'],
                [$supplierB, 'Vanilla Syrup', '32.00'],
                [$supplierB, 'Caramel Syrup', '32.00'],
                [$supplierB, 'Matcha Powder', '145.00'],
                [$supplierB, 'Chocolate Powder', '48.00'],
                [$supplierC, 'Coffee Cups', '0.28'],
                [$supplierC, 'Cup Lids', '0.12'],
                [$supplierC, 'Paper Straws', '0.06'],
            ];

            foreach ($prices as [$supplier, $itemName, $price]) {
                SupplierItem::query()->create([
                    'supplier_id' => $supplier->supplier_id,
                    'item_id' => $items[$itemName]->item_id,
                    'unit_price' => $price,
                    'is_active' => true,
                ]);
            }

            // Opening stock (recorded as normal Stock In transactions).
            $opening = [
                ['Coffee Beans', 'BEAN-OPEN-01', '12', 60],
                ['Fresh Milk', 'MILK-OPEN-01', '6', 2],
                ['Fresh Milk', 'MILK-OPEN-02', '14', 7],
                ['Oat Milk', 'OAT-OPEN-01', '4', 20],
                ['Vanilla Syrup', 'VAN-OPEN-01', '6', 120],
                ['Caramel Syrup', 'CAR-OPEN-02', '5', 150],
                ['Matcha Powder', 'MAT-OPEN-01', '3', 45],
                ['Chocolate Powder', 'CHO-OPEN-01', '4', 120],
                ['Coffee Cups', 'CUP-OPEN-01', '1500', null],
                ['Cup Lids', 'LID-OPEN-01', '1400', null],
                ['Paper Straws', 'STR-OPEN-01', '900', null],
            ];

            foreach ($opening as [$itemName, $batchNumber, $quantity, $expiresInDays]) {
                $stock->stockIn($items[$itemName], [
                    'batch_number' => $batchNumber,
                    'quantity' => $quantity,
                    'received_date' => today()->subDays(7)->toDateString(),
                    'expiration_date' => $expiresInDays === null ? null : today()->addDays($expiresInDays)->toDateString(),
                    'reason' => 'Opening stock',
                ], $admin);
            }

            // An expired batch that is still on the shelf (cannot be received through
            // Stock In because expired goods are rejected, so it is inserted directly).
            $expired = InventoryBatch::query()->create([
                'item_id' => $items['Caramel Syrup']->item_id,
                'batch_number' => 'CAR-OPEN-01',
                'received_date' => today()->subDays(120)->toDateString(),
                'expiration_date' => today()->subDays(2)->toDateString(),
                'initial_quantity' => '3',
                'remaining_quantity' => '2',
            ]);
            StockTransaction::query()->create([
                'item_id' => $expired->item_id,
                'batch_id' => $expired->batch_id,
                'user_id' => $admin->user_id,
                'transaction_type' => TransactionType::In,
                'quantity_change' => '3',
                'reason' => 'Opening stock',
            ])->forceFill(['created_at' => today()->subDays(120)])->save();
            StockTransaction::query()->create([
                'item_id' => $expired->item_id,
                'batch_id' => $expired->batch_id,
                'user_id' => $staff->user_id,
                'transaction_type' => TransactionType::Out,
                'quantity_change' => '-1',
                'reason' => 'Used in beverage preparation',
            ])->forceFill(['created_at' => today()->subDays(10)])->save();

            // A week of usage so the movement trend chart has history.
            $usage = [
                [6, 'Coffee Beans', '1.2'], [6, 'Fresh Milk', '1.5'],
                [5, 'Coffee Beans', '1.1'], [5, 'Coffee Cups', '120'],
                [4, 'Fresh Milk', '2'], [4, 'Cup Lids', '110'],
                [3, 'Coffee Beans', '0.9'], [3, 'Vanilla Syrup', '1'],
                [2, 'Fresh Milk', '1.5'], [2, 'Coffee Cups', '140'],
                [1, 'Matcha Powder', '0.4'], [1, 'Paper Straws', '80'],
            ];

            foreach ($usage as [$daysAgo, $itemName, $quantity]) {
                $stock->stockOut($items[$itemName], $quantity, 'Used in beverage preparation', null, $staff)
                    ->each(fn (StockTransaction $transaction) => $transaction->forceFill(['created_at' => now()->subDays($daysAgo)->setTime(15, 30)])->save());
            }

            // A completed purchase order from the packaging supplier (history).
            $packagingOrder = $orders->create($supplierC, [
                ['supplier_item_id' => SupplierItem::query()->where('supplier_id', $supplierC->supplier_id)->where('item_id', $items['Coffee Cups']->item_id)->value('supplier_item_id'), 'quantity' => '1000'],
                ['supplier_item_id' => SupplierItem::query()->where('supplier_id', $supplierC->supplier_id)->where('item_id', $items['Cup Lids']->item_id)->value('supplier_item_id'), 'quantity' => '1000'],
            ], 'Monthly packaging restock', $staff);
            $orders->approve($packagingOrder, $admin);
            $orders->markOrdered($packagingOrder, $admin);
            $orders->receive($packagingOrder, $packagingOrder->items->mapWithKeys(fn ($line): array => [
                $line->po_item_id => ['batch_number' => 'PO-PACK-'.$line->po_item_id, 'quantity' => $line->quantity_ordered, 'expiration_date' => null],
            ])->all(), $staff);
        });

        $alerts->refreshAll();

        $this->command?->info('Demo data inserted. Log in as staff@zus-demo.test / Staff@1234 or admin@zus-demo.test / Admin@1234.');
    }
}
