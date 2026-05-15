<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Notifications\Enums\Channel;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubscriberNotificationsIndexRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriberNotificationsController extends Controller
{
    public function index(SubscriberNotificationsIndexRequest $request, string $subscriberId): AnonymousResourceCollection
    {
        $query = Notification::query()
            ->where('recipient_id', $subscriberId)
            ->latest();

        if (($status = $request->statusFilter()) instanceof NotificationStatus) {
            $query->where('status', $status->value);
        }

        if (($channel = $request->channelFilter()) instanceof Channel) {
            $query->where('channel', $channel->value);
        }

        return NotificationResource::collection(
            $query->paginate(perPage: $request->perPage(), page: $request->page()),
        );
    }
}
