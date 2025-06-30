<?php

declare(strict_types=1);

namespace App\Radio\Backend\Liquidsoap\Command;

use App\Entity\Repository\StationMediaRepository;
use App\Entity\Station;
use App\Entity\StationMedia;
use App\Radio\Backend\Liquidsoap;
use App\Radio\Enums\LiquidsoapQueues;
use InvalidArgumentException;

final class PlayMediaCommand extends AbstractCommand
{
    public function __construct(
        private readonly StationMediaRepository $mediaRepo,
        private readonly Liquidsoap $liquidsoap
    ) {
    }

    protected function doRun(
        Station $station,
        bool $asAutoDj = false,
        array $payload = []
    ): array {
        $mediaId = $payload['media_id'] ?? null;
        $immediate = (bool)($payload['immediate'] ?? false);

        if (null === $mediaId) {
            throw new InvalidArgumentException('Media ID is required.');
        }

        // Find the media file
        $media = $this->mediaRepo->findByUniqueId($mediaId, $station);
        if (!($media instanceof StationMedia)) {
            throw new InvalidArgumentException('Media file not found.');
        }

        // Get the file path for Liquidsoap
        $mediaPath = $media->getPath();
        
        // If immediate is true, skip current song first
        if ($immediate) {
            $this->liquidsoap->skip($station);
        }

        // Add the media to the request queue (highest priority)
        $result = $this->liquidsoap->enqueue(
            $station,
            LiquidsoapQueues::Requests,
            $mediaPath
        );

        return [
            'success' => true,
            'media_id' => $mediaId,
            'media_path' => $mediaPath,
            'immediate' => $immediate,
            'liquidsoap_response' => $result,
            'message' => $immediate 
                ? 'Media queued for immediate playback (current song skipped)'
                : 'Media queued for playback'
        ];
    }
}
