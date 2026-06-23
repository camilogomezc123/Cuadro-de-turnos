<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaSistema;
use Illuminate\Http\Request;

class HistorialController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditoriaSistema::orderByDesc('created_at');

        if ($request->filled('modulo')) {
            $query->where('modulo', $request->modulo);
        }
        if ($request->filled('usuario')) {
            $query->where('usuario', 'like', '%' . $request->usuario . '%');
        }
        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->hasta);
        }
        if ($request->filled('accion')) {
            $query->where('accion', 'like', '%' . $request->accion . '%');
        }

        $registros = $query->paginate(50)->withQueryString();

        $modulos = AuditoriaSistema::distinct()->orderBy('modulo')->pluck('modulo')->filter();

        return view('historial.index', compact('registros', 'modulos'));
    }
}