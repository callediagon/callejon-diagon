<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DirectorSeeder extends Seeder
{
    public function run(): void
    {
        $director = User::firstOrCreate(
            ['username' => 'director'],
            [
                'account_number' => User::nextAvailableAccountNumber(),
                'name' => 'Director',
                'last_name' => null,
                'password' => 'director123',
                'is_active' => true,
            ]
        );

        $role = Role::where('slug', 'director')->firstOrFail();
        $director->roles()->syncWithoutDetaching([$role->id]);

        $this->command->info('Director creado: username=director / password=director123');
    }
}