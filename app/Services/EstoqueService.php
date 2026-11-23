<?php

namespace App\Services;

use App\Data\EntradaData;
use App\Enums\TipoIngrediente;
use App\Enums\TipoOperacao;
use App\Exceptions\Domain\CapacidadeExcedidaException;
use App\Exceptions\Domain\CompartimentoTravadoNoDiaException;
use App\Exceptions\Domain\EstoqueInsuficienteException;
use App\Exceptions\Domain\TipoIncompativelException;
use App\Models\Compartimento;
use App\Models\Ingrediente;
use App\Models\Movimentacao;
use Illuminate\Support\Facades\DB;


final class EstoqueService
{
    public function registrarEntrada(EntradaData $d): Ingrediente
    {
        return DB::transaction(function () use ($d) {
            $comp = Compartimento::whereKey($d->compartimentoId)->lockForUpdate()->firstOrFail();
            $hoje = now()->toDateString();

            $this->garantirTipoCompativel($comp, $d->tipo, $hoje);

            $novoTotal = bcadd($comp->quantidade_atual, $d->quantidade, 3);
            if(bccomp($novoTotal, $d->tipo->capacidade(), 3) > 0) {
                throw CapacidadeExcedidaException::para($comp, $d->tipo, $d->quantidade);
            }

            $comp->update([
                'tipo_atual' => $d->tipo,
                'tipo_definido_em' => $hoje,
                'quantidade_atual' => $novoTotal,
            ]);

            $ingrediente = Ingrediente::where('compartimento_id', $comp->id)
                ->where('nome', $d->nome)
                ->lockForUpdate()
                ->first()
                ?? new Ingrediente(['compartimento_id' => $comp->id, 'nome' => $d->nome, 'quantidade' => 0]);

            $ingrediente->tipo = $d->tipo;
            $ingrediente->quantidade = bcadd($ingrediente->quantidade, $d->quantidade, 3);
            $ingrediente->save();

            $this->registrarHistorico($ingrediente, TipoOperacao::ENTRADA, $d->quantidade, $d->responsavel);

            return $ingrediente;
        }, attempts: 3);
    }
    

    public function registrarSaida(int $ingredienteId, string $quantidade, string $responsavel): Ingrediente
    {
        return DB::transaction(function () use ($ingredienteId, $quantidade, $responsavel) {
            $compartimentoId = Ingrediente::whereKey($ingredienteId)->valueOrFail('compartimento_id');

            $comp = Compartimento::whereKey($compartimentoId)->lockForUpdate()->firstOrFail();
            $ingrediente = Ingrediente::whereKey($ingredienteId)->lockForUpdate()->firstOrFail();

           if(bccomp($ingrediente->quantidade, $quantidade, 3) < 0) {
                throw EstoqueInsuficienteException::para($ingrediente, $comp, $quantidade);
            }

           $ingrediente->update([
                'quantidade' => bcsub($ingrediente->quantidade, $quantidade, 3),
            ]);

            $comp->update([
                'quantidade_atual' => bcsub($comp->quantidade_atual, $quantidade, 3),
            ]);

            $this->registrarHistorico($ingrediente, TipoOperacao::SAIDA, $quantidade, $responsavel);

            return $ingrediente;
        }, attempts: 3);
    }

    private function garantirTipoCompativel(Compartimento $comp, TipoIngrediente $tipo, string $hoje): void
    {
        if($comp->tipo_atual === null || $comp->tipo_atual === $tipo){
            return;
        }

        if(bccomp($comp->quantidade_atual, '0', 3) > 0) {
            throw TipoIncompativelException::para($comp, $tipo);
        }

        if($comp->tipo_definido_em?->toDateString() === $hoje) {
            throw CompartimentoTravadoNoDiaException::para($comp, $tipo);
        }
    }

    private function registrarHistorico(Ingrediente $i, TipoOperacao $op, string $qtd, string $responsavel): void
    {
        Movimentacao::create([
            'ingrediente_id' => $i->id,
            'compartimento_id' => $i->compartimento_id,
            'operacao' => $op,
            'tipo_ingrediente' => $i->tipo,
            'quantidade' => $qtd,
            'responsavel' => $responsavel,
            'occurred_at' => now(),
        ]);
    }
}
