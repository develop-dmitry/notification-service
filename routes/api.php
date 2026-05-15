<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Internal\ProviderCallbackController;
use App\Http\Controllers\Api\V1\NotificationBatchController;
use App\Http\Controllers\Api\V1\SubscriberNotificationsController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/notifications', [NotificationBatchController::class, 'store'])
        ->middleware(EnsureIdempotency::class)
        ->name('api.v1.notifications.store');

    Route::get('/subscribers/{subscriber}/notifications', [SubscriberNotificationsController::class, 'index'])
        ->name('api.v1.subscribers.notifications.index');

    if (app()->environment(['local', 'testing'])) {
        Route::post('/_internal/provider-callback', [ProviderCallbackController::class, 'store'])
            ->name('api.v1.internal.provider-callback');
    }
});
