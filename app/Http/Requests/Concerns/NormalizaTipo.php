<?php

namespace App\Http\Requests\Concerns;

use App\Enums\TipoIngrediente;

/**
 * Aceita "SECOS", "seco", "Líquido"... e entrega à validação o valor canônico do enum (DECISOES.md, item 3).
 * Se não reconhecer, mantém o valor original para que Rule::enum rejeite com a mensagem certa.
 */
trait NormalizaTipo
{
    protected function prepareForValidation(): void
    {
        $tipo = $this->input('tipo');

        if (is_string($tipo) && ($enum = TipoIngrediente::tryFromInput($tipo)) !== null) {
            $this->merge(['tipo' => $enum->value]);
        }
    }

    public function tipo(): TipoIngrediente
    {
        return TipoIngrediente::from($this->validated('tipo'));
    }
}
