<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('channel', 16);
            $table->string('priority', 16);
            $table->text('message');
            // Не unique: блокировка дублей живёт в Redis с TTL. В БД ключ хранится для аудита.
            $table->string('idempotency_key', 64)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_batches');
    }
};
