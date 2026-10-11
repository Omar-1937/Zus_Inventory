# ZUS Coffee Inventory Management System

BCS3263 Software Quality Assurance project. This is a web-based inventory management system for **one ZUS Coffee branch**. It is built with **Laravel 12, Livewire 4, Blade, Tailwind CSS 4 and MySQL 8.4**.

It has four main modules, and each one lives in its own folder:

| # | Module | Code | Views | Tests |
|---|--------|------|-------|-------|
| 1 | Inventory Management | `app/Modules/Inventory` | `resources/views/modules/inventory` | `tests/Feature/Inventory` |
| 2 | Supplier Management | `app/Modules/Suppliers` | `resources/views/modules/suppliers` | `tests/Feature/Suppliers` |
| 3 | Purchase Order Management | `app/Modules/PurchaseOrders` | `resources/views/modules/purchase-orders` | `tests/Feature/PurchaseOrders` |
| 4 | Dashboard, Notifications & Reports | `app/Modules/DashboardReports` | `resources/views/modules/dashboard-reports` | `tests/Feature/DashboardReports` |

Each module folder contains `Controllers/`, `Livewire/`, `Services/`, `Requests/` (and `Policies/` where needed), a `routes.php` file, and its own service provider. The provider registers the module's routes, Livewire components, policies, view namespace, event listeners and console commands. All providers extend `app/Modules/ModuleServiceProvider.php` and are listed in `bootstrap/providers.php`.

The modules share these files:

- Eloquent models in `app/Models`.
- Enums in `app/Enums`.
- `App\Services\AuditLogger` and `App\Services\Decimal` (exact bcmath arithmetic for money and quantities).
- Authentication: `app/Http/Controllers/Auth`, `routes/web.php`.
- Settings and user management: `app/Livewire/Settings`.

---

## Requirements

- PHP 8.2+ with `pdo_mysql`, `bcmath`, `mbstring` and `openssl` (XAMPP PHP 8.2.12 works).
- Composer 2.
- Node.js 20+ and npm (used only to build the CSS and JS with Vite).
- MySQL 8.4 LTS.

## Setup

```bash
composer install
npm install
npm run build

cp .env.example .env        # skip if .env already exists
php artisan key:generate
```

Edit `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zus_coffee_inventory
DB_USERNAME=zus_app
DB_PASSWORD="your-password"     # quote it if it contains special characters
APP_TIMEZONE=Asia/Kuala_Lumpur
```

Then create the tables and the first administrator:

```bash
php artisan migrate
php artisan zus:create-admin            # prompts for name, email and password
```

Optional: load fictional demonstration data (never run automatically):

```bash
php artisan db:seed --class=DemoDataSeeder
```

Demo logins created by the seeder (fictional): `admin@zus-demo.test` / `Admin@1234` and `staff@zus-demo.test` / `Staff@1234`.

## Running the application

```bash
php artisan serve               # http://127.0.0.1:8000
php artisan schedule:work       # optional: re-evaluates expiry alerts every hour
```

During development you can run `npm run dev` instead of `npm run build` for hot reload.

## Running the tests

The tests run against a **separate** MySQL database, `zus_coffee_inventory_shadow`, which `phpunit.xml` sets with `force="true"`. That database is wiped on every run.

`tests/TestCase.php` refuses to start if the configured database is anything else, so the development database cannot be destroyed by accident. If you ever run `php artisan config:cache`, run `php artisan config:clear` before testing.

```bash
php artisan test                # or: vendor/bin/pest
php artisan test tests/Feature/Inventory    # one module
```

---

## Database (11 core tables)

The 11 core tables are `users`, `categories`, `inventory_items`, `inventory_batches`, `stock_transactions`, `suppliers`, `supplier_items`, `purchase_orders`, `purchase_order_items`, `notifications` and `audit_logs`. Laravel also creates its own tables: `sessions`, `cache`, `jobs`, `password_reset_tokens` and `migrations`.

Design rules:

- **Stock is never stored on `inventory_items`.** It is always calculated from `inventory_batches.remaining_quantity`:
  - *physical quantity* is the sum over all batches;
  - *usable quantity* excludes expired batches.
- Quantities are `DECIMAL(12,3)` and money is `DECIMAL(12,2)` / `DECIMAL(14,2)`. All arithmetic uses bcmath strings (`App\Services\Decimal`), never floats.
- Foreign keys use `RESTRICT`, so history cannot be deleted. Items, suppliers and users are deactivated instead of deleted.
- A MySQL `CHECK (remaining_quantity >= 0)` constraint is the final guard against negative stock.
- `supplier_items` has `UNIQUE(supplier_id, item_id)`.
- `purchase_order_items.unit_price` is copied from `supplier_items` when the order is created, so historical prices never change.
- `notifications.active_key` is a generated column with a UNIQUE index. MySQL itself prevents two active alerts for the same type, item and batch.

Columns added beyond the specification, with reasons:

| Column | Reason |
|--------|--------|
| `users.remember_token` | Needed for Laravel's "remember me" sessions |
| `supplier_items.is_active` | Lets a supplier stop offering an item without deleting order history |
| `purchase_orders.approved_by/at`, `ordered_at`, `received_by/at`, `cancelled_at` | Lifecycle tracking |
| `stock_transactions.notes` | Holds the "Notes" field from the Stock In and Stock Out forms |
| `notifications.resolved_at`, `active_key` | Automatic resolution and duplicate prevention |

## Business rules

- **FEFO stock out.** Stock is taken from the batch with the earliest expiry first, then the oldest received. Batches with no expiry are used last. Expired batches are never used.
- **Concurrency.** Stock out locks the item row and its batches (`SELECT … FOR UPDATE`) inside a transaction. Receiving locks the purchase order row, then rechecks its status, so an order can never be received twice.
- **Expiry.** A batch is *Expiring Soon* when its expiration date is within 3 days (`INVENTORY_EXPIRY_ALERT_DAYS`). It is *Expired* after that date and *Depleted* when empty. Expired stock still counts as physical stock until someone disposes of it.
- **Low stock.** An item is low on stock when its usable quantity is at or below its minimum stock.
- **Purchase order lifecycle.** PENDING → APPROVED → ORDERED → RECEIVED, with CANCELLED as an alternative end state. Only admins can approve or mark an order as ORDERED. Receiving is full-order only and happens in one atomic transaction.
- **Prices.** They are always read from `supplier_items` on the server. The order form never sends a price.
- **Alerts.** Alerts are refreshed after every stock change (the `StockLevelChanged` event fires after commit) and every hour by the scheduler. They resolve automatically when the problem is fixed. Alerts never create purchase orders.

## Roles

| Action | Admin | Staff |
|--------|:----:|:----:|
| View inventory, suppliers, reports, dashboard | ✓ | ✓ |
| Stock in / stock out / dispose | ✓ | ✓ |
| Adjust batch quantity after a stock count | ✓ | – |
| Add or edit items and categories | ✓ | – |
| Manage suppliers and prices | ✓ | – |
| Create purchase orders, receive deliveries | ✓ | ✓ |
| Approve / mark ordered | ✓ | – |
| Cancel orders | any | own PENDING orders |
| Manage user accounts | ✓ | – |

## Migration from the previous React/Express/Prisma version

The earlier prototype, `OneDrive/Desktop/Zus Coffee` (React + Express + Prisma), was left untouched.

Its 11 empty Prisma tables in `zus_coffee_inventory` were checked with `COUNT(*)`, dropped with the owner's approval, and recreated by the Laravel migrations above. The table names, column names and types were kept the same.

Laravel 13 needs PHP 8.3 and the installed XAMPP PHP is 8.2, so the project uses Laravel 12, the latest version that supports PHP 8.2.
