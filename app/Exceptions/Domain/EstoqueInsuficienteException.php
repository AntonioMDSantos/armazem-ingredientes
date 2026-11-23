<?php

namespace App\Exceptions\Domain;

use App\Models\Compartimento;
use App\Models\Ingrediente;

final class EstoqueInsuficienteException extends RegraDeNegocioException
{
    public static function para(Ingrediente $i, Compartimento $c, string $solicitado): self
    {
        $unidade = $i->tipo->unidade();

        return new self(sprintf(
            '%s em %s tem %s %s. Solicitado: %s %s.',
            $i->nome,
            $c->codigo,
            $i->quantidade,
            $unidade,
            $solicitado,
            $unidade,
        ));
    }

    public function codigo(): string
    {
        return 'ESTOQUE_INSUFICIENTE';
    }
}
