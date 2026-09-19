<?php

namespace Tests\Feature;

use App\Models\ArchivoCargado;
use App\Models\Medico;
use App\Models\TurnoMedico;
use App\Models\Uci;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CambioTurnoComponentesTest extends TestCase
{
    use RefreshDatabase;

    public function test_medico_can_request_a_six_hour_component_m_or_t_for_change_or_cession(): void
    {
        $this->withoutMiddleware();

        $uci = Uci::create([
            'nombre' => 'UCI Central',
            'codigo' => 'UCI-01',
        ]);

        $archivo = ArchivoCargado::create([
            'nombre_archivo' => 'prueba.xlsx',
            'ruta' => 'uploads/prueba.xlsx',
            'mes' => now()->month,
            'anio' => now()->year,
            'procesado' => true,
        ]);

        $receptor = Medico::create([
            'nombre' => 'Ana',
            'apellido' => 'Receptor',
            'email' => 'ana@example.com',
            'uci_id' => $uci->id,
            'activo' => true,
        ]);

        $solicitante = Medico::create([
            'nombre' => 'Luis',
            'apellido' => 'Solicitante',
            'email' => 'luis@example.com',
            'uci_id' => $uci->id,
            'activo' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Luis Solicitante',
            'email' => 'luis-user@example.com',
            'rol' => 'medico',
            'medico_id' => $solicitante->id,
        ]);

        $turno = TurnoMedico::create([
            'archivo_id' => $archivo->id,
            'medico_id' => $solicitante->id,
            'uci_id' => $uci->id,
            'fecha' => now()->addDays(5)->toDateString(),
            'dia_numero' => now()->addDays(5)->day,
            'dia_semana' => 'lunes',
            'codigo_turno' => 'MT',
            'horas_diurnas' => 12,
            'horas_nocturnas' => 0,
            'horas_total' => 12,
            'es_fin_semana' => false,
            'es_domingo' => false,
            'estado_turno' => 'programado',
            'fue_laborado' => true,
        ]);

        $response = $this->actingAs($user)->post(route('medico.solicitar-cambio'), [
            'tipo_movimiento' => 'donacion_directa',
            'turno_origen_id' => $turno->id,
            'medico_receptor_id' => $receptor->id,
            'motivo' => 'Necesito ceder solo la mañana',
            'componente_turno' => 'M',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('solicitudes_cambio_turno', [
            'turno_origen_id' => $turno->id,
            'medico_receptor_id' => $receptor->id,
            'componente_turno' => 'M',
            'tipo_movimiento' => 'donacion_directa',
        ]);
    }
}
