<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Ingrediente */
class IngredienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'tipo' => $this->tipo->value,
            // Float só na saída JSON. Toda conta interna continua em string/bcmath.
            'quantidade' => (float) $this->quantidade,
            'unidade' => $this->tipo->unidade(),
            'compartimentoId' => $this->compartimento_id,
            'compartimento' => $this->whenLoaded('compartimento', fn () => $this->compartimento->codigo),
        ];
    }
}
