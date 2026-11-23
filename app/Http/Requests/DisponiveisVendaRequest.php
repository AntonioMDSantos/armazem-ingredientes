<?php

namespace App\Http\Requests;

use App\Enums\TipoIngrediente;
use App\Http\Requests\Concerns\NormalizaTipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisponiveisVendaRequest extends FormRequest
{
    use NormalizaTipo;

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoIngrediente::class)],
        ];
    }
}
