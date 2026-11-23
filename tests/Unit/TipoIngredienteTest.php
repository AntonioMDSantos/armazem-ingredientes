<?php

namespace Tests\Unit;

use App\Enums\TipoIngrediente;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Enum puro: não precisa do Laravel nem do banco. */
final class TipoIngredienteTest extends TestCase
{
    public static function grafiasValidas(): array
    {
        return [
            'SECO' => ['SECO', TipoIngrediente::SECO],
            'SECOS (enunciado)' => ['SECOS', TipoIngrediente::SECO],
            'secos' => ['secos', TipoIngrediente::SECO],
            'Seco' => ['Seco', TipoIngrediente::SECO],
            'com espaços' => ['  secos  ', TipoIngrediente::SECO],
            'LIQUIDO' => ['LIQUIDO', TipoIngrediente::LIQUIDO],
            'LÍQUIDO' => ['LÍQUIDO', TipoIngrediente::LIQUIDO],
            'líquidos' => ['líquidos', TipoIngrediente::LIQUIDO],
            'Liquidos' => ['Liquidos', TipoIngrediente::LIQUIDO],
            'REFRIGERADO' => ['REFRIGERADO', TipoIngrediente::REFRIGERADO],
            'refrigerados' => ['refrigerados', TipoIngrediente::REFRIGERADO],
        ];
    }

    #[Test]
    #[DataProvider('grafiasValidas')]
    public function normaliza_grafias_aceitas(string $entrada, TipoIngrediente $esperado): void
    {
        $this->assertSame($esperado, TipoIngrediente::tryFromInput($entrada));
    }

    public static function grafiasInvalidas(): array
    {
        return [
            'null' => [null],
            'vazio' => [''],
            'só espaços' => ['   '],
            'só S' => ['S'],
            'desconhecido' => ['GASOSO'],
            'plural duplo' => ['SECOSS'],
            'parcial' => ['SEC'],
            'inglês' => ['DRY'],
        ];
    }

    #[Test]
    #[DataProvider('grafiasInvalidas')]
    public function rejeita_grafias_desconhecidas(?string $entrada): void
    {
        $this->assertNull(TipoIngrediente::tryFromInput($entrada));
    }

    public static function capacidadesEUnidades(): array
    {
        return [
            'seco' => [TipoIngrediente::SECO, '600.000', 'kg'],
            'liquido' => [TipoIngrediente::LIQUIDO, '500.000', 'L'],
            'refrigerado' => [TipoIngrediente::REFRIGERADO, '400.000', 'kg'],
        ];
    }

    #[Test]
    #[DataProvider('capacidadesEUnidades')]
    public function capacidade_e_unidade_por_tipo(TipoIngrediente $tipo, string $capacidade, string $unidade): void
    {
        // String com 3 casas: é o formato que o bcmath e a coluna decimal(10,3) usam.
        $this->assertSame($capacidade, $tipo->capacidade());
        $this->assertSame($unidade, $tipo->unidade());
    }

    #[Test]
    public function valores_canonicos_ficam_no_singular(): void
    {
        $this->assertSame(
            ['SECO', 'LIQUIDO', 'REFRIGERADO'],
            array_column(TipoIngrediente::cases(), 'value'),
        );
    }
}
