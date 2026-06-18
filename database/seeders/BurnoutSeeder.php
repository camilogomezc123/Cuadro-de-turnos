<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BurnoutEncuesta;
use App\Models\BurnoutPregunta;

class BurnoutSeeder extends Seeder
{
    public function run(): void
    {
        // Eliminar encuesta previa si existe (para re-sembrar limpio)
        BurnoutEncuesta::query()->delete();

        $enc = BurnoutEncuesta::create([
            'nombre'           => 'Tamizaje de Desgaste Profesional',
            'descripcion'      => 'Encuesta breve de 5 preguntas orientada a detectar riesgo de desgaste profesional. No es diagnóstica ni reemplaza valoración clínica.',
            'periodo'          => 'mensual',
            'activa'           => true,
            'permite_posponer' => false,
            'creada_por'       => 1,
        ]);

        $preguntas = [
            [
                'texto_pregunta' => 'Me siento emocionalmente agotado por mi trabajo.',
                'dimension'      => 'agotamiento_emocional',
                'orden'          => 1,
            ],
            [
                'texto_pregunta' => 'Siento que mi carga laboral supera mi capacidad de recuperación.',
                'dimension'      => 'carga_laboral_recuperacion',
                'orden'          => 2,
            ],
            [
                'texto_pregunta' => 'Me he sentido más distante, frío o irritable con pacientes o compañeros.',
                'dimension'      => 'despersonalizacion',
                'orden'          => 3,
            ],
            [
                'texto_pregunta' => 'Siento que mi trabajo ha perdido satisfacción o sentido de logro.',
                'dimension'      => 'realizacion_personal',
                'orden'          => 4,
            ],
            [
                'texto_pregunta' => 'Mis turnos están afectando mi descanso, vida familiar o bienestar.',
                'dimension'      => 'impacto_turnos',
                'orden'          => 5,
            ],
        ];

        foreach ($preguntas as $p) {
            BurnoutPregunta::create([
                'encuesta_id'    => $enc->id,
                'texto_pregunta' => $p['texto_pregunta'],
                'dimension'      => $p['dimension'],
                'orden'          => $p['orden'],
                'activa'         => true,
                'obligatoria'    => true,
            ]);
        }
    }
}
