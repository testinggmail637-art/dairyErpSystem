<?php

use App\Http\Middleware\EnsureFarmSelected;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'farm' => EnsureFarmSelected::class,
        ]);

        // Resolve/validate the farm before route model binding, so bound models are farm-scoped (404 otherwise).
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureFarmSelected::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
