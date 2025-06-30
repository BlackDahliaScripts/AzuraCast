<?php

declare(strict_types=1);

namespace App\Controller\Api\Stations;

use App\Controller\SingleActionInterface;
use App\Entity\Api\Status;
use App\Entity\Repository\StationMediaRepository;
use App\Entity\Repository\StationQueueRepository;
use App\Entity\StationQueue;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use App\Radio\AutoDJ\Queue;
use App\Radio\Backend\Liquidsoap;
use App\Utilities\Time;
use App\Utilities\Types;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

#[
    OA\Post(
        path: '/station/{station_id}/queue/media/{media_id}',
        operationId: 'queueMedia',
        summary: 'Queue a specific media file by its ID.',
        tags: [OpenApi::TAG_STATIONS_QUEUE],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
            new OA\Parameter(
                name: 'media_id',
                description: 'Media ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', format: 'int64')
            ),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    'play_immediately' => new OA\Property(
                        property: 'play_immediately',
                        description: 'Whether to play the media file immediately (skip current song)',
                        type: 'boolean',
                        default: false
                    ),
                ]
            )
        ),
        responses: [
            new OpenApi\Response\Success(
                content: new OA\JsonContent(ref: Status::class)
            ),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\NotFound(),
            new OpenApi\Response\GenericError(),
        ]
    )
]
final class QueueMediaAction implements SingleActionInterface
{
    public function __construct(
        private readonly StationMediaRepository $mediaRepo,
        private readonly StationQueueRepository $queueRepo,
        private readonly Queue $queue,
        private readonly Liquidsoap $liquidsoap
    ) {
    }

    public function __invoke(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        /** @var string $mediaId */
        $mediaId = $params['media_id'];
        
        $station = $request->getStation();
        
        // Find the media file
        $media = $this->mediaRepo->requireForStation($mediaId, $station);
        
        // Get request body parameters
        $parsedBody = $request->getParsedBody();
        $playImmediately = Types::bool(
            is_array($parsedBody) ? ($parsedBody['play_immediately'] ?? false) : false
        );
        
        // Create a new queue entry
        $queueRow = StationQueue::fromMedia($station, $media);
        
        if ($playImmediately) {
            // For immediate playback, set it to be sent to AutoDJ immediately
            // and position it at the front of the queue
            $queueRow->setTimestampCued(Time::nowUtc());
            $queueRow->setSentToAutodj(false); // Will be picked up immediately by AutoDJ
            
            // Clear any existing unsent queue items to prioritize this one
            $this->queueRepo->clearUpcomingQueue($station);
        } else {
            // For regular queuing, let the AutoDJ queue system handle timing
            $queueRow->setTimestampCued(Time::nowUtc());
        }
        
        // Persist the queue entry
        $this->queueRepo->getEntityManager()->persist($queueRow);
        $this->queueRepo->getEntityManager()->flush();
        
        // If immediate playback is requested, trigger the skip command
        if ($playImmediately) {
            try {
                // Rebuild the queue to ensure proper ordering
                $this->queue->buildQueue($station);
                
                // Skip to next song (which will be our queued media)
                $this->liquidsoap->skip($station);
                
                return $response->withJson([
                    'success' => true,
                    'message' => 'Media queued and playing immediately.',
                    'queue_id' => $queueRow->getIdRequired(),
                    'media_id' => $media->getIdRequired(),
                ]);
            } catch (\Exception $e) {
                return $response->withJson([
                    'success' => false,
                    'message' => 'Media queued but failed to play immediately: ' . $e->getMessage(),
                    'queue_id' => $queueRow->getIdRequired(),
                    'media_id' => $media->getIdRequired(),
                ]);
            }
        }
        
        // Rebuild the queue to include the new item
        $this->queue->buildQueue($station);
        
        return $response->withJson([
            'success' => true,
            'message' => 'Media queued successfully.',
            'queue_id' => $queueRow->getIdRequired(),
            'media_id' => $media->getIdRequired(),
        ]);
    }
}
