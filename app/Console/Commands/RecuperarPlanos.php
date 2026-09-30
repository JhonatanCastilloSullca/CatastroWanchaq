<?php

namespace App\Console\Commands;

use App\Services\RecuperarPlanoFichaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecuperarPlanos extends Command
{
    protected $signature = 'planos:recuperar
        {--aplicar : Generar archivos y actualizar imagen_plano; sin esta opción solo se revisa}
        {--limite=0 : Máximo de planos faltantes a revisar o recuperar; 0 recorre todos}
        {--lote= : Revisar solo este id_lote}
        {--ficha= : Revisar solo este id_ficha}';

    protected $description = 'Recupera planos faltantes de fichas individuales desde el WMS, omitiendo archivos existentes';

    public function handle(RecuperarPlanoFichaService $planos): int
    {
        if (!ctype_digit((string) $this->option('limite'))) {
            $this->error('El límite debe ser un entero mayor o igual a cero.');
            return self::FAILURE;
        }
        $aplicar = (bool) $this->option('aplicar');
        $lock = null;
        if ($aplicar) {
            $lock = fopen(storage_path('app/recuperar-planos.lock'), 'c');
            if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
                if (is_resource($lock)) {
                    fclose($lock);
                }
                $this->error('Ya hay una recuperación en ejecución o no se pudo obtener el bloqueo.');
                return self::FAILURE;
            }
        }
        $contadores = ['revisadas'=>0, 'existentes'=>0, 'faltantes'=>0, 'recuperadas'=>0, 'errores'=>0];
        try {
            $this->info($aplicar ? 'Recuperando solo planos faltantes. Puede reanudar ejecutando el mismo comando.' : 'VISTA PREVIA: no se consultará el WMS ni se modificarán archivos o fichas.');
            $query = DB::table('tf_fichas as f')->join('tf_fichas_individuales as i', 'i.id_ficha', '=', 'f.id_ficha')
                ->whereRaw('TRIM(f.tipo_ficha) = ?', ['01'])
                ->select('f.id_ficha', 'f.id_lote', 'i.imagen_plano')
                ->orderBy('f.id_lote')->orderBy('f.id_ficha');
            if ($this->option('lote')) {
                $query->where('f.id_lote', $this->option('lote'));
            }
            if ($this->option('ficha')) {
                $query->where('f.id_ficha', $this->option('ficha'));
            }
            $consecutivos = 0;
            foreach ($query->lazy(500) as $fila) {
                $contadores['revisadas']++;
                if ($planos->existe($fila->imagen_plano)) {
                    $contadores['existentes']++;
                    continue;
                }
                $contadores['faltantes']++;
                if ($aplicar) {
                    try {
                        $estado = $planos->recuperar((string) $fila->id_ficha, (string) $fila->id_lote);
                        $contadores[$estado === 'recuperada' ? 'recuperadas' : 'existentes']++;
                        $consecutivos = 0;
                    } catch (\Throwable $e) {
                        $contadores['errores']++;
                        $consecutivos++;
                        Log::warning('Recuperación de plano fallida', ['id_ficha'=>$fila->id_ficha, 'id_lote'=>$fila->id_lote, 'error'=>$e->getMessage()]);
                        $this->warn("Ficha {$fila->id_ficha}: ".$e->getMessage());
                    }
                }
                if ($contadores['faltantes'] % 100 === 0) {
                    $this->line('Avance: '.json_encode($contadores));
                }
                if ($consecutivos >= 10) {
                    $this->error('Detenido tras 10 fallos consecutivos. Revise conexión, geometrías y servicio de mapas.');
                    break;
                }
                if ((int) $this->option('limite') > 0 && $contadores['faltantes'] >= (int) $this->option('limite')) {
                    break;
                }
            }
            $this->table(array_keys($contadores), [array_values($contadores)]);
            if ($aplicar) {
                Log::info('Recuperación de planos terminada', $contadores);
            }
            return $contadores['errores'] > 0 ? self::FAILURE : self::SUCCESS;
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
}
