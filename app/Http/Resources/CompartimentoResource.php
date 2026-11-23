<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Compartimento */
class CompartimentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tipo = $this->tipo_atual;

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'tipoAtual' => $tipo?->value,
            'tipoDefinidoEm' => $this->tipo_definido_em?->toDateString(),
            'quantidadeAtual' => (float) $this->quantidade_atual,
            // Vazio sem tipo: a capacidade só é definida pelo tipo que entrar (DECISOES.md, item 1).
            'capacidade' => $tipo ? (float) $tipo->capacidade() : null,
            'unidade' => $tipo?->unidade(),
            // Só existe quando a query calcula espaco_livre (ex.: consulta de disponíveis).
            // array_key_exists e não isset: o valor pode ser 0.
           'espacoLivre' => $this->when($this->resource->getAttribute('espaco_livre') !== null,
           fn () => (float) $this->resource->getAttribute('espaco_livre'),
           ),
        ];
    }
}
