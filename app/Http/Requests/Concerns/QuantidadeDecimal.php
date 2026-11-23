<?php

namespace App\Http\Requests\Concerns;

trait QuantidadeDecimal
{
    /**
     * Regras de quantidade: positiva e no máximo 3 casas, como a coluna decimal(10,3).
     * A regra decimal também barra notação científica ("1e3", 1.0E-5).
     */
    protected function regrasQuantidade(): array
    {
        return ['required', 'numeric', 'gt:0', 'decimal:0,3'];
    }

    /**
     * Quantidade como string para o bcmath. Em JSON, 12.5 chega como float; com serialize_precision=-1
     * (padrão do PHP 8) a conversão devolve a representação mais curta ("12.5", "0.1"), sem ruído de float.
     */
    public function quantidade(): string
    {
        return (string) $this->validated('quantidade');
    }
}
