<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_attempts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->unsignedInteger('attempt_no');
            $table->string('result', 32);
            $table->text('error')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['notification_id', 'attempt_no'], 'notification_attempts_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_attempts');
    }
};
