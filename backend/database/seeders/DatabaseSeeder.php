<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Demo data only outside production: it creates a known login (demo@example.com / password).
     */
    public function run(): void
    {
        if ($this->container->isProduction()) {
            $this->command?->warn('Skipping demo data in production.');

            return;
        }

        $this->call(DemoWeddingSeeder::class);
    }
}
