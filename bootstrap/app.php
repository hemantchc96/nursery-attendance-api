<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request, $exception) => $request->routeIs('attendance.*') || $request->expectsJson());
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($request->routeIs('attendance.*') && in_array($exception->getStatusCode(), [400, 404, 409], true)) {
                return response()->json(['message' => $exception->getStatusCode() === 404 ? 'Resource not found.' : $exception->getMessage()], $exception->getStatusCode());
            }
        });
    })->create();
