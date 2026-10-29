<?php

namespace Tests\Feature;

use App\Models\ArchivoCargado;
use App\Models\Medico;
use App\Models\TurnoMedico;
use App\Models\Uci;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurnosApiTest extends TestCase
{
    use RefreshDatabase;

    private string $keyFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keyFile = tempnam(sys_get_temp_dir(), 'turnos-api-test-');
        file_put_contents($this->keyFile, json_encode(['test' => hash('sha256', 'test-secret')]));
        config(['turnos_api.key_file' => $this->keyFile]);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-15 06:00', 'America/Bogota'));
    }

    protected function tearDown(): void
    {
        if (isset($this->keyFile)) unlink($this->keyFile);
        parent::tearDown();
    }

    private function shift(ArchivoCargado $file, Uci $uci, Medico $doctor, string $date, string $code, array $extra = []): TurnoMedico
    {
        return TurnoMedico::create(array_merge([
            'archivo_id' => $file->id, 'uci_id' => $uci->id, 'medico_id' => $doctor->id,
            'fecha' => $date, 'dia_numero' => (int) substr($date, -2), 'dia_semana' => 'jueves',
            'codigo_turno' => $code, 'fue_laborado' => true, 'estado_turno' => 'programado',
        ], $extra));
    }

    private function file(int $month = 10): ArchivoCargado
    {
        return ArchivoCargado::create(['nombre_archivo' => 'fixture', 'ruta' => 'fixture', 'mes' => $month, 'anio' => 2026, 'procesado' => true]);
    }

    public function test_auth_validation_and_missing_programming(): void
    {
        $this->get('/api/v1/turnos')->assertStatus(401)->assertJsonStructure(['message']);
        $this->withToken('wrong')->get('/api/v1/turnos')->assertStatus(401);
        $this->withToken('test-secret')->get('/api/v1/turnos?fecha=2026-02-30')->assertStatus(422);
        $this->withToken('test-secret')->get('/api/v1/turnos?uci=UNKNOWN')->assertStatus(404);
        $this->withToken('test-secret')->get('/api/v1/turnos')->assertOk()
            ->assertJsonPath('fecha', '2026-10-15')->assertJsonPath('programacion_cargada', false);
        file_put_contents($this->keyFile, '{}');
        $this->withToken('test-secret')->get('/api/v1/turnos')->assertStatus(503);
    }

    public function test_current_programming_night_overlap_split_shifts_and_privacy(): void
    {
        $uci = Uci::create(['nombre' => 'Central', 'codigo' => 'C', 'activa' => true]);
        Uci::create(['nombre' => 'Vacía', 'codigo' => 'V', 'activa' => true]);
        Uci::create(['nombre' => 'Inactiva', 'codigo' => 'I', 'activa' => false]);
        $doctor = Medico::create(['nombre' => 'Ana', 'apellido' => 'Gómez', 'email' => 'private@example.com', 'documento' => 'SECRET', 'uci_id' => $uci->id]);
        $old = $this->file(9);
        $this->shift($old, $uci, $doctor, '2026-10-15', 'MTN');
        $file = $this->file();
        $this->shift($file, $uci, $doctor, '2026-10-14', 'N');
        $this->shift($file, $uci, $doctor, '2026-10-15', 'MN');
        $this->shift($file, $uci, $doctor, '2026-10-15', 'T', ['estado_turno' => 'cancelado']);
        $this->shift($file, $uci, $doctor, '2026-10-15', 'M', ['fue_laborado' => false]);
        $this->shift($file, $uci, $doctor, '2026-10-15', 'LIBRE');
        $response = $this->withToken('test-secret')->get('/api/v1/turnos');
        $response->assertOk()->assertJsonCount(2, 'ucis')->assertJsonCount(3, 'ucis.0.turnos')
            ->assertJsonPath('ucis.0.turnos.0.inicio', '2026-10-14T19:00:00-05:00')
            ->assertJsonPath('ucis.0.turnos.0.fin', '2026-10-15T07:00:00-05:00')
            ->assertJsonPath('ucis.0.turnos.1.fin', '2026-10-15T13:00:00-05:00')
            ->assertJsonPath('ucis.0.turnos.2.inicio', '2026-10-15T19:00:00-05:00')
            ->assertJsonPath('ucis.0.turnos.2.fin', '2026-10-16T07:00:00-05:00')
            ->assertJsonPath('ucis.1.estado', 'sin_turnos_registrados');
        $this->assertStringNotContainsString('private@example.com', $response->getContent());
        $this->assertStringNotContainsString('SECRET', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->withToken('test-secret')->get('/api/v1/turnos?uci=V')->assertOk()->assertJsonCount(1, 'ucis');
    }

    public function test_month_boundary_and_tn_are_included(): void
    {
        $uci = Uci::create(['nombre' => 'Central', 'codigo' => 'C', 'activa' => true]);
        $doctor = Medico::create(['nombre' => 'Ana', 'uci_id' => $uci->id]);
        $this->shift($this->file(9), $uci, $doctor, '2026-09-30', 'TN');
        $this->shift($this->file(10), $uci, $doctor, '2026-10-01', 'M');
        $this->withToken('test-secret')->get('/api/v1/turnos?fecha=2026-10-01')->assertOk()
            ->assertJsonCount(2, 'ucis.0.turnos')
            ->assertJsonPath('ucis.0.turnos.0.inicio', '2026-09-30T13:00:00-05:00');
    }

    public function test_updated_assignments_are_returned_without_stale_cache(): void
    {
        $uci = Uci::create(['nombre' => 'Central', 'codigo' => 'C', 'activa' => true]);
        $original = Medico::create(['nombre' => 'Original', 'uci_id' => $uci->id]);
        $replacement = Medico::create(['nombre' => 'Receptor', 'uci_id' => $uci->id]);
        $file = $this->file();
        $shift = $this->shift($file, $uci, $original, '2026-10-15', 'MT');
        $this->withToken('test-secret')->get('/api/v1/turnos')->assertJsonPath('ucis.0.turnos.0.medico', 'Original');
        // Estado persistido por una cesión aprobada: donante conserva M; receptor recibe T.
        $shift->update(['codigo_turno' => 'M']);
        $this->shift($file, $uci, $replacement, '2026-10-15', 'T');
        $this->withToken('test-secret')->get('/api/v1/turnos')->assertOk()
            ->assertJsonCount(2, 'ucis.0.turnos')
            ->assertJsonPath('ucis.0.turnos.0.codigo_turno', 'M')
            ->assertJsonPath('ucis.0.turnos.1.medico', 'Receptor');
    }

    public function test_key_creation_rotation_and_revocation(): void
    {
        $output = $this->keyFile.'-secret';
        try {
            $this->artisan('turnos:api-key', ['nombre' => 'gpt', '--output' => $output])->assertSuccessful();
            $first = trim(file_get_contents($output));
            $this->assertStringNotContainsString($first, file_get_contents($this->keyFile));
            $this->withToken($first)->get('/api/v1/turnos')->assertOk();
            unlink($output);
            $this->artisan('turnos:api-key', ['nombre' => 'gpt', '--output' => $output])->assertSuccessful();
            $this->withToken($first)->get('/api/v1/turnos')->assertStatus(401);
            $this->artisan('turnos:api-key', ['nombre' => 'gpt', '--revoke' => true])->assertSuccessful();
            $this->withToken(trim(file_get_contents($output)))->get('/api/v1/turnos')->assertStatus(401);
        } finally {
            if (is_file($output)) unlink($output);
        }
    }

    public function test_rate_limit_is_enforced_without_authentication(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->get('/api/v1/turnos', ['REMOTE_ADDR' => '192.0.2.10'])->assertStatus(401);
        }
        $this->get('/api/v1/turnos', ['REMOTE_ADDR' => '192.0.2.10'])->assertStatus(429);
    }
}
