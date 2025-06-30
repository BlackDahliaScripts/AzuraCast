<?php

declare(strict_types=1);

namespace App\Radio\Backend\Liquidsoap\Command;

use App\Container\EntityManagerAwareTrait;
use App\Entity\Repository\StationMediaRepository;
use App\Entity\Station;
use App\Entity\StationMedia;
use App\Radio\Backend\Liquidsoap;
use RuntimeException;

final class PlayMediaCommand extends AbstractCommand
{
    use EntityManagerAwareTrait;

    public function __construct(
        private readonly Liquidsoap $liquidsoap,
        private readonly StationMediaRepository $mediaRepo,
    ) {
    }

    protected function doRun(
        Station $station,
        bool $asAutoDj = false,
        array $payload = []
    ): mixed {
        $mediaId = $payload['media_id'] ?? null;
        $immediate = $payload['immediate'] ?? false;

        if (!$mediaId) {
            throw new RuntimeException('No media_id provided.');
        }

        // Find the media
        $media = $this->mediaRepo->findByUniqueId($mediaId, $station);
        if (!$media instanceof StationMedia) {
            throw new RuntimeException('Media not found.');
        }

        // Get the media path
        $mediaPath = $media->getPath();

        // Build the liquidsoap command
        if ($immediate) {
            // Play immediately, interrupting current song
            $command = sprintf(
                'request.dynamic.insert(request.create("media:%s"))',
                $mediaPath
            );
        } else {
            // Queue at the top of the request queue
            $command = sprintf(
                'request.queue.push(request.create("media:%s"))',
                $mediaPath
            );
        }

        // Execute the command
        $response = $this->liquidsoap->command($station, $command);

        return [
            'success' => true,
            'message' => $immediate ? 'Playing media immediately' : 'Media queued',
            'media' => [
                'id' => $media->getUniqueId(),
                'title' => $media->getTitle(),
                'artist' => $media->getArtist(),
            ],
            'response' => $response,
        ];
    }
}
