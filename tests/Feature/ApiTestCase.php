<?php

namespace Tests\Feature;

use App\Models\Compartimento;
use App\Models\Ingrediente;
use App\Models\Movimentacao;
use Database\Seeders\CompartimentoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    // Cada teste começa com C1..C5 vazios (ids 1..5).
    protected bool $seed = true;

    protected string $seeder = CompartimentoSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Relógio fixo às 10h: a trava diária não depende da hora em que a suíte roda.
        $this->travelTo(now()->setTime(10, 0));
    }


    protected function entrada(array $override = []): TestResponse
    {
        return $this->postJson('/api/ingredientes', array_merge([
            'nome' => 'Farinha de trigo',
            'tipo' => 'SECOS',
            'quantidade' => 400,
            'compartimentoId' => 1,
            'responsavel' => 'pedro',
        ], $override));
    }

    protected function saida(int $ingredienteId, int|float $quantidade, string $responsavel = 'ana'): TestResponse
    {
        return $this->postJson("/api/ingredientes/{$ingredienteId}/saida", [
            'quantidade' => $quantidade,
            'responsavel' => $responsavel,
        ]);
    }

    /**
     * Toda rejeição: status esperado, o código em 'error', nenhuma movimentação nova e estado intacto.
     * Compara a linha inteira dos compartimentos (saldo, tipo e data do tipo): uma operação rejeitada
     * também não pode travar o tipo. Compara também o saldo de cada ingrediente.
     */
    protected function assertSemEfeito(callable $requisicao, string $erro, int $status = 400): TestResponse
    {
        $movimentacoesAntes = Movimentacao::count();
        $estadoAntes = $this->retratoDoEstoque();

        /** @var TestResponse $response */
        $response = $requisicao();

        $response->assertStatus($status)
            ->assertJsonPath('error', $erro)
            ->assertJsonStructure(['error', 'message']);

        $this->assertSame($movimentacoesAntes, Movimentacao::count(), 'Rejeição não pode gerar movimentação.');
        $this->assertSame($estadoAntes, $this->retratoDoEstoque(), 'Rejeição não pode alterar o estoque.');

        return $response;
    }

    protected function retratoDoEstoque(): array
    {
        return [
            'compartimentos' => Compartimento::query()->toBase()->orderBy('id')
                ->get(['codigo', 'tipo_atual', 'tipo_definido_em', 'quantidade_atual'])
                ->map(fn ($c) => (array) $c)->all(),
            'ingredientes' => Ingrediente::query()->orderBy('id')->pluck('quantidade', 'id')->all(),
        ];
    }

    protected function assertSaldo(int $compartimentoId, string $esperado): void
    {
        $this->assertSame($esperado, Compartimento::find($compartimentoId)->quantidade_atual);
    }
}
