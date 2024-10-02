<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class EstadoElemento extends Authenticatable implements AuditableContract
{
    use HasFactory, Notifiable, Auditable;
    public $timestamps = false;
    protected $table = 'tf_estado_elemento';
    protected $fillable = [
        'cimientos',
        'muros',
        'pisos',
        'techos',
        'pilastras',
        'revestimiento',
        'balcones',
        'puertas',
        'ventanas',
        'rejas',
        'otros',
    ];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id_ficha';

    public function getRouteKeyName()
    {
        return 'id_ficha';
    }

    public function getKeyName()
    {
        return 'id_ficha';
    }
}
