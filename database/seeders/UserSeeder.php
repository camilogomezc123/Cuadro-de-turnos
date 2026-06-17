<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Usuario master
        User::updateOrCreate(
            ['email' => 'master@uci.local'],
            [
                'name'     => 'Administrador Master',
                'password' => Hash::make('master2024'),
                'rol'      => 'master',
            ]
        );

        // Coordinadora UCI Torre C
        User::updateOrCreate(
            ['email' => 'coordinadora@uci.local'],
            [
                'name'         => 'Coordinadora UCI Torre C',
                'password'     => Hash::make('coord2024'),
                'rol'          => 'coordinador',
                'uci_asignada' => 'UCI-C',
            ]
        );

        // Visualizador general
        User::updateOrCreate(
            ['email' => 'viewer@uci.local'],
            [
                'name'     => 'Visualizador',
                'password' => Hash::make('viewer2024'),
                'rol'      => 'visualizador',
            ]
        );
    }
}
