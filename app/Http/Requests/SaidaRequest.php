<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\QuantidadeDecimal;
use Illuminate\Foundation\Http\FormRequest;

class SaidaRequest extends FormRequest
{
    use QuantidadeDecimal;

    public function rules(): array
    {
        return [
            'quantidade' => $this->regrasQuantidade(),
            'responsavel' => ['required', 'string', 'max:255'],
        ];
    }
}
