<?php

namespace App\Http\Controllers;

use App\Models\Uci;
use App\Models\ConfiguracionCoberturaUci;
use App\Models\TipoTurno;
use App\Models\AuditoriaSistema;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    public function index()
    {
        $ucis       = Uci::where('activa', true)->orderBy('nombre')->get();
        $tiposTurno = TipoTurno::where('activo', true)->orderBy('codigo')->get();

        $configs = [];
        foreach ($ucis as $uci) {
            $configs[$uci->id] = ConfiguracionCoberturaUci::paraUci($uci->id);
        }

        return view('configuracion.index', compact('ucis', 'configs', 'tiposTurno'));
    }

    public function actualizarCobertura(Request $request, Uci $uci)
    {
        $data = $request->validate([
            'min_medicos_manana'      => 'required|integer|min:0',
            'min_medicos_tarde'       => 'required|integer|min:0',
            'min_medicos_noche'       => 'required|integer|min:0',
            'min_medicos_finde'       => 'required|integer|min:0',
            'horas_minimas_mensual'   => 'required|numeric|min:0',
            'horas_maximas_mensual'   => 'required|numeric|min:0',
            'horas_maximas_semanales' => 'required|numeric|min:0',
            'permite_mtn'             => 'nullable|boolean',
        ]);

        $data['permite_mtn'] = $request->boolean('permite_mtn');

        $config = ConfiguracionCoberturaUci::updateOrCreate(
            ['uci_id' => $uci->id],
            $data
        );

        AuditoriaSistema::registrar('ACTUALIZAR_CONFIG', 'configuracion', 'ConfiguracionCoberturaUci', $config->id,
            null, $data, "Configuración de cobertura actualizada para {$uci->nombre}");

        return back()->with('success', "Configuración de {$uci->nombre} actualizada.");
    }
}
