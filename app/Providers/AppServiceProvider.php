<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Notifications\Providers\MockEmailProvider;
use App\Domain\Notifications\Providers\MockSmsProvider;
use App\Http\Middleware\Idempotency\IdempotencyStore;
use Illuminate\Support\ServiceProvider;
use Override;

class AppServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->bind(MockSmsProvider::class, fn (): MockSmsProvider => new MockSmsProvider(
            (float) config('notifications.mock.failure_rate', 0),
            (int) config('notifications.mock.latency_ms', 0),
        ));

        $this->app->bind(MockEmailProvider::class, fn (): MockEmailProvider => new MockEmailProvider(
            (float) config('notifications.mock.failure_rate', 0),
            (int) config('notifications.mock.latency_ms', 0),
            (bool) config('notifications.mock.email_use_mailpit', false),
        ));

        $this->app->singleton(IdempotencyStore::class, fn (): IdempotencyStore => IdempotencyStore::fromConfig());
    }

    public function boot(): void
    {
        //
    }
}
