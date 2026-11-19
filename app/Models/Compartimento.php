<?php

namespace App\Models;

use App\Enums\TipoIngrediente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compartimento extends Model
{
    protected $table = 'compartimentos';

    protected $fillable = [
        'codigo',
        'tipo_atual',
        'tipo_definido_em',
        'quantidade_atual',
    ];

    protected function casts(): array
    {
        return [
            'tipo_atual' => TipoIngrediente::class,
            'tipo_definido_em' => 'date',
            'quantidade_atual' => 'decimal:3',
        ];
    }

    public function ingredientes(): HasMany
    {
        return $this->hasMany(Ingrediente::class);
    }

    // public function movimentacoes(): HasMany
    // {
    //     return $this->hasMany(Movimentacao::class);
    // }
}
