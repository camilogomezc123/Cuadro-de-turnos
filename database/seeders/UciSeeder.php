<?php

namespace Database\Seeders;

use App\Models\Uci;
use Illuminate\Database\Seeder;

class UciSeeder extends Seeder
{
    public function run(): void
    {
        $ucis = [
            ['nombre' => 'UCI CARDIOVASCULAR',  'codigo' => 'UCI-CARDIO'],
            ['nombre' => 'UCI TORRE B1',         'codigo' => 'UCI-B1'],
            ['nombre' => 'UCI TORRE B2',         'codigo' => 'UCI-B2'],
            ['nombre' => 'UCI TORRE C',          'codigo' => 'UCI-C'],
            ['nombre' => 'UCI RESPIRATORIA',     'codigo' => 'UCI-RESP'],
            ['nombre' => 'UCIN',                 'codigo' => 'UCIN'],
            ['nombre' => 'UCI QUIRÚRGICA',       'codigo' => 'UCI-QUIR'],
            ['nombre' => 'UCI GENERAL',          'codigo' => 'UCI-GEN'],
            ['nombre' => 'UCI NEUROLÓGICA',      'codigo' => 'UCI-NEURO'],
        ];

        foreach ($ucis as $uci) {
            Uci::firstOrCreate(['codigo' => $uci['codigo']], $uci);
        }
    }
}
