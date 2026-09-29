<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ficha;
use App\Models\Lote;
use App\Models\Institucion;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

class LoteEnlacesController extends Controller
{
    private const ARCHIVOS = ['imagen1', 'imagen2', 'imagen3', 'rentas', 'sunarp', 'plano'];

    public function index(Lote $lote)
    {
        $salida = ['individuales' => [], 'cotitulares' => [], 'economicas' => [], 'pu' => [], 'archivos' => []];
        $fichas = Ficha::where('id_lote', $lote->id_lote)->where('activo', '1')
            ->whereIn(\DB::raw('TRIM(tipo_ficha)'), ['01', '02', '03'])
            ->with('archivos')->orderBy('id_uni_cat')->orderBy('id_ficha')->get();
        foreach ($fichas as $ficha) {
            $tipo = trim($ficha->tipo_ficha);
            $params = ['lote' => $lote->id_lote, 'ficha' => $ficha->id_ficha];
            if ($tipo === '01') {
                $salida['individuales'][] = route('api.rentas.ficha.pdf', $params + ['documento' => 'ficha-individual']);
                $salida['pu'][] = route('api.rentas.ficha.pdf', $params + ['documento' => 'predio-urbano']);
            } else {
                $salida[$tipo === '02' ? 'cotitulares' : 'economicas'][] = route('api.rentas.ficha.asociada', $params);
            }
            foreach ($ficha->archivos as $archivo) {
                foreach (self::ARCHIVOS as $campo) {
                    if (filled($archivo->$campo)) {
                        $salida['archivos'][] = route('api.rentas.ficha.adjunto', $params + ['archivo' => $archivo->id, 'campo' => $campo]);
                    }
                }
            }
        }
        return response()->json($salida)->header('Cache-Control', 'private, no-store');
    }

    public function ficha(Lote $lote, string $ficha)
    {
        $ficha = Ficha::where('id_lote', $lote->id_lote)->where('id_ficha', $ficha)->where('activo', '1')
            ->whereIn(\DB::raw('TRIM(tipo_ficha)'), ['02', '03'])->firstOrFail();
        $vista = trim($ficha->tipo_ficha) === '02' ? 'cotitularidad' : 'economica';
        $logos = Institucion::first();
        $pdf = new Mpdf(['format' => 'A4', 'margin_left' => 10, 'margin_right' => 10,
            'margin_top' => 10, 'margin_bottom' => 10, 'tempDir' => storage_path('app/mpdf-temp')]);
        $pdf->WriteHTML(view('pages.pdf.'.$vista, compact('ficha', 'logos'))->render());
        return response($pdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$vista.'.pdf"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function archivo(Lote $lote, string $ficha, string $archivo, string $campo)
    {
        abort_unless(in_array($campo, self::ARCHIVOS, true), 404);
        $ficha = Ficha::where('id_lote', $lote->id_lote)->where('id_ficha', $ficha)->where('activo', '1')
            ->whereIn(\DB::raw('TRIM(tipo_ficha)'), ['01', '02', '03'])->firstOrFail();
        $registro = $ficha->archivos()->where('id', $archivo)->firstOrFail();
        $nombre = $registro->$campo;
        abort_unless(is_string($nombre) && $nombre !== '' && !str_contains($nombre, "\0")
            && basename(str_replace('\\', '/', $nombre)) === $nombre, 404);
        $ruta = 'img/archivos/'.$nombre;
        abort_unless(Storage::exists($ruta), 404, 'El archivo no se encuentra disponible.');
        return Storage::download($ruta, $nombre, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
