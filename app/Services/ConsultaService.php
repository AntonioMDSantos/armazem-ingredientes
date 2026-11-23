<?php

namespace App\Services;

use App\Enums\TipoIngrediente;
use App\Models\Compartimento;
use App\Models\Ingrediente;
use App\Models\Movimentacao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/** Leituras. Não trava nada: o resultado é um retrato do momento, não uma reserva. */
final class ConsultaService
{
    /** Compartimentos onde a quantidade cabe, do mais justo ao mais folgado (best-fit). */
    public function disponiveisParaArmazenar(TipoIngrediente $tipo, string $quantidade): Collection
    {
        $hoje = now()->toDateString();
        $capacidade = $tipo->capacidade();

        return Compartimento::query()
            ->select('*')
            ->selectRaw('? - quantidade_atual AS espaco_livre', [$capacidade])
            ->where(function ($q) use ($tipo, $hoje) {
                $q->where('tipo_atual', $tipo->value)          // mesmo tipo
                    ->orWhereNull('tipo_atual')                 // nunca usado
                    ->orWhere(fn ($q) => $q                     // vazio e fora da trava do dia
                        ->where('quantidade_atual', 0)
                        ->where('tipo_definido_em', '<', $hoje));
            })
            ->whereRaw('? - quantidade_atual >= ?', [$capacidade, $quantidade])
            ->orderBy('espaco_livre')
            ->orderBy('codigo')
            ->get();
    }

    public function disponiveisParaVenda(TipoIngrediente $tipo): Collection
    {
        return Compartimento::query()
            ->where('tipo_atual', $tipo->value)
            ->where('quantidade_atual', '>', 0)
            ->orderByDesc('quantidade_atual')
            ->orderBy('codigo')
            ->get();
    }

    /** @return list<array{type:string,totalQuantity:float}> */
    public function volumePorTipo(): array
    {
        $totais = Ingrediente::query()->toBase()
            ->selectRaw('tipo, SUM(quantidade) AS total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        return collect(TipoIngrediente::cases())
            ->map(fn (TipoIngrediente $t) => [
                'type' => $t->value,
                'totalQuantity' => (float) ($totais[$t->value] ?? 0),
            ])
            ->sortBy('type')
            ->values()
            ->all();
    }

    public function historico(string $sortBy, string $order, int $perPage): LengthAwarePaginator
    {
        [$principal, $desempate, $ordemDesempate] = match ($sortBy) {
            'compartimento' => ['compartimento_id', 'occurred_at', 'desc'],
            default => ['occurred_at', 'compartimento_id', 'asc'],
        };

        return Movimentacao::query()
            ->with(['compartimento:id,codigo', 'ingrediente:id,nome'])
            ->orderBy($principal, $order)
            ->orderBy($desempate, $ordemDesempate)
            ->orderBy('id', $order) // ordem determinística na paginação
            ->paginate($perPage)
            ->withQueryString();
    }
}