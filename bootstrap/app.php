<?php

use App\Exceptions\Domain\RegraDeNegocioException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Regra de negócio violada é resposta esperada, não falha do sistema: não vai para o log de erro.
        $exceptions->dontReport(RegraDeNegocioException::class);

        // Regra de negócio violada -> 400 (pedido explícito do enunciado).
        $exceptions->render(fn (RegraDeNegocioException $e) => response()->json([
            'error' => $e->codigo(),
            'message' => $e->getMessage(),
        ], 400));

        // Payload inválido -> 400 também, por consistência com o enunciado (o padrão do Laravel é 422).
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'VALIDACAO',
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 400);
            }
        });

        // ModelNotFoundException chega aqui já convertida em NotFoundHttpException.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'NAO_ENCONTRADO',
                    'message' => 'Recurso não encontrado.',
                ], 404);
            }
        });
    })->create();