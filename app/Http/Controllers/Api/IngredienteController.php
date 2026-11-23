<?php

namespace App\Http\Controllers\Api;

use App\Data\EntradaData;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaidaRequest;
use App\Http\Requests\StoreIngredienteRequest;
use App\Http\Resources\IngredienteResource;
use App\Models\Ingrediente;
use App\Services\ConsultaService;
use App\Services\EstoqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class IngredienteController extends Controller
{
    public function __construct(
        private readonly EstoqueService $estoque,
        private readonly ConsultaService $consulta,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return IngredienteResource::collection(
            Ingrediente::with('compartimento')->orderBy('id')->paginate(50),
        );
    }

    public function show(Ingrediente $ingrediente): IngredienteResource
    {
        return IngredienteResource::make($ingrediente->load('compartimento'));
    }

    /** Entrada: cria ou soma. Sempre 201, porque sempre nasce uma movimentação (DECISOES.md, item 6). */
    public function store(StoreIngredienteRequest $request): JsonResponse
    {
        $ingrediente = $this->estoque->registrarEntrada($request->toEntradaData());

        return IngredienteResource::make($ingrediente->load('compartimento'))
            ->response()
            ->setStatusCode(201);
    }

    /** Recebe o id, não o model: o service relê o ingrediente com lock, e um id inexistente vira 404 lá. */
    public function saida(SaidaRequest $request, Ingrediente $ingrediente): IngredienteResource
    {
        $atualizado = $this->estoque->registrarSaida(
            $ingrediente->id,
            (string) $request->validated('quantidade'),
            $request->validated('responsavel'),
        );

        return IngredienteResource::make($atualizado->load('compartimento'));
    }

    public function volume(): JsonResponse
    {
        return response()->json($this->consulta->volumePorTipo());
    }
}
