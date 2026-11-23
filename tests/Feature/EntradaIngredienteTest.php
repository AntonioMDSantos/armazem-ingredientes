<?php

namespace Tests\Feature;

use App\Models\Compartimento;
use App\Models\Ingrediente;
use App\Models\Movimentacao;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * POST /api/ingredientes. Cada seção corresponde a um item de DECISOES.md.
 * Compartimentos semeados: C1..C5 com ids 1..5, todos vazios.
 */
final class EntradaIngredienteTest extends ApiTestCase
{
    // ---------------------------------------------------------------
    // Item 1: capacidade por tipo. Compartimento vazio assume a do tipo que entra.
    // ---------------------------------------------------------------

    public static function capacidades(): array
    {
        return [
            'SECO 600 kg' => ['SECO', '600'],
            'LIQUIDO 500 L' => ['LIQUIDO', '500'],
            'REFRIGERADO 400 kg' => ['REFRIGERADO', '400'],
        ];
    }

    #[Test]
    #[DataProvider('capacidades')]
    public function compartimento_vazio_aceita_exatamente_a_capacidade_do_tipo(string $tipo, string $capacidade): void
    {
        $this->entrada(['tipo' => $tipo, 'quantidade' => (float) $capacidade])->assertCreated();

        $this->assertSaldo(1, $capacidade.'.000');
    }

    #[Test]
    #[DataProvider('capacidades')]
    public function rejeita_entrada_que_ultrapassa_a_capacidade_do_tipo(string $tipo, string $capacidade): void
    {
        $this->assertRejeitada(
            ['tipo' => $tipo, 'quantidade' => (float) bcadd($capacidade, '0.001', 3)],
            'CAPACIDADE_EXCEDIDA',
        );
    }

    #[Test]
    public function capacidade_considera_o_que_ja_esta_ocupado(): void
    {
        $this->entrada(['quantidade' => 400])->assertCreated();

        $this->assertRejeitada(['nome' => 'Açúcar', 'quantidade' => 200.001], 'CAPACIDADE_EXCEDIDA');

        $this->entrada(['nome' => 'Açúcar', 'quantidade' => 200])->assertCreated();
        $this->assertSaldo(1, '600.000');
    }

    // ---------------------------------------------------------------
    // Item 2: decimal(10,3) e bcmath, nunca float.
    // ---------------------------------------------------------------

    #[Test]
    public function soma_decimais_sem_erro_de_ponto_flutuante(): void
    {
        $this->entrada(['quantidade' => 0.1])->assertCreated();
        $this->entrada(['quantidade' => 0.2])->assertCreated()->assertJsonPath('data.quantidade', 0.3);

        $this->assertSaldo(1, '0.300');
        $this->assertSame('0.300', Ingrediente::sole()->quantidade);
    }

    public static function quantidadesInvalidas(): array
    {
        return [
            'mais de 3 casas' => [1.0001],
            'zero' => [0],
            'negativa' => [-5],
            'notação científica' => ['1e2'],
            'texto' => ['muito'],
        ];
    }

    #[Test]
    #[DataProvider('quantidadesInvalidas')]
    public function rejeita_quantidade_invalida(mixed $quantidade): void
    {
        $this->assertRejeitada(['quantidade' => $quantidade], 'VALIDACAO')
            ->assertJsonValidationErrors('quantidade');
    }

    // ---------------------------------------------------------------
    // Item 3: enum canônico no singular; entrada aceita plural, minúsculas e acento.
    // ---------------------------------------------------------------

    public static function grafiasDeTipo(): array
    {
        return [
            'SECOS (exemplo do enunciado)' => ['SECOS', 'SECO'],
            'SECO' => ['SECO', 'SECO'],
            'secos' => ['secos', 'SECO'],
            'Líquidos' => ['Líquidos', 'LIQUIDO'],
            'liquido' => ['liquido', 'LIQUIDO'],
            'REFRIGERADOS' => ['REFRIGERADOS', 'REFRIGERADO'],
            'refrigerado' => ['refrigerado', 'REFRIGERADO'],
        ];
    }

    #[Test]
    #[DataProvider('grafiasDeTipo')]
    public function normaliza_o_tipo_para_o_valor_canonico(string $enviado, string $canonico): void
    {
        $this->entrada(['tipo' => $enviado, 'quantidade' => 10])
            ->assertCreated()
            ->assertJsonPath('data.tipo', $canonico);

        $this->assertSame($canonico, Ingrediente::sole()->tipo->value);
        $this->assertSame($canonico, Compartimento::find(1)->tipo_atual->value);
    }

    #[Test]
    public function rejeita_tipo_desconhecido(): void
    {
        $this->assertRejeitada(['tipo' => 'GASOSO'], 'VALIDACAO')
            ->assertJsonValidationErrors('tipo');
    }

    // ---------------------------------------------------------------
    // Item 4: tipo = categoria. Um compartimento guarda uma categoria por vez.
    // ---------------------------------------------------------------

    #[Test]
    public function ingredientes_diferentes_da_mesma_categoria_dividem_o_compartimento(): void
    {
        $this->entrada(['nome' => 'Farinha de trigo', 'quantidade' => 300])->assertCreated();
        $this->entrada(['nome' => 'Açúcar', 'tipo' => 'SECO', 'quantidade' => 200])->assertCreated();

        $this->assertSame(2, Ingrediente::where('compartimento_id', 1)->count());
        $this->assertSaldo(1, '500.000');
    }

    #[Test]
    public function rejeita_outra_categoria_em_compartimento_com_saldo(): void
    {
        $this->entrada(['quantidade' => 100])->assertCreated();

        $this->assertRejeitada(['nome' => 'Leite', 'tipo' => 'LIQUIDO', 'quantidade' => 10], 'TIPO_INCOMPATIVEL');
    }

    // ---------------------------------------------------------------
    // Item 5: compartimento que recebeu um tipo hoje só aceita outro amanhã,
    // mesmo esvaziado. O dia é o de America/Sao_Paulo.
    // ---------------------------------------------------------------

    #[Test]
    public function compartimento_esvaziado_hoje_rejeita_outro_tipo(): void
    {
        $this->entradaESaidaTotal();

        $this->assertRejeitada(['nome' => 'Leite', 'tipo' => 'LIQUIDO', 'quantidade' => 10], 'COMPARTIMENTO_TRAVADO_NO_DIA');
    }

    #[Test]
    public function compartimento_esvaziado_hoje_aceita_o_mesmo_tipo(): void
    {
        $this->entradaESaidaTotal();

        $this->entrada(['nome' => 'Açúcar', 'tipo' => 'SECO', 'quantidade' => 10])->assertCreated();
    }

    #[Test]
    public function compartimento_esvaziado_aceita_outro_tipo_no_dia_seguinte(): void
    {
        $this->entradaESaidaTotal();

        $this->travelTo(now()->addDay()->startOfDay());

        $this->entrada(['nome' => 'Leite', 'tipo' => 'LIQUIDO', 'quantidade' => 10])->assertCreated();
        $this->assertSame('LIQUIDO', Compartimento::find(1)->tipo_atual->value);
    }

    #[Test]
    public function virada_do_dia_segue_o_fuso_de_sao_paulo(): void
    {
        // 23:50 em SP = 02:50 UTC do dia seguinte; 00:10 em SP = 03:10 UTC.
        // Em UTC as duas operações cairiam no mesmo dia e a troca seria rejeitada.
        $this->travelTo(now('America/Sao_Paulo')->setTime(23, 50));
        $this->entradaESaidaTotal();

        $this->travelTo(now('America/Sao_Paulo')->addDay()->setTime(0, 10));

        $this->entrada(['nome' => 'Leite', 'tipo' => 'LIQUIDO', 'quantidade' => 10])->assertCreated();
    }

    // ---------------------------------------------------------------
    // Item 6: POST é uma ENTRADA. Mesmo nome no mesmo compartimento soma; senão cria.
    // Sempre gera histórico. 201 nos dois casos.
    // ---------------------------------------------------------------

    #[Test]
    public function cria_ingrediente_e_registra_a_movimentacao(): void
    {
        $id = $this->entrada(['quantidade' => 400])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Farinha de trigo')
            ->assertJsonPath('data.tipo', 'SECO')
            ->assertJsonPath('data.quantidade', 400)
            ->assertJsonPath('data.compartimentoId', 1)
            ->json('data.id');

        $mov = Movimentacao::sole();
        $this->assertSame($id, $mov->ingrediente_id);
        $this->assertSame(1, $mov->compartimento_id);
        $this->assertSame('ENTRADA', $mov->operacao->value);
        $this->assertSame('SECO', $mov->tipo_ingrediente->value);
        $this->assertSame('400.000', $mov->quantidade);
        $this->assertSame('pedro', $mov->responsavel);
        $this->assertSaldo(1, '400.000');
    }

    #[Test]
    public function mesmo_nome_no_mesmo_compartimento_soma_e_responde_201(): void
    {
        $primeiro = $this->entrada(['quantidade' => 100])->assertCreated()->json('data.id');

        $segundo = $this->entrada(['quantidade' => 50, 'responsavel' => 'ana'])
            ->assertCreated()
            ->assertJsonPath('data.quantidade', 150)
            ->json('data.id');

        $this->assertSame($primeiro, $segundo);
        $this->assertSame(1, Ingrediente::count());
        $this->assertSame(2, Movimentacao::where('operacao', 'ENTRADA')->count());
        $this->assertSaldo(1, '150.000');
    }

    #[Test]
    public function mesmo_nome_em_outro_compartimento_cria_outro_ingrediente(): void
    {
        $this->entrada(['quantidade' => 100, 'compartimentoId' => 1])->assertCreated();
        $this->entrada(['quantidade' => 100, 'compartimentoId' => 2])->assertCreated();

        $this->assertSame(2, Ingrediente::count());
        $this->assertSaldo(1, '100.000');
        $this->assertSaldo(2, '100.000');
    }

    // ---------------------------------------------------------------
    // Item 8: payload inválido -> 400 com errors por campo.
    // ---------------------------------------------------------------

    #[Test]
    public function payload_vazio_retorna_400_com_erro_por_campo(): void
    {
        $this->assertRejeitadaPayload([], 'VALIDACAO')
            ->assertJsonValidationErrors(['nome', 'tipo', 'quantidade', 'compartimentoId', 'responsavel']);
    }

    #[Test]
    public function compartimento_inexistente_retorna_400(): void
    {
        $this->assertRejeitada(['compartimentoId' => 99], 'VALIDACAO')
            ->assertJsonValidationErrors('compartimentoId');
    }

    #[Test]
    public function compartimento_id_em_snake_case_nao_e_aceito(): void
    {
        $payload = [
            'nome' => 'Farinha de trigo', 'tipo' => 'SECOS', 'quantidade' => 400,
            'compartimento_id' => 1, 'responsavel' => 'pedro',
        ];

        $this->assertRejeitadaPayload($payload, 'VALIDACAO')
            ->assertJsonValidationErrors('compartimentoId');
    }

    // ---------------------------------------------------------------
    // Item 12: nome gravado como enviado, com trim; comparação diferencia maiúsculas.
    // ---------------------------------------------------------------

    #[Test]
    public function nome_e_gravado_com_trim_e_soma_com_o_existente(): void
    {
        $this->entrada(['nome' => 'Farinha', 'quantidade' => 10])->assertCreated();
        $this->entrada(['nome' => '  Farinha  ', 'quantidade' => 5])->assertCreated();

        $this->assertSame('Farinha', Ingrediente::sole()->nome);
        $this->assertSaldo(1, '15.000');
    }

    #[Test]
    public function nome_com_maiusculas_diferentes_vira_outro_ingrediente(): void
    {
        $this->entrada(['nome' => 'Farinha', 'quantidade' => 10])->assertCreated();
        $this->entrada(['nome' => 'farinha', 'quantidade' => 10])->assertCreated();

        $this->assertSame(2, Ingrediente::count());
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /** Envia a entrada padrão do ApiTestCase com $override e exige rejeição sem efeito colateral. */
    private function assertRejeitada(array $override, string $erro): TestResponse
    {
        return $this->assertSemEfeito(fn () => $this->entrada($override), $erro);
    }

    /** Igual a assertRejeitada, mas com o payload exato (sem os valores padrão). */
    private function assertRejeitadaPayload(array $payload, string $erro): TestResponse
    {
        return $this->assertSemEfeito(fn () => $this->postJson('/api/ingredientes', $payload), $erro);
    }

    /** Entrada de SECO no C1 e saída total: compartimento vazio, mas com tipo definido hoje. */
    private function entradaESaidaTotal(): void
    {
        $id = $this->entrada(['quantidade' => 100])->assertCreated()->json('data.id');
        $this->saida($id, 100)->assertOk();
        $this->assertSaldo(1, '0.000');
    }
}
