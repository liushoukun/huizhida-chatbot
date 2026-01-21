<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RedJasmine\Admin\Domain\Models\Admin;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();


        Admin::factory()->create([
            'name'     => 'admin',
            'email'    => 'admin@red-jasmine.top',
            'password' => 'admin'
        ]);
        Admin::factory()->create([
            'name'     => 'service',
            'email'    => 'service@red-jasmine.top',
            'password' => 'service'
        ]);

    }
}
