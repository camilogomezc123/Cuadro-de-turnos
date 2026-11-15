<?php

namespace Tests\Feature;

use App\Models\ArchivoCargado;
use App\Models\Ausencia;
use App\Models\Medico;
use App\Models\SolicitudCambioTurno;
use App\Models\TurnoMedico;
use App\Models\Uci;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * $request->string('x', '') devuelve un Stringable, que en PHP siempre es
 * "verdadero" en un if() aunque el texto que contenga esté vacío. Eso hacía
 * que "if ($estado) $query->where('estado', $estado)" aplicara el filtro
 * con valor vacío incluso cuando el usuario no eligió ningún filtro,
 * mostrando la lista vacía por defecto. Ya se corrigió en AlertaController;
 * esta prueba cubre los otros dos sitios con el mismo patrón.
 */
class FiltrosSinSeleccionarTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuarioMaster(): User
    {
        return User::factory()->create(['rol' => 'master']);
    }

    public function test_cambios_turno_muestra_solicitudes_sin_filtro_de_estado(): void
    {
        $uci = Uci::create(['nombre' => 'UCI Central', 'codigo' => 'UCI-01']);
        $archivo = ArchivoCargado::create([
            'nombre_archivo' => 'prueba.xlsx',
            'ruta'           => 'uploads/prueba.xlsx',
            'mes'            => now()->month,
            'anio'           => now()->year,
            'procesado'      => true,
            'activo'         => true,
        ]);
        $medicoA = Medico::create(['nombre' => 'Ana', 'apellido' => 'A', 'uci_id' => $uci->id, 'activo' => true]);
        $medicoB = Medico::create(['nombre' => 'Beto', 'apellido' => 'B', 'uci_id' => $uci->id, 'activo' => true]);

        $turno = TurnoMedico::create([
            'archivo_id' => $archivo->id, 'medico_id' => $medicoA->id, 'uci_id' => $uci->id,
            'fecha' => now()->addDays(5)->toDateString(), 'dia_numero' => now()->addDays(5)->day,
            'dia_semana' => 'lunes', 'codigo_turno' => 'MT',
            'horas_diurnas' => 12, 'horas_nocturnas' => 0, 'horas_total' => 12,
            'es_fin_semana' => false, 'es_domingo' => false,
            'estado_turno' => 'programado', 'fue_laborado' => true,
        ]);

        SolicitudCambioTurno::create([
            'tipo_movimiento' => 'donacion_directa',
            'turno_origen_id' => $turno->id,
            'medico_solicitante_id' => $medicoA->id,
            'medico_receptor_id' => $medicoB->id,
            'motivo' => 'Necesito ceder el turno',
            'estado' => 'pendiente',
        ]);

        $response = $this->actingAs($this->crearUsuarioMaster())->get(route('cambios-turno.index'));

        $response->assertOk();
        $response->assertSee('Necesito ceder el turno');
    }

    public function test_ausencias_muestra_registros_sin_filtro_de_estado(): void
    {
        $medico = Medico::create(['nombre' => 'Ana', 'apellido' => 'A', 'activo' => true]);

        Ausencia::create([
            'medico_id'    => $medico->id,
            'tipo'         => 'vacaciones',
            'fecha_inicio' => now()->addDays(1)->toDateString(),
            'fecha_fin'    => now()->addDays(5)->toDateString(),
            'estado'       => 'pendiente',
            'descripcion'  => 'Vacaciones de prueba',
        ]);

        $response = $this->actingAs($this->crearUsuarioMaster())->get(route('ausencias.index'));

        $response->assertOk();
        $response->assertDontSee('No hay ausencias registradas');
    }
}
