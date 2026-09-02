<?php

use App\Http\Middleware\Require2FA;
use App\Http\Middleware\SessionTimeout;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'require.2fa'         => Require2FA::class,
            'session.timeout'     => SessionTimeout::class,
            'check.product.limit'    => \App\Http\Middleware\CheckProductLimit::class,
            'check.warehouse.limit'  => \App\Http\Middleware\CheckWarehouseLimit::class,
            'check.pos.terminal'     => \App\Http\Middleware\CheckPOSTerminalLimit::class,
            'check.transfer.feature' => \App\Http\Middleware\CheckStockTransferFeature::class,
            'role'                => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'          => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission'  => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('reports:send-monthly-pnl')->monthlyOn(1, '07:00');
        $schedule->command('reports:send-scheduled')->dailyAt('06:00');
    })
    ->create();
