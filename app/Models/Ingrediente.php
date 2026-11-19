<?php

namespace App\Models;

use App\Enums\TipoIngrediente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingrediente extends Model
{
    protected $table = 'ingredientes';

    protected $fillable = [
        'compartimento_id',
        'nome',
        'tipo',
        'quantidade',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoIngrediente::class,
            'quantidade' => 'decimal:3',
        ];
    }

    public function compartimento(): BelongsTo
    {
        return $this->belongsTo(Compartimento::class);
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(Movimentacao::class);
    }
}
