<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['slug' => 'director', 'name' => 'Director'],
            ['slug' => 'vice', 'name' => 'Vice'],
            ['slug' => 'banquero', 'name' => 'Banquero'],
            ['slug' => 'jefe_casa', 'name' => 'Jefe de Casa'],
            ['slug' => 'moderador', 'name' => 'Moderador'],
            ['slug' => 'estudiante', 'name' => 'Estudiante'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}