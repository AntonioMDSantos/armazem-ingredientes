<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Movimentacao */
class MovimentacaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operacao' => $this->operacao->value,
            'tipoIngrediente' => $this->tipo_ingrediente->value,
            'quantidade' => (float) $this->quantidade,
            'responsavel' => $this->responsavel,
            'ingredienteId' => $this->ingrediente_id,
            'ingrediente' => $this->ingrediente->nome,
            'compartimentoId' => $this->compartimento_id,
            'compartimento' => $this->compartimento->codigo,
            // ISO 8601 com milissegundos e offset: 2026-11-19T17:39:12.965-03:00.
            // O banco guarda microssegundos; a resposta corta em milissegundos.
            'dataHora' => $this->occurred_at->format('Y-m-d\TH:i:s.vP'),
        ];
    }
}
