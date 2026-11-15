<?php

namespace Tests\Feature;

use App\Models\ArchivoCargado;
use App\Models\Medico;
use App\Models\SolicitudCambioTurno;
use App\Models\TurnoMedico;
use App\Models\Uci;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El módulo "Cambios de turno" (CambioTurnoController) y el portal del
 * médico (MedicoPortalController) aprobaban cambios con dos copias de la
 * misma lógica que habían divergido: una ignoraba el componente parcial del
 * turno al intercambiar, y la otra nunca validaba el límite de 216h/mes ni
 * recalculaba el indicador de horas. Ambas ahora delegan en
 * CambioTurnoAprobacionService; estas pruebas cubren esos tres puntos.
 */
class CambioTurnoAprobacionTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuarioMaster(): User
    {
        return User::factory()->create(['rol' => 'master']);
    }

    private function crearArchivo(): ArchivoCargado
    {
        return ArchivoCargado::create([
            'nombre_archivo' => 'prueba.xlsx',
            'ruta'           => 'uploads/prueba.xlsx',
            'mes'            => now()->month,
            'anio'           => now()->year,
            'procesado'      => true,
        ]);
    }

    private function crearTurno(ArchivoCargado $archivo, Uci $uci, Medico $medico, string $codigo, array $horas): TurnoMedico
    {
        $fecha = now()->addDays(5);

        return TurnoMedico::create([
            'archivo_id'      => $archivo->id,
            'medico_id'       => $medico->id,
            'uci_id'          => $uci->id,
            'fecha'           => $fecha->toDateString(),
            'dia_numero'      => $fecha->day,
            'dia_semana'      => 'lunes',
            'codigo_turno'    => $codigo,
            'horas_diurnas'   => $horas['diurnas'],
            'horas_nocturnas' => $horas['nocturnas'],
            'horas_total'     => $horas['total'],
            'es_fin_semana'   => false,
            'es_domingo'      => false,
            'estado_turno'    => 'programado',
            'fue_laborado'    => true,
        ]);
    }

    public function test_aprobar_cambio_directo_con_componente_respeta_solo_ese_componente(): void
    {
        $uci = Uci::create(['nombre' => 'UCI Central', 'codigo' => 'UCI-01']);
        $archivo = $this->crearArchivo();

        $medicoA = Medico::create(['nombre' => 'Ana', 'apellido' => 'A', 'uci_id' => $uci->id, 'activo' => true]);
        $medicoB = Medico::create(['nombre' => 'Beto', 'apellido' => 'B', 'uci_id' => $uci->id, 'activo' => true]);

        $turnoOrigen  = $this->crearTurno($archivo, $uci, $medicoA, 'MT', ['diurnas' => 12, 'nocturnas' => 0, 'total' => 12]);
        $turnoDestino = $this->crearTurno($archivo, $uci, $medicoB, 'N',  ['diurnas' => 0,  'nocturnas' => 12, 'total' => 12]);

        $solicitud = SolicitudCambioTurno::create([
            'tipo_movimiento'       => 'cambio_directo',
            'turno_origen_id'       => $turnoOrigen->id,
            'turno_destino_id'      => $turnoDestino->id,
            'componente_turno'      => 'M',
            'medico_solicitante_id' => $medicoA->id,
            'medico_receptor_id'    => $medicoB->id,
            'motivo'                => 'Cambio parcial de la mañana',
            'estado'                => 'aceptado_colega',
        ]);

        $user = $this->crearUsuarioMaster();
        $response = $this->actingAs($user)->patch(route('cambios-turno.aprobar', $solicitud));

        $response->assertSessionHas('success');

        // Antes del fix se ignoraba el componente y se intercambiaban los
        // códigos completos (origen -> N, destino -> MT). Con el componente
        // 'M' respetado: origen pierde solo la M (MT - M = T) y destino la
        // gana combinada con lo que ya tenía (N + M = MN).
        $this->assertSame('T',  $turnoOrigen->refresh()->codigo_turno);
        $this->assertSame(6.0,  (float) $turnoOrigen->horas_total);
        $this->assertSame('MN', $turnoDestino->refresh()->codigo_turno);
        $this->assertSame(18.0, (float) $turnoDestino->horas_total);
    }

    public function test_portal_bloquea_aprobacion_si_el_receptor_supera_216_horas(): void
    {
        $uci = Uci::create(['nombre' => 'UCI Central', 'codigo' => 'UCI-01']);
        $archivo = $this->crearArchivo();

        $medicoA = Medico::create(['nombre' => 'Ana', 'apellido' => 'A', 'uci_id' => $uci->id, 'activo' => true]);
        $medicoB = Medico::create(['nombre' => 'Beto', 'apellido' => 'B', 'uci_id' => $uci->id, 'activo' => true]);

        // El receptor ya casi al límite mensual.
        $this->crearTurno($archivo, $uci, $medicoB, 'MTN', ['diurnas' => 12, 'nocturnas' => 12, 'total' => 210]);

        $turnoOrigen = $this->crearTurno($archivo, $uci, $medicoA, 'MT', ['diurnas' => 12, 'nocturnas' => 0, 'total' => 12]);

        $solicitud = SolicitudCambioTurno::create([
            'tipo_movimiento'       => 'oferta_abierta',
            'turno_origen_id'       => $turnoOrigen->id,
            'medico_solicitante_id' => $medicoA->id,
            'medico_receptor_id'    => $medicoB->id,
            'motivo'                => 'Ofrezco mi turno',
            'estado'                => 'pendiente_aprobacion_maestro',
        ]);

        $user = $this->crearUsuarioMaster();
        $response = $this->actingAs($user)->post(route('cambios.aprobar', $solicitud));

        $response->assertSessionHas('error');
        $this->assertSame($medicoA->id, $turnoOrigen->refresh()->medico_id, 'El turno no debió reasignarse.');
        $this->assertSame('pendiente_aprobacion_maestro', $solicitud->refresh()->estado);
    }

    public function test_portal_recalcula_el_indicador_de_horas_al_aprobar(): void
    {
        $uci = Uci::create(['nombre' => 'UCI Central', 'codigo' => 'UCI-01']);
        $archivo = $this->crearArchivo();

        $medicoA = Medico::create(['nombre' => 'Ana', 'apellido' => 'A', 'uci_id' => $uci->id, 'activo' => true]);
        $medicoB = Medico::create(['nombre' => 'Beto', 'apellido' => 'B', 'uci_id' => $uci->id, 'activo' => true]);

        $turnoOrigen = $this->crearTurno($archivo, $uci, $medicoA, 'MT', ['diurnas' => 12, 'nocturnas' => 0, 'total' => 12]);

        $solicitud = SolicitudCambioTurno::create([
            'tipo_movimiento'       => 'oferta_abierta',
            'turno_origen_id'       => $turnoOrigen->id,
            'medico_solicitante_id' => $medicoA->id,
            'medico_receptor_id'    => $medicoB->id,
            'motivo'                => 'Ofrezco mi turno',
            'estado'                => 'pendiente_aprobacion_maestro',
        ]);

        $this->assertDatabaseCount('indicador_medicos', 0);

        $user = $this->crearUsuarioMaster();
        $response = $this->actingAs($user)->post(route('cambios.aprobar', $solicitud));

        $response->assertSessionHas('success');
        $this->assertSame($medicoB->id, $turnoOrigen->refresh()->medico_id);

        $this->assertDatabaseHas('indicador_medicos', [
            'medico_id'   => $medicoB->id,
            'uci_id'      => $uci->id,
            'total_horas' => 12,
        ]);
        $this->assertDatabaseHas('indicador_medicos', [
            'medico_id'   => $medicoA->id,
            'uci_id'      => $uci->id,
            'total_horas' => 0,
        ]);
    }
}
