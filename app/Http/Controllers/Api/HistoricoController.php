<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HistoricoRequest;
use App\Http\Resources\MovimentacaoResource;
use App\Services\ConsultaService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class HistoricoController extends Controller
{
    public function __construct(private readonly ConsultaService $consulta) {}

    public function index(HistoricoRequest $request): AnonymousResourceCollection
    {
        return MovimentacaoResource::collection(
            $this->consulta->historico($request->sortBy(), $request->order(), $request->perPage())
        );
    }
}
