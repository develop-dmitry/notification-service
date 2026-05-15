<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Internal;

use App\Application\Notifications\UseCases\RecordProviderCallback;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProviderCallbackController extends Controller
{
    public function __construct(private readonly RecordProviderCallback $useCase) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_id' => ['required', 'uuid'],
            'status' => ['required', Rule::in([NotificationStatus::Delivered->value, NotificationStatus::Failed->value])],
            'error' => ['nullable', 'string', 'max:1024'],
        ]);

        $notification = $this->useCase->execute(
            $validated['notification_id'],
            NotificationStatus::from($validated['status']),
            $validated['error'] ?? null,
        );

        if (! $notification instanceof Notification) {
            return response()->json(['error' => 'notification_not_found'], 404);
        }

        return response()->json([
            'notification_id' => $notification->id,
            'status' => $notification->status->value,
        ]);
    }
}
