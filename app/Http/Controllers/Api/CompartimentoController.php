<?php

namespace App\Http\Controllers\Api;

use App\Enums\TipoIngrediente;
use App\Http\Controllers\Controller;
use App\Http\Requests\DisponiveisRequest;
use App\Http\Requests\DisponiveisVendaRequest;
use App\Http\Resources\CompartimentoResource;
use App\Models\Compartimento;
use App\Services\ConsultaService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CompartimentoController extends Controller
{
    public function __construct(private readonly ConsultaService $consulta) {}

    public function index(): AnonymousResourceCollection
    {
        return CompartimentoResource::collection(Compartimento::orderBy('codigo')->get());
    }

    public function disponiveis(DisponiveisRequest $request): AnonymousResourceCollection
    {
        return CompartimentoResource::collection($this->consulta->disponiveisParaArmazenar(
            TipoIngrediente::from($request->validated('tipo')),
            (string) $request->validated('quantidade'))
        );
    }

    public function disponiveisParaVenda(DisponiveisVendaRequest $request): AnonymousResourceCollection
    {
        return CompartimentoResource::collection(
            $this->consulta->disponiveisParaVenda(TipoIngrediente::from($request->validated('tipo')))
        );
    }
}
