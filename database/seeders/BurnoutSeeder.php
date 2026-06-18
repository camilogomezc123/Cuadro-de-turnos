<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BurnoutEncuesta;
use App\Models\BurnoutPregunta;

class BurnoutSeeder extends Seeder
{
    public function run(): void
    {
        $enc = BurnoutEncuesta::create([
            'nombre'           => 'Evaluación de Bienestar Profesional',
            'descripcion'      => 'Encuesta periódica de evaluación de desgaste profesional para el equipo médico.',
            'periodo'          => 'mensual',
            'activa'           => true,
            'permite_posponer' => false,
            'creada_por'       => 1,
        ]);

        // ── Agotamiento Emocional (9 ítems) ──────────────────────
        $ae = [
            'Ítem AE-1: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Agotamiento emocional',
            'Ítem AE-2: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Agotamiento emocional',
            'Ítem AE-3: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Agotamiento emocional',
            'Ítem AE-4: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Agotamiento emocional',
            'Ítem AE-5: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Agotamiento emocional',
            'Ítem AE-6: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Agotamiento emocional',
            'Ítem AE-7: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Agotamiento emocional',
            'Ítem AE-8: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Agotamiento emocional',
            'Ítem AE-9: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Agotamiento emocional',
        ];

        // ── Despersonalización (5 ítems) ─────────────────────────
        $dp = [
            'Ítem D-1: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Despersonalización',
            'Ítem D-2: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Despersonalización',
            'Ítem D-3: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Despersonalización',
            'Ítem D-4: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Despersonalización',
            'Ítem D-5: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Despersonalización',
        ];

        // ── Realización Personal (8 ítems) ──────────────────────
        $rp = [
            'Ítem RP-1: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Realización personal',
            'Ítem RP-2: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Realización personal',
            'Ítem RP-3: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Realización personal',
            'Ítem RP-4: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Realización personal',
            'Ítem RP-5: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Realización personal',
            'Ítem RP-6: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Realización personal',
            'Ítem RP-7: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Realización personal',
            'Ítem RP-8: [Reemplazar con texto autorizado institucionalmente] — Dimensión: Realización personal',
        ];

        $orden = 1;
        foreach ($ae as $txt) {
            BurnoutPregunta::create([
                'encuesta_id'   => $enc->id,
                'texto_pregunta'=> $txt,
                'dimension'     => 'agotamiento_emocional',
                'orden'         => $orden++,
                'activa'        => true,
                'obligatoria'   => true,
            ]);
        }
        foreach ($dp as $txt) {
            BurnoutPregunta::create([
                'encuesta_id'   => $enc->id,
                'texto_pregunta'=> $txt,
                'dimension'     => 'despersonalizacion',
                'orden'         => $orden++,
                'activa'        => true,
                'obligatoria'   => true,
            ]);
        }
        foreach ($rp as $txt) {
            BurnoutPregunta::create([
                'encuesta_id'   => $enc->id,
                'texto_pregunta'=> $txt,
                'dimension'     => 'realizacion_personal',
                'orden'         => $orden++,
                'activa'        => true,
                'obligatoria'   => true,
            ]);
        }
    }
}
