<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HistoricoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sortBy' => ['sometimes', 'string', 'in:date,compartimento'],
            'order' => ['sometimes', 'string', 'in:asc,desc'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    public function sortBy(): string
    {
        return $this->validated('sortBy', 'date');
    }

    public function order(): string
    {
        return $this->validated('order', 'desc');
    }

    public function perPage(): int
    {
        return (int) $this->validated('perPage', 20);
    }
}
