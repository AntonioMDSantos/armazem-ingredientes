<?php 

namespace App\Data;

use App\Enums\TipoIngrediente;

final readonly class EntradaData
{
    public function __construct(
        public string $nome,
        public TipoIngrediente $tipo,
        public string $quantidade,
        public int $compartimentoId,
        public string $responsavel,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            nome: $data['nome'],
            tipo: TipoIngrediente::from($data['tipo']),
            quantidade: $data['quantidade'],
            compartimentoId: (int) $data['compartimento_id'],
            responsavel: $data['responsavel'],
        );
    }
}