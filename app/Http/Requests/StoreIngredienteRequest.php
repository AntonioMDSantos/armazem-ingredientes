<?php

namespace App\Http\Requests;

use App\Data\EntradaData;
use App\Enums\TipoIngrediente;
use App\Http\Requests\Concerns\NormalizaTipo;
use App\Http\Requests\Concerns\QuantidadeDecimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIngredienteRequest extends FormRequest
{
    use NormalizaTipo;
    use QuantidadeDecimal;

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoIngrediente::class)],
            'quantidade' => $this->regrasQuantidade(),
            'compartimentoId' => ['required', 'integer', Rule::exists('compartimentos', 'id')],
            'responsavel' => ['required', 'string', 'max:255'],
        ];
    }

    public function toEntradaData(): EntradaData
    {
        return new EntradaData(
            nome: $this->validated('nome'),
            tipo: $this->tipo(),
            quantidade: $this->quantidade(),
            compartimentoId: (int) $this->validated('compartimentoId'),
            responsavel: $this->validated('responsavel'),
        );
    }
}
