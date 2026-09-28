<?php

namespace App\Http\Controllers;

use App\Services\ReporteFichasSectorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteFichasSectorController extends Controller
{
    public function index(Request $request, ReporteFichasSectorService $reporte)
    {
        $datos = $request->validate([
            'sector' => ['nullable', 'string', 'max:20', 'required_with:manzana,lote'],
            'manzana' => ['nullable', 'string', 'max:30', 'required_with:lote'],
            'lote' => ['nullable', 'string', 'max:40'],
        ]);

        $sectores = DB::table('catastro.tf_sectores')
            ->select('id_sector', 'codi_sector')->orderBy('codi_sector')->get();
        $sector = $manzana = $lote = $fichas = null;
        $filas = collect();

        if (!empty($datos['sector'])) {
            $sector = $sectores->firstWhere('id_sector', $datos['sector']);
            abort_unless($sector, 404);

            if (!empty($datos['manzana'])) {
                $manzana = DB::table('catastro.tf_manzanas')
                    ->where('id_sector', $sector->id_sector)
                    ->where('id_mzna', $datos['manzana'])->first();
                abort_unless($manzana, 404);
            }

            if (!empty($datos['lote'])) {
                $lote = DB::table('catastro.tf_lotes')
                    ->where('id_mzna', $manzana->id_mzna)
                    ->where('id_lote', $datos['lote'])->first();
                abort_unless($lote, 404);
                $fichas = $reporte->fichas($sector->id_sector)
                    ->where('f.id_lote', $lote->id_lote)
                    ->select('f.id_ficha', 'f.nume_ficha', 'f.tipo_ficha', 'f.id_uni_cat')
                    ->orderBy('f.tipo_ficha')->orderBy('f.nume_ficha')->orderBy('f.id_ficha')
                    ->paginate(50)->withQueryString();
            } else {
                $filas = $reporte->resumen($sector->id_sector, $manzana->id_mzna ?? null);
            }
        }

        $tipos = ReporteFichasSectorService::TIPOS;

        return view('pages.reporte.fichas-sector', compact(
            'sectores', 'sector', 'manzana', 'lote', 'filas', 'fichas', 'tipos'
        ));
    }
}
