<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            ServicesSeeder::class,
            SponsorsSeeder::class,
            CategoriesSeeder::class,
            UsersSeeder::class,
            ApartmentsSeeder::class,
            MessagesSeeder::class,
        ]);
    }
}
