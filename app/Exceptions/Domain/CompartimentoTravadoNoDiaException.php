<?php

namespace App\Exceptions\Domain;

use App\Enums\TipoIngrediente;
use App\Models\Compartimento;

final class CompartimentoTravadoNoDiaException extends RegraDeNegocioException
{
    public static function para(Compartimento $c, TipoIngrediente $solicitado): self
    {
        return new self(sprintf(
            '%s recebeu %s em %s e só pode receber %s a partir de %s.',
            $c->codigo,
            $c->tipo_atual?->value,
            $solicitado->value,
        ));
    }

    public function codigo(): string
    {
        return 'COMPARTIMENTO_TRAVADO_NO_DIA';
    }
}
