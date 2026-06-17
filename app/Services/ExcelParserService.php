<?php

namespace App\Services;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Parser del Excel de turnos UCI — Clínica de Occidente.
 *
 * FORMATO REAL DEL ARCHIVO:
 * - Cada PESTAÑA = un MES  (ej: "Mayo 2026", "Junio 2026")
 * - La hoja "Secuencia 2026" se ignora.
 * - Dentro de cada hoja hay BLOQUES verticales por UCI.
 *   Un bloque empieza cuando col A contiene "UCI", "UCIN" o "TORRE".
 *   Bloque: FilaUCI → FilaLetras → FilaNumeros → FilasMedicos...
 *
 * COMPATIBILIDAD RETROACTIVA:
 * - Si la hoja NO es mensual (nombre sin mes), intenta el formato antiguo:
 *   Fila1=UCI, Fila2=Letras, Fila3=Números, Filas4+=Médicos.
 */
class ExcelParserService
{
    // ── Códigos de turno ──────────────────────────────────────────
    const TURNOS = [
        'M'    => ['diurnas' => 6.0,  'nocturnas' => 0.0,  'total' => 6.0,  'ausencia' => false],
        'T'    => ['diurnas' => 6.0,  'nocturnas' => 0.0,  'total' => 6.0,  'ausencia' => false],
        'MT'   => ['diurnas' => 12.0, 'nocturnas' => 0.0,  'total' => 12.0, 'ausencia' => false],
        'N'    => ['diurnas' => 0.0,  'nocturnas' => 12.0, 'total' => 12.0, 'ausencia' => false],
        'MTN'  => ['diurnas' => 12.0, 'nocturnas' => 12.0, 'total' => 24.0, 'ausencia' => false],
        'MN'   => ['diurnas' => 6.0,  'nocturnas' => 12.0, 'total' => 18.0, 'ausencia' => false],
        'VAC'  => ['diurnas' => 0.0,  'nocturnas' => 0.0,  'total' => 0.0,  'ausencia' => true],
        'PER'  => ['diurnas' => 0.0,  'nocturnas' => 0.0,  'total' => 0.0,  'ausencia' => true],
        'INC'  => ['diurnas' => 0.0,  'nocturnas' => 0.0,  'total' => 0.0,  'ausencia' => true],
        'LIBRE'=> ['diurnas' => 0.0,  'nocturnas' => 0.0,  'total' => 0.0,  'ausencia' => false],
        ''     => ['diurnas' => 0.0,  'nocturnas' => 0.0,  'total' => 0.0,  'ausencia' => false],
    ];

    // MN está importado pero genera alerta de "código no oficial"
    const CODIGOS_NO_OFICIALES = ['MN'];

    const DIA_NOMBRE = [
        0=>'lunes', 1=>'martes', 2=>'miercoles',
        3=>'jueves', 4=>'viernes', 5=>'sabado', 6=>'domingo',
    ];

    // Hojas que se deben ignorar
    const HOJAS_IGNORAR = ['SECUENCIA', 'PLANTILLA', 'FORMATO', 'RESUMEN', 'INSTRUCCIONES', 'HOJA'];

    // Nombres de meses en español → número
    const MESES = [
        'ENERO'=>1,'FEBRERO'=>2,'MARZO'=>3,'ABRIL'=>4,'MAYO'=>5,'JUNIO'=>6,
        'JULIO'=>7,'AGOSTO'=>8,'SEPTIEMBRE'=>9,'OCTUBRE'=>10,'NOVIEMBRE'=>11,'DICIEMBRE'=>12,
    ];

    // Mapeo UCI (la clave más larga que coincida gana; UCIN tiene prioridad absoluta)
    const UCIS_PALABRAS = [
        'CARDIOVASCULAR' => 'UCI-CARDIO',
        'NEUROVASCULAR'  => 'UCI-NEURO',
        'NEUROLOGICA'    => 'UCI-NEURO',
        'NEUROLÓGICA'    => 'UCI-NEURO',
        'NEURO'          => 'UCI-NEURO',
        'TORRE B1'       => 'UCI-B1',
        'TORRE B2'       => 'UCI-B2',
        'TORRE B 1'      => 'UCI-B1',
        'TORRE B 2'      => 'UCI-B2',
        'TORRE C'        => 'UCI-C',
        'RESPIRATORIA'   => 'UCI-RESP',
        'QUIRÚRGICA'     => 'UCI-QUIR',
        'QUIRURGICA'     => 'UCI-QUIR',
        'QUIRU'          => 'UCI-QUIR',
        'GENERAL'        => 'UCI-GEN',
    ];

    private array $errores      = [];
    private array $advertencias = [];
    private array $alertasMtn   = [];
    private array $alertasNoOficial = [];

    // ──────────────────────────────────────────────────────────────
    // PUNTO DE ENTRADA PRINCIPAL
    // ──────────────────────────────────────────────────────────────

    /**
     * Parsea el archivo Excel.
     *
     * @param string   $rutaArchivo  Ruta absoluta al .xlsx
     * @param int|null $mesFiltro    Si se indica, sólo importa ese mes (null = todos)
     * @param int|null $anioFiltro   Año del filtro (requiere mesFiltro)
     *
     * @return array {
     *   'meses'        => ['2026-06' => ['mes'=>6,'anio'=>2026,'nombre_hoja'=>...,'ucis'=>[...]]],
     *   'modo'         => 'multi_mes' | 'hoja_uci' (retrocompatible),
     *   'ucis'         => [...] (solo en modo retrocompatible),
     *   'errores'      => [...],
     *   'advertencias' => [...],
     *   'alertas_mtn'  => [...],
     *   'alertas_no_oficial' => [...],
     * }
     */
    public function parsear(string $rutaArchivo, ?int $mesFiltro = null, ?int $anioFiltro = null): array
    {
        $this->errores           = [];
        $this->advertencias      = [];
        $this->alertasMtn        = [];
        $this->alertasNoOficial  = [];

        try {
            $spreadsheet = IOFactory::load($rutaArchivo);
        } catch (\Exception $e) {
            return $this->resultado(errores: ["No se pudo abrir el archivo: {$e->getMessage()}"]);
        }

        $hojas = $spreadsheet->getSheetNames();
        if (empty($hojas)) {
            return $this->resultado(errores: ['El archivo no contiene hojas.']);
        }

        // Detectar si el archivo usa hojas mensuales o el formato antiguo (hoja=UCI)
        $tieneHojasMensuales = false;
        foreach ($hojas as $nombreHoja) {
            if ($this->detectarMesAnioDeHoja($nombreHoja) !== null) {
                $tieneHojasMensuales = true;
                break;
            }
        }

        if ($tieneHojasMensuales) {
            return $this->parsearFormatoMultiMes($spreadsheet, $hojas, $mesFiltro, $anioFiltro);
        } else {
            // Formato antiguo: una hoja = una UCI
            return $this->parsearFormatoUnaHojaUnaUci($spreadsheet, $hojas, $mesFiltro, $anioFiltro);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // FORMATO NUEVO: Hojas mensuales + bloques UCI dentro de cada hoja
    // ──────────────────────────────────────────────────────────────

    private function parsearFormatoMultiMes(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet,
        array $hojas,
        ?int $mesFiltro,
        ?int $anioFiltro
    ): array {
        $mesesProcesados    = [];
        $hojasNoReconocidas = [];

        foreach ($hojas as $nombreHoja) {
            // Ignorar hojas de secuencia/plantilla
            if ($this->debeIgnorar($nombreHoja)) {
                $this->advertencias[] = "Hoja ignorada: \"{$nombreHoja}\"";
                continue;
            }

            $info = $this->detectarMesAnioDeHoja($nombreHoja);
            if (!$info) {
                $hojasNoReconocidas[] = $nombreHoja;
                continue;
            }

            $mes  = $info['mes'];
            $anio = $info['anio'];

            // Aplicar filtro si se indicó
            if ($mesFiltro && $anioFiltro) {
                if ($mes !== $mesFiltro || $anio !== $anioFiltro) continue;
            }

            $hoja          = $spreadsheet->getSheetByName($nombreHoja);
            $ucisDetectadas = $this->parsearHojaMultiUci($hoja, $mes, $anio, $nombreHoja);

            if (!empty($ucisDetectadas)) {
                $clave = "{$anio}-{$mes}";
                $mesesProcesados[$clave] = [
                    'mes'         => $mes,
                    'anio'        => $anio,
                    'nombre_hoja' => $nombreHoja,
                    'ucis'        => $ucisDetectadas,
                ];
            } else {
                $this->advertencias[] = "Hoja \"{$nombreHoja}\" no tiene bloques UCI válidos.";
            }
        }

        if (!empty($hojasNoReconocidas)) {
            $this->advertencias[] = 'Hojas sin mes reconocido: ' . implode(', ', array_map(fn($h) => '"'.$h.'"', $hojasNoReconocidas));
        }

        return $this->resultado(
            modo: 'multi_mes',
            meses: $mesesProcesados,
        );
    }

    /**
     * Detecta y parsea bloques UCI dentro de UNA hoja mensual.
     * Un bloque empieza cuando col A contiene UCI / UCIN / TORRE.
     */
    private function parsearHojaMultiUci(
        Worksheet $hoja, int $mes, int $anio, string $nombreHoja
    ): array {
        $maxRow    = $hoja->getHighestRow();
        $maxCol    = Coordinate::columnIndexFromString($hoja->getHighestColumn());
        $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

        // 1. Escanear columna A para encontrar cabeceras UCI
        $bloques = [];
        for ($fila = 1; $fila <= min($maxRow, 1000); $fila++) {
            $valorA = $this->celda($hoja, 1, $fila);
            if ($this->esFilaUci($valorA)) {
                $bloques[] = [
                    'fila_uci'  => $fila,
                    'nombre_raw'=> $valorA,
                ];
            }
        }

        if (empty($bloques)) return [];

        $ucisDetectadas = [];

        // 2. Procesar cada bloque
        foreach ($bloques as $idx => $bloque) {
            $filaUci  = $bloque['fila_uci'];
            $nombreRaw = $bloque['nombre_raw'];

            $codigoUci = $this->detectarCodigoUci($nombreRaw);
            if (!$codigoUci) {
                $this->advertencias[] = "UCI no reconocida: \"{$nombreRaw}\" (hoja \"{$nombreHoja}\" fila {$filaUci}).";
                continue;
            }

            // Estructura del bloque:
            //   filaUci+0 = Nombre UCI
            //   filaUci+1 = Letras de días (L,M,M,J,V,S,D...)
            //   filaUci+2 = Números de días (1,2,3,4...)
            //   filaUci+3 = Primer médico
            $filaNumeros      = $filaUci + 2;
            $primerFilaMedico = $filaUci + 3;

            // Fin del bloque = inicio del siguiente bloque o fin de hoja
            $filaFin = isset($bloques[$idx + 1])
                ? $bloques[$idx + 1]['fila_uci'] - 1
                : $maxRow;

            // 3. Construir mapa columna→día
            $mapaColumnas = $this->mapaDesdeFila(
                $hoja, $maxCol, $mes, $anio, $diasEnMes, $filaNumeros
            );

            if (empty($mapaColumnas)) {
                $this->advertencias[] = "Sin columnas de días en bloque \"{$nombreRaw}\" (hoja \"{$nombreHoja}\").";
                continue;
            }

            // 4. Leer médicos
            $medicos = $this->leerMedicosDeBloque(
                $hoja, $primerFilaMedico, $filaFin,
                $mapaColumnas, $nombreRaw, $codigoUci, $nombreHoja
            );

            if (!empty($medicos)) {
                // Si la misma UCI ya fue detectada en esta hoja, fusionar
                if (isset($ucisDetectadas[$codigoUci])) {
                    $ucisDetectadas[$codigoUci]['medicos'] = array_merge(
                        $ucisDetectadas[$codigoUci]['medicos'],
                        $medicos
                    );
                } else {
                    $ucisDetectadas[$codigoUci] = [
                        'nombre_hoja' => $nombreHoja,
                        'codigo'      => $codigoUci,
                        'nombre_raw'  => $this->normalizarNombreUci($nombreRaw),
                        'fila_inicio' => $filaUci,
                        'medicos'     => $medicos,
                    ];
                }
            }
        }

        return $ucisDetectadas;
    }

    /**
     * Lee las filas de médicos dentro de un bloque UCI.
     */
    private function leerMedicosDeBloque(
        Worksheet $hoja,
        int $primerFila, int $filaFin,
        array $mapaColumnas,
        string $nombreUciRaw, string $codigoUci, string $nombreHoja
    ): array {
        $medicos              = [];
        $vaciosConsecutivos   = 0;

        for ($fila = $primerFila; $fila <= $filaFin; $fila++) {
            $valorA = $this->celda($hoja, 1, $fila);

            if ($valorA === '') {
                $vaciosConsecutivos++;
                if ($vaciosConsecutivos >= 8) break;
                continue;
            }
            $vaciosConsecutivos = 0;

            // Si es otra cabecera UCI → fin del bloque
            if ($this->esFilaUci($valorA)) break;

            // Ignorar filas de resumen (total/subtotal/suma/números)
            if ($this->esCabecera($valorA)) continue;
            if (is_numeric(str_replace([' ','.','$'], '', $valorA))) continue;

            // Normalizar nombre del médico
            $nombreNorm = $this->normalizarNombreMedico($valorA);
            if (empty($nombreNorm) || mb_strlen($nombreNorm) < 3) continue;

            // Leer turnos de este médico
            $turnos = [];
            foreach ($mapaColumnas as $col => $info) {
                $rawCell = $this->celda($hoja, $col, $fila);
                $codigo  = strtoupper(preg_replace('/[^A-Za-z]/', '', $rawCell));

                $esCodNoOficial = false;
                $observacion    = '';

                if ($codigo === '') {
                    $horas = self::TURNOS[''];
                } elseif ($codigo === 'LIBRE') {
                    $horas  = self::TURNOS[''];
                    $codigo = '';
                } elseif (isset(self::TURNOS[$codigo])) {
                    $horas = self::TURNOS[$codigo];

                    // MN: válido pero marcado como no oficial
                    if (in_array($codigo, self::CODIGOS_NO_OFICIALES)) {
                        $esCodNoOficial = true;
                        $observacion    = "Código '{$codigo}' no parametrizado oficialmente.";
                        $this->alertasNoOficial[] = [
                            'hoja'    => $nombreHoja,
                            'uci'     => $codigoUci,
                            'medico'  => $nombreNorm,
                            'fecha'   => $info['fecha'],
                            'codigo'  => $codigo,
                            'mensaje' => "El médico {$nombreNorm} tiene el código no oficial '{$codigo}' el {$info['fecha']} en {$nombreUciRaw}.",
                        ];
                    }

                    // MTN solo sábado o domingo
                    if ($codigo === 'MTN' && !$info['es_fin_semana']) {
                        $observacion = "MTN en día hábil.";
                        $this->alertasMtn[] = [
                            'hoja'    => $nombreHoja,
                            'uci'     => $codigoUci,
                            'medico'  => $nombreNorm,
                            'fecha'   => $info['fecha'],
                            'mensaje' => "El médico {$nombreNorm} tiene turno MTN asignado en día hábil para la fecha {$info['fecha']}. MTN solo está permitido sábado o domingo.",
                        ];
                    }
                } else {
                    $observacion = "Código desconocido: '{$codigo}'.";
                    $this->advertencias[] = "Código desconocido '{$codigo}' — médico '{$nombreNorm}', día {$info['dia']} ({$nombreHoja}).";
                    $horas  = self::TURNOS[''];
                    $codigo = $codigo; // conservar para auditoría
                }

                $turnos[] = [
                    'dia'                => $info['dia'],
                    'fecha'              => $info['fecha'],
                    'dia_semana'         => $info['dia_semana'],
                    'codigo_turno'       => $codigo,
                    'horas_diurnas'      => $horas['diurnas'],
                    'horas_nocturnas'    => $horas['nocturnas'],
                    'horas_total'        => $horas['total'],
                    'es_fin_semana'      => $info['es_fin_semana'],
                    'es_domingo'         => $info['es_domingo'],
                    'fila_excel'         => $fila,
                    'columna_excel'      => $col,
                    'es_codigo_no_oficial' => $esCodNoOficial,
                    'observacion'        => $observacion,
                ];
            }

            $medicos[$nombreNorm] = $turnos;
        }

        return $medicos;
    }

    // ──────────────────────────────────────────────────────────────
    // FORMATO ANTIGUO RETROCOMPATIBLE: cada hoja = una UCI
    // ──────────────────────────────────────────────────────────────

    private function parsearFormatoUnaHojaUnaUci(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet,
        array $hojas,
        ?int $mesForzado,
        ?int $anioForzado
    ): array {
        if (!$mesForzado || !$anioForzado) {
            return $this->resultado(errores: [
                'Este archivo usa el formato antiguo (una hoja por UCI). Indique mes y año.'
            ]);
        }

        $ucis = [];
        $hojasNoReconocidas = [];

        foreach ($hojas as $nombreHoja) {
            if ($this->debeIgnorar($nombreHoja)) continue;

            $hoja = $spreadsheet->getSheetByName($nombreHoja);

            $nombreSalaF1 = $this->celda($hoja, 1, 1);
            $codigoUci    = $this->detectarCodigoUci($nombreSalaF1)
                         ?? $this->detectarCodigoUci($nombreHoja);

            if (!$codigoUci) {
                $hojasNoReconocidas[] = "\"{$nombreHoja}\" (F1: \"{$nombreSalaF1}\")";
                continue;
            }

            $maxRow    = $hoja->getHighestRow();
            $maxCol    = Coordinate::columnIndexFromString($hoja->getHighestColumn());
            $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mesForzado, $anioForzado);

            // Días en fila 3 (o fallback fila 2)
            $mapa = $this->construirMapaColumnas($hoja, $maxCol, $mesForzado, $anioForzado, $diasEnMes);
            if (empty($mapa)) { $this->advertencias[] = "Sin días en \"{$nombreHoja}\"."; continue; }

            $medicos = $this->leerMedicosDeBloque(
                $hoja, 4, $maxRow, $mapa, $nombreSalaF1 ?: $nombreHoja, $codigoUci, $nombreHoja
            );

            if (!empty($medicos)) {
                $ucis[$codigoUci] = [
                    'nombre_hoja' => $nombreHoja,
                    'codigo'      => $codigoUci,
                    'medicos'     => $medicos,
                ];
            }
        }

        if (!empty($hojasNoReconocidas)) {
            $this->advertencias[] = 'Hojas no reconocidas: ' . implode(', ', $hojasNoReconocidas);
        }

        return $this->resultado(
            modo: 'hoja_uci',
            ucis: $ucis,
        );
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────

    private function construirMapaColumnas(
        Worksheet $hoja, int $maxCol, int $mes, int $anio, int $diasEnMes
    ): array {
        $mapa = $this->mapaDesdeFila($hoja, $maxCol, $mes, $anio, $diasEnMes, 3);
        if (empty($mapa)) {
            $mapa = $this->mapaDesdeFila($hoja, $maxCol, $mes, $anio, $diasEnMes, 2);
        }
        return $mapa;
    }

    private function mapaDesdeFila(
        Worksheet $hoja, int $maxCol, int $mes, int $anio, int $diasEnMes, int $filaNumeros
    ): array {
        $mapa       = [];
        $diasVistos = [];

        for ($col = 2; $col <= $maxCol; $col++) {
            $valor = $this->celda($hoja, $col, $filaNumeros);
            if (!is_numeric($valor)) continue;

            $dia = (int)$valor;
            if ($dia < 1 || $dia > $diasEnMes) continue;
            if (isset($diasVistos[$dia])) continue;

            $diasVistos[$dia] = true;
            $fecha            = Carbon::create($anio, $mes, $dia);
            $dow              = $fecha->dayOfWeek;           // 0=Dom … 6=Sab
            $idx              = ($dow === 0) ? 6 : $dow - 1; // 0=Lun … 6=Dom

            $mapa[$col] = [
                'dia'          => $dia,
                'fecha'        => $fecha->toDateString(),
                'dia_semana'   => self::DIA_NOMBRE[$idx],
                'es_fin_semana'=> in_array($dow, [0, 6]),
                'es_domingo'   => ($dow === 0),
            ];
        }

        return $mapa;
    }

    private function detectarMesAnioDeHoja(string $nombreHoja): ?array
    {
        $upper = strtoupper(trim($nombreHoja));
        foreach (self::MESES as $nombre => $num) {
            if (str_contains($upper, $nombre)) {
                preg_match('/(\d{4})/', $nombreHoja, $match);
                if ($match && (int)$match[1] >= 2020 && (int)$match[1] <= 2035) {
                    return ['mes' => $num, 'anio' => (int)$match[1]];
                }
            }
        }
        return null;
    }

    private function debeIgnorar(string $nombreHoja): bool
    {
        $upper = strtoupper(trim($nombreHoja));
        foreach (self::HOJAS_IGNORAR as $patron) {
            if (str_contains($upper, $patron)) return true;
        }
        return false;
    }

    private function esFilaUci(string $texto): bool
    {
        return !empty($texto) && (bool)preg_match('/UCI|UCIN|TORRE/i', $texto);
    }

    private function detectarCodigoUci(?string $nombre): ?string
    {
        if (empty($nombre)) return null;

        $norm = $this->normalizar($nombre);

        // UCIN tiene prioridad absoluta
        if (str_contains($norm, 'UCIN') || str_contains($norm, 'NEONATAL')) {
            return 'UCIN';
        }

        $mejorCodigo   = null;
        $mejorLongitud = 0;

        foreach (self::UCIS_PALABRAS as $clave => $codigo) {
            if (str_contains($norm, $this->normalizar($clave))) {
                if (strlen($clave) > $mejorLongitud) {
                    $mejorLongitud = strlen($clave);
                    $mejorCodigo   = $codigo;
                }
            }
        }

        return $mejorCodigo;
    }

    private function normalizarNombreUci(string $texto): string
    {
        // Quitar espacios dobles, limpiar
        return preg_replace('/\s+/', ' ', trim($texto));
    }

    private function normalizarNombreMedico(string $texto): string
    {
        // Quitar espacios múltiples, trim, conservar tildes
        $nombre = preg_replace('/\s+/', ' ', trim($texto));
        return mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8');
    }

    private function esCabecera(string $texto): bool
    {
        $upper = $this->normalizar($texto);
        foreach (['NOMBRE', 'MEDICO', 'DOCTOR', 'ESPECIALISTA', 'TOTAL', 'SUBTOTAL', 'RESUMEN', 'SUMA', 'PROMEDIO'] as $p) {
            if (str_contains($upper, $p)) return true;
        }
        return false;
    }

    private function celda(Worksheet $hoja, int $col, int $fila): string
    {
        try {
            return trim((string)$hoja->getCell([$col, $fila])->getValue());
        } catch (\Throwable) {
            return '';
        }
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtoupper(trim($texto));
        return str_replace(
            ['Á','É','Í','Ó','Ú','Ü','Ñ','á','é','í','ó','ú','ü','ñ'],
            ['A','E','I','O','U','U','N','A','E','I','O','U','U','N'],
            $texto
        );
    }

    private function resultado(
        string $modo = 'hoja_uci',
        array  $meses  = [],
        array  $ucis   = [],
        array  $errores = [],
    ): array {
        return [
            'modo'               => $modo,
            'meses'              => $meses,
            'ucis'               => $ucis,   // solo en modo hoja_uci
            'errores'            => array_merge($this->errores, $errores),
            'advertencias'       => $this->advertencias,
            'alertas_mtn'        => $this->alertasMtn,
            'alertas_no_oficial' => $this->alertasNoOficial,
        ];
    }

    public function getErrores(): array           { return $this->errores; }
    public function getAdvertencias(): array      { return $this->advertencias; }
    public function getAlertasMtn(): array        { return $this->alertasMtn; }
    public function getAlertasNoOficial(): array  { return $this->alertasNoOficial; }
}
