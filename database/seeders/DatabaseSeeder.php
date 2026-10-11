<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Intentionally inserts nothing: the application must work with an empty
     * database. Create the first administrator with `php artisan zus:create-admin`
     * and, optionally, load fictional demo data with
     * `php artisan db:seed --class=DemoDataSeeder`.
     */
    public function run(): void
    {
        $this->command?->info('No default data seeded. Use `php artisan zus:create-admin` and optionally `php artisan db:seed --class=DemoDataSeeder`.');
    }
}
