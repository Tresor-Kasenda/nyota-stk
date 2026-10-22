<?php

use App\Http\Middleware\RequireNyotaSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['nyota' => RequireNyotaSession::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (DomainException $error, Request $request) {
            return response()->json(['status' => 0, 'msg' => $error->getMessage(), 'message' => $error->getMessage()]);
        });
        $exceptions->render(function (ValidationException $error, Request $request) {
            if ($request->ajax() || $request->expectsJson()) {
                $messages = array_map(fn (array $messages): string => $messages[0], $error->errors());

                return response()->json($messages + ['status' => 0, 'msg' => 'Un ou plusieurs champs obligatoires sont vides ou mal remplis', 'errors' => $messages]);
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
