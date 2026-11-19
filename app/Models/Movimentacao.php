<?php

namespace App\Models;

use App\Enums\TipoIngrediente;
use App\Enums\TipoOperacao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movimentacao extends Model
{
    // Sem isso o Laravel pluralizaria para "movimentacaos".
    protected $table = 'movimentacoes';

    public $timestamps = false;

    // Grava occurred_at com microssegundos (timestamp(6), horário de America/Sao_Paulo).
    // O padrão 'Y-m-d H:i:s' truncaria para segundos e quebraria a ordenação do histórico.
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'ingrediente_id',
        'compartimento_id',
        'operacao',
        'tipo_ingrediente',
        'quantidade',
        'responsavel',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'operacao' => TipoOperacao::class,
            'tipo_ingrediente' => TipoIngrediente::class,
            'quantidade' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
    }

    public function ingrediente(): BelongsTo
    {
        return $this->belongsTo(Ingrediente::class);
    }

    public function compartimento(): BelongsTo
    {
        return $this->belongsTo(Compartimento::class);
    }
}
