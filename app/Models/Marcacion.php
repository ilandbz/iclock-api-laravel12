<?php

namespace App\Models;

use App\Http\Traits\MarcacionTrait;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Marcacion extends Model
{
    use HasFactory, MarcacionTrait;

    protected $table = 'marcaciones';

    protected $fillable = [
        'numero_documento', 'uid', 'fecha', 'estado', 'tipo', 'serial', 'ip'
    ];
}
