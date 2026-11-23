<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * GET /api/compartimentos/disponiveis e /disponiveis-para-venda (DECISOES.md, itens 1, 5 e 13).
 * Consultas não alteram nada: os testes de rejeição usam assertSemEfeito igual aos de escrita.
 */
final class CompartimentoConsultaTest extends ApiTestCase
{
    // ---------------------------------------------------------------
    // Disponíveis para armazenar: best-fit (item 13)
    // ---------------------------------------------------------------

    #[Test]
    public function ordena_do_menor_espaco_livre_que_comporta_para_o_maior(): void
    {
        $this->entrada(['compartimentoId' => 1, 'quantidade' => 500]); // livre 100
        $this->entrada(['compartimentoId' => 2, 'quantidade' => 300]); // livre 300
        $this->entrada(['compartimentoId' => 4, 'quantidade' => 550]); // livre 50: não comporta 80
        // C3 e C5 vazios: livre 600, empate desfeito pelo código

        $this->disponiveis('SECO', 80)
            ->assertOk()
            ->assertJsonPath('data.*.codigo', ['C1', 'C2', 'C3', 'C5'])
            ->assertJsonPath('data.*.espacoLivre', [100, 300, 600, 600]);
    }

    #[Test]
    public function compartimento_com_espaco_exatamente_igual_entra_na_lista(): void
    {
        $this->entrada(['compartimentoId' => 1, 'quantidade' => 500]);

        // espacoLivre é o espaço antes da entrada: 100 livres comportam exatamente 100.
        $this->disponiveis('SECO', 100)->assertJsonPath('data.0.codigo', 'C1')
            ->assertJsonPath('data.0.espacoLivre', 100);
    }

    // ---------------------------------------------------------------
    // Exclusão por capacidade (item 1)
    // ---------------------------------------------------------------

    public static function capacidades(): array
    {
        return [
            'seco 600' => ['SECO', 600],
            'liquido 500' => ['LIQUIDO', 500],
            'refrigerado 400' => ['REFRIGERADO', 400],
        ];
    }

    #[Test]
    #[DataProvider('capacidades')]
    public function compartimento_vazio_comporta_ate_a_capacidade_do_tipo_pedido(string $tipo, int $capacidade): void
    {
        $this->disponiveis($tipo, $capacidade)
            ->assertOk()
            ->assertJsonPath('data.*.codigo', ['C1', 'C2', 'C3', 'C4', 'C5']);

        $this->disponiveis($tipo, $capacidade + 0.001)->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function exclui_compartimento_sem_espaco_suficiente(): void
    {
        $this->entrada(['compartimentoId' => 1, 'quantidade' => 550]);

        $this->disponiveis('SECO', 50.001)->assertJsonMissingPath('data.4')
            ->assertJsonPath('data.*.codigo', ['C2', 'C3', 'C4', 'C5']);
    }

    // ---------------------------------------------------------------
    // Exclusão por tipo (item 4) e pela trava do dia (item 5)
    // ---------------------------------------------------------------

    #[Test]
    public function exclui_compartimento_com_saldo_de_outro_tipo(): void
    {
        $this->entrada(['compartimentoId' => 1, 'tipo' => 'LIQUIDO', 'quantidade' => 10]);

        $this->disponiveis('SECO', 10)->assertJsonPath('data.*.codigo', ['C2', 'C3', 'C4', 'C5']);
        $this->disponiveis('LIQUIDO', 10)->assertJsonPath('data.0.codigo', 'C1');
    }

    #[Test]
    public function exclui_compartimento_esvaziado_hoje_para_outro_tipo(): void
    {
        $this->esvaziarC1ComSeco();

        $this->disponiveis('LIQUIDO', 10)->assertJsonPath('data.*.codigo', ['C2', 'C3', 'C4', 'C5']);
    }

    #[Test]
    public function compartimento_esvaziado_hoje_continua_disponivel_para_o_mesmo_tipo(): void
    {
        $this->esvaziarC1ComSeco();

        $this->disponiveis('SECO', 10)->assertJsonPath('data.*.codigo', ['C1', 'C2', 'C3', 'C4', 'C5']);
    }

    #[Test]
    public function compartimento_esvaziado_volta_para_outro_tipo_no_dia_seguinte(): void
    {
        $this->esvaziarC1ComSeco();
        $this->travelTo(now()->addDay()->startOfDay());

        $this->disponiveis('LIQUIDO', 10)->assertJsonPath('data.*.codigo', ['C1', 'C2', 'C3', 'C4', 'C5']);
    }

    #[Test]
    public function consulta_nao_reserva_nem_altera_nada(): void
    {
        $this->entrada(['compartimentoId' => 1, 'quantidade' => 100]);
        $antes = $this->retratoDoEstoque();

        $this->disponiveis('SECO', 500)->assertOk();
        $this->getJson('/api/compartimentos/disponiveis-para-venda?tipo=SECO')->assertOk();

        $this->assertSame($antes, $this->retratoDoEstoque());
    }

    #[Test]
    public function aceita_tipo_no_plural_e_minusculo(): void
    {
        $this->disponiveis('líquidos', 10)->assertOk()->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.espacoLivre', 500);
    }

    // ---------------------------------------------------------------
    // Disponíveis para venda: só o tipo pedido, com saldo > 0
    // ---------------------------------------------------------------

    #[Test]
    public function venda_lista_apenas_o_tipo_pedido_com_saldo_do_maior_para_o_menor(): void
    {
        $this->entrada(['compartimentoId' => 1, 'quantidade' => 100]);
        $this->entrada(['compartimentoId' => 2, 'quantidade' => 300]);
        $this->entrada(['compartimentoId' => 3, 'tipo' => 'LIQUIDO', 'quantidade' => 50]);
        $this->entrada(['compartimentoId' => 4, 'quantidade' => 300]);

        $this->venda('SECO')
            ->assertOk()
            ->assertJsonPath('data.*.codigo', ['C2', 'C4', 'C1'])
            ->assertJsonPath('data.*.quantidadeAtual', [300, 300, 100])
            ->assertJsonMissingPath('data.0.espacoLivre');
    }

    #[Test]
    public function venda_exclui_compartimento_zerado_do_mesmo_tipo(): void
    {
        $this->esvaziarC1ComSeco();
        $this->entrada(['compartimentoId' => 2, 'quantidade' => 10]);

        $this->venda('secos')->assertJsonPath('data.*.codigo', ['C2']);
    }

    #[Test]
    public function venda_sem_estoque_do_tipo_retorna_lista_vazia(): void
    {
        $this->entrada(['compartimentoId' => 1, 'quantidade' => 10]);

        $this->venda('REFRIGERADO')->assertOk()->assertExactJson(['data' => []]);
    }

    // ---------------------------------------------------------------
    // Parâmetros obrigatórios
    // ---------------------------------------------------------------

    public static function parametrosInvalidosDisponiveis(): array
    {
        return [
            'sem parâmetros' => ['', ['tipo', 'quantidade']],
            'sem tipo' => ['quantidade=10', ['tipo']],
            'sem quantidade' => ['tipo=SECO', ['quantidade']],
            'tipo desconhecido' => ['tipo=GASOSO&quantidade=10', ['tipo']],
            'quantidade zero' => ['tipo=SECO&quantidade=0', ['quantidade']],
            'quantidade com 4 casas' => ['tipo=SECO&quantidade=1.0001', ['quantidade']],
            'quantidade texto' => ['tipo=SECO&quantidade=muito', ['quantidade']],
        ];
    }

    #[Test]
    #[DataProvider('parametrosInvalidosDisponiveis')]
    public function disponiveis_valida_parametros(string $query, array $camposComErro): void
    {
        $this->assertSemEfeito(fn () => $this->getJson("/api/compartimentos/disponiveis?{$query}"), 'VALIDACAO')
            ->assertJsonValidationErrors($camposComErro);
    }

    public static function parametrosInvalidosVenda(): array
    {
        return [
            'sem tipo' => [''],
            'tipo desconhecido' => ['tipo=GASOSO'],
        ];
    }

    #[Test]
    #[DataProvider('parametrosInvalidosVenda')]
    public function venda_valida_o_tipo(string $query): void
    {
        $this->assertSemEfeito(fn () => $this->getJson("/api/compartimentos/disponiveis-para-venda?{$query}"), 'VALIDACAO')
            ->assertJsonValidationErrors('tipo');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function disponiveis(string $tipo, int|float $quantidade): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/compartimentos/disponiveis?'.http_build_query(compact('tipo', 'quantidade')));
    }

    private function venda(string $tipo): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/compartimentos/disponiveis-para-venda?'.http_build_query(compact('tipo')));
    }

    /** C1 recebe SECO hoje e é esvaziado: saldo 0, tipo SECO travado até amanhã. */
    private function esvaziarC1ComSeco(): void
    {
        $id = $this->entrada(['compartimentoId' => 1, 'quantidade' => 100])->assertCreated()->json('data.id');
        $this->saida($id, 100)->assertOk();
    }
}
