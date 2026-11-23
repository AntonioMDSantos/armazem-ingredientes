<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/** GET /api/ingredientes/volume e GET /api/historico (DECISOES.md, item 9). */
final class VolumeEHistoricoTest extends ApiTestCase
{
    // ---------------------------------------------------------------
    // Volume por tipo: array na raiz, sem envelope 'data', como no enunciado
    // ---------------------------------------------------------------

    #[Test]
    public function volume_segue_o_formato_do_enunciado(): void
    {
        $this->entrada(['compartimentoId' => 1, 'tipo' => 'SECOS', 'quantidade' => 400]);
        $this->entrada(['compartimentoId' => 2, 'tipo' => 'LIQUIDO', 'nome' => 'Leite', 'quantidade' => 200]);
        $this->entrada(['compartimentoId' => 3, 'tipo' => 'REFRIGERADO', 'nome' => 'Manteiga', 'quantidade' => 150]);

        $this->getJson('/api/ingredientes/volume')
            ->assertOk()
            ->assertExactJson([
                ['type' => 'LIQUIDO', 'totalQuantity' => 200.0],
                ['type' => 'REFRIGERADO', 'totalQuantity' => 150.0],
                ['type' => 'SECO', 'totalQuantity' => 400.0],
            ]);
    }

    #[Test]
    public function volume_sem_estoque_lista_os_tres_tipos_zerados(): void
    {
        $this->getJson('/api/ingredientes/volume')
            ->assertOk()
            ->assertExactJson([
                ['type' => 'LIQUIDO', 'totalQuantity' => 0.0],
                ['type' => 'REFRIGERADO', 'totalQuantity' => 0.0],
                ['type' => 'SECO', 'totalQuantity' => 0.0],
            ]);
    }

    #[Test]
    public function volume_soma_compartimentos_e_desconta_saidas(): void
    {
        $farinha = $this->entrada(['compartimentoId' => 1, 'quantidade' => 0.1])->json('data.id');
        $this->entrada(['compartimentoId' => 2, 'nome' => 'Açúcar', 'quantidade' => 0.2]);
        $this->entrada(['compartimentoId' => 3, 'nome' => 'Sal', 'quantidade' => 10]);
        $this->saida($farinha, 0.1)->assertOk();

        $this->getJson('/api/ingredientes/volume')
            ->assertJsonPath('2', ['type' => 'SECO', 'totalQuantity' => 10.2])
            ->assertJsonPath('0.totalQuantity', 0);
    }

    // ---------------------------------------------------------------
    // Histórico: padrão data desc, desempate compartimento asc
    // ---------------------------------------------------------------

    #[Test]
    public function historico_padrao_e_data_desc(): void
    {
        $this->entradaAs('08:00', 1);
        $this->entradaAs('09:00', 2);
        $this->entradaAs('10:00', 3);

        $this->getJson('/api/historico')
            ->assertOk()
            ->assertJsonPath('data.*.compartimento', ['C3', 'C2', 'C1'])
            ->assertJsonPath('meta.per_page', 20);
    }

    public static function sentidos(): array
    {
        return [
            'asc' => ['asc', ['C1', 'C2', 'C3']],
            'desc' => ['desc', ['C3', 'C2', 'C1']],
        ];
    }

    #[Test]
    #[DataProvider('sentidos')]
    public function historico_ordena_por_data_nos_dois_sentidos(string $order, array $esperado): void
    {
        $this->entradaAs('08:00', 1);
        $this->entradaAs('09:00', 2);
        $this->entradaAs('10:00', 3);

        $this->getJson("/api/historico?sortBy=date&order={$order}")
            ->assertOk()
            ->assertJsonPath('data.*.compartimento', $esperado);
    }

    #[Test]
    public function mesma_data_desempata_por_compartimento_asc(): void
    {
        // Relógio parado: as três movimentações têm o mesmo occurred_at.
        $this->entrada(['compartimentoId' => 3]);
        $this->entrada(['compartimentoId' => 1]);
        $this->entrada(['compartimentoId' => 2]);

        $this->getJson('/api/historico')->assertJsonPath('data.*.compartimento', ['C1', 'C2', 'C3']);
    }

    /**
     * Regressão (item 9): com precisão de segundos, as duas movimentações empatariam e o desempate
     * por compartimento asc poria C1 primeiro, quebrando a ordem cronológica.
     */
    #[Test]
    public function movimentacoes_no_mesmo_segundo_mantem_a_ordem_cronologica(): void
    {
        $this->travelTo(now()->setTime(10, 0, 0, 100_000));
        $this->entrada(['compartimentoId' => 1]);

        $this->travelTo(now()->setTime(10, 0, 0, 900_000));
        $this->entrada(['compartimentoId' => 2]);

        $this->getJson('/api/historico?order=desc')
            ->assertJsonPath('data.*.compartimento', ['C2', 'C1'])
            ->assertJsonPath('data.0.dataHora', now()->setTime(10, 0, 0, 900_000)->format('Y-m-d\TH:i:s.vP'))
            ->assertJsonPath('data.1.dataHora', now()->setTime(10, 0, 0, 100_000)->format('Y-m-d\TH:i:s.vP'));

        $this->getJson('/api/historico?order=asc')->assertJsonPath('data.*.compartimento', ['C1', 'C2']);
    }

    #[Test]
    public function data_hora_em_iso_8601_com_milissegundos(): void
    {
        $this->travelTo(Carbon::parse('2026-11-23 10:15:30.123456', 'America/Sao_Paulo'));
        $this->entrada();

        $this->getJson('/api/historico')->assertJsonPath('data.0.dataHora', '2026-11-23T10:15:30.123-03:00');
    }

    // ---------------------------------------------------------------
    // Histórico por compartimento
    // ---------------------------------------------------------------

    #[Test]
    #[DataProvider('sentidos')]
    public function historico_ordena_por_compartimento_nos_dois_sentidos(string $order, array $esperado): void
    {
        $this->entradaAs('08:00', 3);
        $this->entradaAs('09:00', 1);
        $this->entradaAs('10:00', 2);

        $this->getJson("/api/historico?sortBy=compartimento&order={$order}")
            ->assertJsonPath('data.*.compartimento', $esperado);
    }

    #[Test]
    public function por_compartimento_desempata_pela_mais_recente(): void
    {
        $this->entradaAs('08:00', 1, 'Farinha');
        $this->entradaAs('09:00', 2, 'Farinha');
        $this->entradaAs('10:00', 1, 'Açúcar');

        $this->getJson('/api/historico?sortBy=compartimento&order=asc')
            ->assertJsonPath('data.*.compartimento', ['C1', 'C1', 'C2'])
            ->assertJsonPath('data.*.ingrediente', ['Açúcar', 'Farinha', 'Farinha']);
    }

    // ---------------------------------------------------------------
    // Paginação e whitelist de parâmetros
    // ---------------------------------------------------------------

    #[Test]
    public function historico_e_paginado(): void
    {
        foreach (range(1, 3) as $i) {
            $this->entradaAs(sprintf('%02d:00', $i), 1);
        }

        $this->getJson('/api/historico?perPage=2')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/historico?perPage=2&page=2')->assertJsonCount(1, 'data');
    }

    public static function parametrosForaDaWhitelist(): array
    {
        return [
            'sortBy coluna' => ['sortBy=id', 'sortBy'],
            'sortBy injeção' => ['sortBy='.urlencode('occurred_at; drop table movimentacoes'), 'sortBy'],
            'sortBy ddate' => ['sortBy=ddate', 'sortBy'],
            'order inválido' => ['order=up', 'order'],
            'order injeção' => ['order='.urlencode('desc, (select 1)'), 'order'],
            'perPage zero' => ['perPage=0', 'perPage'],
            'perPage acima de 100' => ['perPage=101', 'perPage'],
            'perPage texto' => ['perPage=tudo', 'perPage'],
        ];
    }

    #[Test]
    #[DataProvider('parametrosForaDaWhitelist')]
    public function historico_rejeita_parametro_fora_da_whitelist(string $query, string $campo): void
    {
        $this->entrada();

        $this->assertSemEfeito(fn () => $this->getJson("/api/historico?{$query}"), 'VALIDACAO')
            ->assertJsonValidationErrors($campo);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /** Entrada de SECO no compartimento indicado, no horário indicado de hoje. */
    private function entradaAs(string $hora, int $compartimentoId, string $nome = 'Farinha de trigo'): void
    {
        [$h, $m] = array_map('intval', explode(':', $hora));
        $this->travelTo(now()->setTime($h, $m));

        $this->entrada(['compartimentoId' => $compartimentoId, 'nome' => $nome, 'quantidade' => 10])->assertCreated();
    }
}
