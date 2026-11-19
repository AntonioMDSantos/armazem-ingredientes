<?php


namespace App\Enums;

enum TipoIngrediente: string
{
    case SECO = 'SECO';
    case LIQUIDO = 'LIQUIDO';
    case REFRIGERADO = 'REFRIGERADO';

    public function capacidade(): string
    {
        return match ($this) {
            self::SECO => '600.000',
            self::LIQUIDO => '500.000',
            self::REFRIGERADO => '400.000',
        };
    }

    public function unidade(): string
    {
        return $this === self::LIQUIDO ? 'L' : 'kg';
    }

    public static function tryFromInput(?string $valor): ?self
    {
        if ($valor === null) {
            return null;
        }

        $normalizado = str_replace('Í', 'I', mb_strtoupper(trim($valor)));
        $normalizado = preg_replace('/S$/', '', $normalizado);

        return self::tryFrom($normalizado);
    }
}