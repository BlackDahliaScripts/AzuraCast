<?php

declare(strict_types=1);

namespace App\Controller\Api\Stations;

use App\Container\ContainerAwareTrait;
use App\Entity\Api\Status;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use App\Radio\Enums\LiquidsoapCommands;
use App\Utilities\Types;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

#[
    OA\Post(
        path: '/station/{station_id}/backend/play-media',
        operationId: 'playMedia',
        summary: 'Play a media file immediately or queue it for playback',
        tags: [OpenApi::TAG_STATIONS_BROADCASTING],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'media_id',
                        description: 'The ID of the media file to play',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'immediate',
                        description: 'Whether to play the media immediately (skipping current song)',
                        type: 'boolean',
                        default: false
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
final class PlayMediaController
{
    use ContainerAwareTrait;

    public function __invoke(
        ServerRequest $request,
        Response $response
    ): ResponseInterface {
        $station = $request->getStation();
        $parsedBody = $request->getParsedBody();

        if (!is_array($parsedBody)) {
            return $response->withStatus(400)
                ->withJson(new Status(false, 'Invalid request body.'));
        }

        $mediaId = Types::stringOrNull($parsedBody['media_id'] ?? null, true);
        $immediate = (bool)($parsedBody['immediate'] ?? false);

        if (null === $mediaId) {
            return $response->withStatus(400)
                ->withJson(new Status(false, 'Media ID is required.'));
        }

        try {
            $commandClass = LiquidsoapCommands::PlayMedia->getClass();
            $commandInstance = $this->di->get($commandClass);

            $result = $commandInstance->run(
                $station,
                false,
                [
                    'media_id' => $mediaId,
                    'immediate' => $immediate,
                ]
            );

            return $response->withJson(new Status(
                true,
                $result['message'] ?? 'Media playback initiated successfully.'
            ));
        } catch (InvalidArgumentException $e) {
            return $response->withStatus(400)
                ->withJson(new Status(false, $e->getMessage()));
        } catch (\Throwable $e) {
            return $response->withStatus(500)
                ->withJson(new Status(false, 'An error occurred while processing the request.'));
        }
    }
}
