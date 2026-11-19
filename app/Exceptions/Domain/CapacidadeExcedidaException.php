<?php

namespace App\Exceptions\Domain;

use App\Enums\TipoIngrediente;
use App\Models\Compartimento;

final class CapacidadeExcedidaException extends RegraDeNegocioException
{
    public static function para(Compartimento $c, TipoIngrediente $tipo, string $solicitacao): self
    {
       return new self(sprintf(
        '%s suporta %s %s de %s. Ocupado: %s. Solicitado: %s.',
        $c->codigo,
        $tipo->capacidade(),
        $tipo->unidade(),
        $tipo->value,
        $c->quantidade_atual,
        $solicitacao
       ));
    }

    public function codigo(): string
    {
        return 'CAPACIDADE_EXCEDIDA';
    }
}