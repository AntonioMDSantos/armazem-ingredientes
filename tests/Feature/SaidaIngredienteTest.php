<?php

namespace Tests\Feature;

use App\Models\Compartimento;
use App\Models\Ingrediente;
use App\Models\Movimentacao;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/** POST /api/ingredientes/{id}/saida (DECISOES.md, item 7). */
final class SaidaIngredienteTest extends ApiTestCase
{
    // ---------------------------------------------------------------
    // Caminho feliz
    // ---------------------------------------------------------------

    #[Test]
    public function saida_reduz_o_ingrediente_e_o_compartimento(): void
    {
        $id = $this->criarIngrediente(400);

        $this->saida($id, 150)
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.quantidade', 250)
            ->assertJsonPath('data.compartimentoId', 1);

        $this->assertSame('250.000', Ingrediente::find($id)->quantidade);
        $this->assertSaldo(1, '250.000');
    }

    #[Test]
    public function saida_registra_movimentacao_com_a_foto_do_momento(): void
    {
        $id = $this->criarIngrediente(400);

        $this->saida($id, 150, 'ana')->assertOk();

        $mov = Movimentacao::where('operacao', 'SAIDA')->sole();
        $this->assertSame($id, $mov->ingrediente_id);
        $this->assertSame(1, $mov->compartimento_id);
        $this->assertSame('SECO', $mov->tipo_ingrediente->value);
        $this->assertSame('150.000', $mov->quantidade);
        $this->assertSame('ana', $mov->responsavel);
        $this->assertTrue($mov->occurred_at->equalTo(now()));
    }

    #[Test]
    public function saida_de_todo_o_estoque_e_permitida(): void
    {
        $id = $this->criarIngrediente(400);

        $this->saida($id, 400)->assertOk()->assertJsonPath('data.quantidade', 0);

        $this->assertSame('0.000', Ingrediente::find($id)->quantidade);
        $this->assertSaldo(1, '0.000');
    }

    #[Test]
    public function saida_usa_aritmetica_decimal_exata(): void
    {
        $id = $this->criarIngrediente(0.3);

        $this->saida($id, 0.1)->assertOk();
        $this->saida($id, 0.1)->assertOk();
        $this->saida($id, 0.1)->assertOk()->assertJsonPath('data.quantidade', 0);

        $this->assertSaldo(1, '0.000');
    }

    #[Test]
    public function saida_nao_mexe_em_outro_ingrediente_do_mesmo_compartimento(): void
    {
        $farinha = $this->criarIngrediente(300);
        $acucar = $this->entrada(['nome' => 'Açúcar', 'quantidade' => 200])->assertCreated()->json('data.id');

        $this->saida($farinha, 100)->assertOk();

        $this->assertSame('200.000', Ingrediente::find($acucar)->quantidade);
        $this->assertSaldo(1, '400.000');
    }

    // ---------------------------------------------------------------
    // Trava do dia (item 5): só a ENTRADA define o tipo; saída não renova a trava.
    // ---------------------------------------------------------------

    #[Test]
    public function saida_total_mantem_o_tipo_e_a_data_do_compartimento(): void
    {
        $id = $this->criarIngrediente(100);

        $this->saida($id, 100)->assertOk();

        $c = Compartimento::find(1);
        $this->assertSame('SECO', $c->tipo_atual->value);
        $this->assertSame(now()->toDateString(), $c->tipo_definido_em->toDateString());
    }

    #[Test]
    public function saida_em_outro_dia_nao_renova_a_trava(): void
    {
        $id = $this->criarIngrediente(100);

        $this->travelTo(now()->addDay());
        $this->saida($id, 100)->assertOk();

        $this->assertSame(now()->subDay()->toDateString(), Compartimento::find(1)->tipo_definido_em->toDateString());
        $this->entrada(['nome' => 'Leite', 'tipo' => 'LIQUIDO', 'quantidade' => 10])->assertCreated();
    }

    // ---------------------------------------------------------------
    // Rejeições: status, 'error', nenhuma movimentação nova, estoque intacto.
    // ---------------------------------------------------------------

    #[Test]
    public function rejeita_saida_maior_que_o_estoque(): void
    {
        $id = $this->criarIngrediente(100);

        $this->assertSaidaRejeitada($id, 100.001, 'ESTOQUE_INSUFICIENTE')
            ->assertJsonPath('message', 'Farinha de trigo em C1 tem 100.000 kg. Solicitado: 100.001 kg.');
    }

    #[Test]
    public function rejeita_saida_de_ingrediente_zerado(): void
    {
        $id = $this->criarIngrediente(100);
        $this->saida($id, 100)->assertOk();

        $this->assertSaidaRejeitada($id, 1, 'ESTOQUE_INSUFICIENTE');
    }

    #[Test]
    public function saida_de_ingrediente_inexistente_retorna_404(): void
    {
        $this->criarIngrediente(100);

        $this->assertSemEfeito(fn () => $this->saida(999, 1), 'NAO_ENCONTRADO', 404);
    }

    #[Test]
    public function id_nao_numerico_retorna_404(): void
    {
        $this->assertSemEfeito(
            fn () => $this->postJson('/api/ingredientes/abc/saida', ['quantidade' => 1, 'responsavel' => 'ana']),
            'NAO_ENCONTRADO',
            404,
        );
    }

    public static function quantidadesInvalidas(): array
    {
        return [
            'zero' => [0],
            'negativa' => [-1],
            'mais de 3 casas' => [1.0001],
            'notação científica' => ['1e1'],
            'texto' => ['tudo'],
        ];
    }

    #[Test]
    #[DataProvider('quantidadesInvalidas')]
    public function rejeita_quantidade_invalida(mixed $quantidade): void
    {
        $id = $this->criarIngrediente(100);

        $this->assertSemEfeito(
            fn () => $this->postJson("/api/ingredientes/{$id}/saida", ['quantidade' => $quantidade, 'responsavel' => 'ana']),
            'VALIDACAO',
        )->assertJsonValidationErrors('quantidade');
    }

    #[Test]
    public function payload_vazio_retorna_erro_por_campo(): void
    {
        $id = $this->criarIngrediente(100);

        $this->assertSemEfeito(fn () => $this->postJson("/api/ingredientes/{$id}/saida", []), 'VALIDACAO')
            ->assertJsonValidationErrors(['quantidade', 'responsavel']);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /** Farinha de trigo (SECO) no C1. */
    private function criarIngrediente(int|float $quantidade): int
    {
        return $this->entrada(['quantidade' => $quantidade])->assertCreated()->json('data.id');
    }

    private function assertSaidaRejeitada(int $id, int|float $quantidade, string $erro): TestResponse
    {
        return $this->assertSemEfeito(fn () => $this->saida($id, $quantidade), $erro);
    }
}
