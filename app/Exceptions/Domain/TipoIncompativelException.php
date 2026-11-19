<?php

namespace App\Exceptions\Domain;

use App\Enums\TipoIngrediente;
use App\Models\Compartimento;

final class TipoIncompativelException extends RegraDeNegocioException
{
    public static function para(Compartimento $c, TipoIngrediente $solicitado): self
    {
        return new self(sprintf(
            '%s armazena %s e não aceita %s.',
            $c->codigo,
            $c->tipo_atual?->value,
            $c->quantidade_atual,
            $c->tipo_atual->unidade(),
            $solicitado->value
        ));
    }

    public function codigo(): string
    {
        return 'TIPO_INCOMPATIVEL';
    }
}
