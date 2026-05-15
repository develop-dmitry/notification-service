<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Notifications\DataTransferObjects\CreateBatchData;
use App\Application\Notifications\UseCases\CreateNotificationBatch;
use App\Http\Controllers\Controller;
use App\Http\Resources\BatchAcceptedResource;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationBatchController extends Controller
{
    public function __construct(private readonly CreateNotificationBatch $useCase) {}

    #[HeaderParameter(
        name: 'Idempotency-Key',
        description: 'Уникальный UUID v4 запроса. Повтор с тем же ключом и телом вернёт сохранённый ответ; конфликт тела — 409.',
        required: true,
        type: 'string',
        format: 'uuid',
        example: '8b4e6f60-2c1f-4f2a-9f9b-3a7d5e1c2a91',
    )]
    public function store(Request $request, CreateBatchData $data): JsonResponse
    {
        $result = $this->useCase->execute($data, (string) $request->header('Idempotency-Key'));

        return new BatchAcceptedResource($result['batch'], count($result['notificationIds']))
            ->response()
            ->setStatusCode(202);
    }
}
