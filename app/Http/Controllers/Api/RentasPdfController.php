<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lote;
use App\Services\RentasPdfService;
use Illuminate\Http\Response;

class RentasPdfController extends Controller
{
    public function individual(Lote $lote, RentasPdfService $pdf): Response
    {
        return $this->responder($lote, $pdf, 'individual');
    }

    public function archivoRentas(Lote $lote, RentasPdfService $pdf): Response
    {
        return $this->responder($lote, $pdf, 'archivo-rentas');
    }

    public function predioUrbano(Lote $lote, RentasPdfService $pdf): Response
    {
        return $this->responder($lote, $pdf, 'predio-urbano');
    }

    private function responder(Lote $lote, RentasPdfService $pdf, string $tipo): Response
    {
        $nombre = $tipo.'-'.preg_replace('/[^a-zA-Z0-9_-]/', '_', $lote->id_lote).'.pdf';

        return response($pdf->generar($lote, $tipo), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
