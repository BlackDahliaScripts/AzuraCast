<?php

declare(strict_types=1);

namespace App\Controller\Api\Stations;

use App\Container\EntityManagerAwareTrait;
use App\Entity\Api\StationServiceStatus;
use App\Entity\Api\Status;
use App\Entity\Station;
use App\Exception\Supervisor\NotRunningException;
use App\Http\Response;
use App\Http\ServerRequest;
use App\Nginx\Nginx;
use App\OpenApi;
use App\Radio\Adapters;
use App\Radio\Backend\Liquidsoap;
use App\Radio\Configuration;
use App\Radio\Enums\BackendAdapters;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

#[
    OA\Get(
        path: '/station/{station_id}/status',
        operationId: 'getServiceStatus',
        summary: 'Retrieve the current status of all serivces associated with the radio broadcast.',
        tags: [OpenApi::TAG_STATIONS_BROADCASTING],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
        ],
        responses: [
            new OpenApi\Response\Success(
                content: new OA\JsonContent(
                    ref: StationServiceStatus::class
                )
            ),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\NotFound(),
            new OpenApi\Response\GenericError(),
        ]
    ),
    OA\Post(
        path: '/station/{station_id}/restart',
        operationId: 'restartServices',
        summary: 'Restart all services associated with the radio broadcast.',
        tags: [OpenApi::TAG_STATIONS_BROADCASTING],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
        ],
        responses: [
            new OpenApi\Response\Success(),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\NotFound(),
            new OpenApi\Response\GenericError(),
        ]
    ),
    OA\Post(
        path: '/station/{station_id}/frontend/{action}',
        operationId: 'doFrontendServiceAction',
        summary: 'Perform service control actions on the radio frontend (Icecast, Shoutcast, etc.)',
        tags: [OpenApi::TAG_STATIONS_BROADCASTING],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
            new OA\Parameter(
                name: 'action',
                description: 'The action to perform.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    default: 'restart',
                    enum: [
                        'start',
                        'stop',
                        'reload',
                        'restart',
                    ]
                )
            ),
        ],
        responses: [
            new OpenApi\Response\Success(),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\NotFound(),
            new OpenApi\Response\GenericError(),
        ]
    ),
    OA\Post(
        path: '/station/{station_id}/backend/{action}',
        operationId: 'doBackendServiceAction',
        summary: 'Perform service control actions on the radio backend (Liquidsoap)',
        tags: [OpenApi::TAG_STATIONS_BROADCASTING],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
            new OA\Parameter(
                name: 'action',
                description: 'The action to perform.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    default: 'restart',
                    enum: [
                        'skip',
                        'disconnect',
                        'start',
                        'stop',
                        'reload',
                        'restart',
                        'play-media',
                        'queue-media',
                        'clear-queue',
                    ]
                )
            ),
        ],
        requestBody: new OA\RequestBody(
            description: 'Optional request body for media control actions.',
            required: false,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'media_id',
                        description: 'The media ID to play (for play-media action).',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'immediate',
                        description: 'Whether to play immediately (for play-media action).',
                        type: 'boolean',
                        default: false
                    ),
                    new OA\Property(
                        property: 'media_ids',
                        description: 'Array of media IDs to queue (for queue-media action).',
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    ),
                    new OA\Property(
                        property: 'position',
                        description: 'Position to queue at: "next" or "end" (for queue-media action).',
                        type: 'string',
                        default: 'next'
                    ),
                ]
            )
        ),
        responses: [
            new OpenApi\Response\Success(),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\NotFound(),
            new OpenApi\Response\GenericError(),
        ]
    )
]
final class ServicesController
{
    use EntityManagerAwareTrait;

    public function __construct(
        private readonly Configuration $configuration,
        private readonly Nginx $nginx,
        private readonly Adapters $adapters,
    ) {
    }

    public function statusAction(
        ServerRequest $request,
        Response $response
    ): ResponseInterface {
        $station = $request->getStation();

        $backend = $this->adapters->getBackendAdapter($station);
        $frontend = $this->adapters->getFrontendAdapter($station);

        return $response->withJson(
            new StationServiceStatus(
                null !== $backend && $backend->isRunning($station),
                null !== $frontend && $frontend->isRunning($station),
                $station->getHasStarted(),
                $station->getNeedsRestart()
            )
        );
    }

    public function reloadAction(
        ServerRequest $request,
        Response $response
    ): ResponseInterface {
        $this->reloadOrRestartStation($request->getStation(), true);

        return $response->withJson(new Status(true, __('Station reloaded.')));
    }

    public function restartAction(
        ServerRequest $request,
        Response $response
    ): ResponseInterface {
        $this->reloadOrRestartStation($request->getStation(), false);

        return $response->withJson(new Status(true, __('Station restarted.')));
    }

    protected function reloadOrRestartStation(
        Station $station,
        bool $attemptReload
    ): void {
        $station->setHasStarted(true);
        $this->em->persist($station);
        $this->em->flush();

        $this->configuration->writeConfiguration(
            station: $station,
            forceRestart: true,
            attemptReload: $attemptReload
        );

        $this->nginx->writeConfiguration($station);
    }

    public function frontendAction(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        /** @var string $do */
        $do = $params['do'] ?? 'restart';

        $station = $request->getStation();
        $frontend = $this->adapters->requireFrontendAdapter($station);

        switch ($do) {
            case 'stop':
                $frontend->stop($station);

                return $response->withJson(new Status(true, __('Service stopped.')));

            case 'start':
                $frontend->start($station);

                return $response->withJson(new Status(true, __('Service started.')));

            case 'reload':
                $frontend->write($station);
                $frontend->reload($station);

                return $response->withJson(new Status(true, __('Service reloaded.')));

            case 'restart':
            default:
                try {
                    $frontend->stop($station);
                } catch (NotRunningException) {
                }

                $frontend->write($station);
                $frontend->start($station);

                return $response->withJson(new Status(true, __('Service restarted.')));
        }
    }

    public function backendAction(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        /** @var string $do */
        $do = $params['do'] ?? 'restart';

        $station = $request->getStation();

        // Ensure we have Liquidsoap backend for media control actions
        if (in_array($do, ['play-media', 'queue-media', 'clear-queue'], true)) {
            if (BackendAdapters::Liquidsoap !== $station->getBackendType()) {
                return $response->withStatus(400)->withJson(
                    new Status(false, __('This feature is only available for stations using Liquidsoap.'))
                );
            }
        }

        $backend = $this->adapters->requireBackendAdapter($station);

        switch ($do) {
            case 'skip':
                $backend->skip($station);
                return $response->withJson(new Status(true, __('Song skipped.')));

            case 'disconnect':
                $backend->disconnectStreamer($station);
                return $response->withJson(new Status(true, __('Streamer disconnected.')));

            case 'play-media':
                /** @var Liquidsoap $backend */
                $parsedBody = $request->getParsedBody();
                $body = is_array($parsedBody) ? $parsedBody : [];

                $mediaId = $body['media_id'] ?? null;
                if (!$mediaId) {
                    return $response->withStatus(400)->withJson(
                        new Status(false, __('No media_id provided.'))
                    );
                }

                $immediate = $body['immediate'] ?? false;
                $result = $backend->playMedia($station, $mediaId, $immediate);

                return $response->withJson($result);

            case 'queue-media':
                /** @var Liquidsoap $backend */
                $parsedBody = $request->getParsedBody();
                $body = is_array($parsedBody) ? $parsedBody : [];

                $mediaIds = $body['media_ids'] ?? [];
                if (empty($mediaIds)) {
                    return $response->withStatus(400)->withJson(
                        new Status(false, __('No media_ids provided.'))
                    );
                }

                $position = $body['position'] ?? 'next';
                $result = $backend->queueMedia($station, $mediaIds, $position);

                return $response->withJson($result);

            case 'clear-queue':
                /** @var Liquidsoap $backend */
                $backend->clearQueue($station);
                return $response->withJson(new Status(true, __('Request queue cleared.')));

            case 'stop':
                $backend->stop($station);
                return $response->withJson(new Status(true, __('Service stopped.')));

            case 'start':
                $backend->start($station);
                return $response->withJson(new Status(true, __('Service started.')));

            case 'reload':
                $backend->write($station);
                $backend->reload($station);
                return $response->withJson(new Status(true, __('Service reloaded.')));

            case 'restart':
            default:
                try {
                    $backend->stop($station);
                } catch (NotRunningException) {
                }

                $backend->write($station);
                $backend->start($station);
                return $response->withJson(new Status(true, __('Service restarted.')));
        }
    }
}
