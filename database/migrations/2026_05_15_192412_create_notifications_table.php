<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained('notification_batches')->cascadeOnDelete();
            $table->string('recipient_id', 64);
            $table->string('recipient_address', 255);
            $table->string('channel', 16);
            $table->string('priority', 16);
            $table->string('status', 16);
            $table->unsignedInteger('attempts_count')->default(0);
            $table->text('last_error')->nullable();
            $table->string('provider_message_id', 128)->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'created_at'], 'notifications_recipient_idx');
            $table->index('status', 'notifications_status_idx');
        });

        DB::statement(<<<'SQL'
            CREATE INDEX notifications_queued_pending_idx
            ON notifications (created_at)
            WHERE status = 'queued' AND published_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
