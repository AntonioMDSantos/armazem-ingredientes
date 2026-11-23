<?php

namespace App\Http\Requests;

use App\Enums\TipoIngrediente;
use App\Http\Requests\Concerns\NormalizaTipo;
use App\Http\Requests\Concerns\QuantidadeDecimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisponiveisRequest extends FormRequest
{
    use NormalizaTipo;
    use QuantidadeDecimal;

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoIngrediente::class)],
            'quantidade' => $this->regrasQuantidade(),
        ];
    }
}
