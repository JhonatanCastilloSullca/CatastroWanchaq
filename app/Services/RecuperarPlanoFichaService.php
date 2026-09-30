<?php

namespace App\Services;

use App\Models\Ficha;
use App\Models\FichaIndividual;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RecuperarPlanoFichaService
{
    private ?string $ultimoLote = null;
    private ?string $ultimaImagen = null;

    public function existe(?string $nombre): bool
    {
        return filled($nombre) && $nombre !== 'imagen_plano.png'
            && basename(str_replace('\\', '/', $nombre)) === $nombre
            && !str_contains($nombre, "\0")
            && Storage::disk('public')->exists('img/imagenesplanos/'.$nombre)
            && Storage::disk('public')->size('img/imagenesplanos/'.$nombre) > 0;
    }

    public function recuperar(string $idFicha, string $idLote): string
    {
        abort_unless(preg_match('/^[a-zA-Z0-9_-]+$/D', $idFicha), 422, 'Identificador de ficha inválido.');
        $individual = FichaIndividual::findOrFail($idFicha);
        if ($this->existe($individual->imagen_plano)) {
            return 'omitida';
        }
        $nombre = $idFicha.'.png';
        $imagen = $this->existe($nombre) ? null : $this->imagenLote($idLote);

        // No mantener bloqueos de base mientras responde el WMS.
        return DB::transaction(function () use ($idFicha, $idLote, $nombre, $imagen) {
            $ficha = Ficha::where('id_ficha', $idFicha)->lockForUpdate()->firstOrFail();
            if ((string) $ficha->id_lote !== $idLote || trim($ficha->tipo_ficha) !== '01') {
                throw new \RuntimeException('La ficha cambió de lote o tipo durante la recuperación.');
            }
            $individual = FichaIndividual::where('id_ficha', $idFicha)->lockForUpdate()->firstOrFail();
            if ($this->existe($individual->imagen_plano)) {
                return 'omitida';
            }
            $disk = Storage::disk('public');
            $ruta = 'img/imagenesplanos/'.$nombre;
            if (! $this->existe($nombre)) {
                if ($imagen === null) {
                    throw new \RuntimeException('El archivo cambió durante la recuperación. Reintente la ficha.');
                }
                $temporal = $ruta.'.'.Str::uuid().'.tmp';
                try {
                    if (! $disk->put($temporal, $imagen) || ! $disk->move($temporal, $ruta)) {
                        throw new \RuntimeException('No se pudo guardar la imagen en el disco public.');
                    }
                } finally {
                    $disk->delete($temporal);
                }
            }
            $individual->imagen_plano = $nombre;
            $individual->save();
            return 'recuperada';
        });
    }

    private function imagenLote(string $idLote): string
    {
        if ($this->ultimoLote === $idLote && $this->ultimaImagen !== null) {
            return $this->ultimaImagen;
        }
        $base = rtrim((string) config('planos.url_map'), '/');
        if (!preg_match('~^https?://~i', $base)) {
            throw new \RuntimeException('Configure URL_MAP con la dirección http o https del servidor de mapas.');
        }
        $extension = DB::connection('pgsqlgeo')->selectOne("
            SELECT ST_XMin(extent) AS xmin, ST_YMin(extent) AS ymin,
                   ST_XMax(extent) AS xmax, ST_YMax(extent) AS ymax
            FROM (SELECT ST_Expand(ST_Extent(geom), 5) AS extent
                  FROM geo.tg_lote WHERE id_lote = ?) AS limites
        ", [$idLote]);
        $valores = [$extension?->xmin, $extension?->ymin, $extension?->xmax, $extension?->ymax];
        foreach ($valores as $valor) {
            if (!is_numeric($valor) || !is_finite((float) $valor)) {
                throw new \RuntimeException('El lote no tiene una extensión geográfica válida.');
            }
        }
        if ($valores[0] >= $valores[2] || $valores[1] >= $valores[3]) {
            throw new \RuntimeException('La extensión geográfica del lote está vacía.');
        }
        $response = Http::connectTimeout(10)->timeout(45)->retry(2, 1000)->get($base.'/servicio/wms', [
            'service' => 'WMS', 'request' => 'getMap', 'version' => '1.1.1',
            'layers' => 'lote,id_lote,vertice_lote,eje_via', 'styles' => '',
            'WIDTH' => 1680, 'HEIGHT' => 834, 'SRS' => 'EPSG:32719',
            'BBOX' => implode(',', $valores), 'format' => 'image/png', 'id' => $idLote,
        ]);
        $response->throw();
        $contenido = $response->body();
        $info = @getimagesizefromstring($contenido);
        if (!str_starts_with($contenido, "\x89PNG\r\n\x1a\n") || !$info || $info[2] !== IMAGETYPE_PNG) {
            throw new \RuntimeException('El servicio de mapas no devolvió una imagen PNG válida.');
        }
        $this->ultimoLote = $idLote;
        return $this->ultimaImagen = $contenido;
    }
}
