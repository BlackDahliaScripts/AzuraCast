<?php

declare(strict_types=1);

namespace App\Radio\Backend\Liquidsoap\Command;

use App\Container\EntityManagerAwareTrait;
use App\Entity\Repository\StationMediaRepository;
use App\Entity\Station;
use App\Radio\Backend\Liquidsoap;
use InvalidArgumentException;
use RuntimeException;

final class QueueMediaCommand extends AbstractCommand
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
        $mediaIds = $payload['media_ids'] ?? [];
        $position = $payload['position'] ?? 'next'; // 'next' or 'end'

        if (empty($mediaIds)) {
            throw new RuntimeException('No media_ids provided.');
        }

        $queued = [];
        foreach ($mediaIds as $mediaId) {
            $media = $this->mediaRepo->findByUniqueId($mediaId, $station);
            if ($media) {
                $command = match ($position) {
                    'next' => sprintf('request.queue.push(request.create("media:%s"))', $media->getPath()),
                    'end' => sprintf('request.queue.append(request.create("media:%s"))', $media->getPath()),
                    default => throw new InvalidArgumentException('Invalid position')
                };

                $this->liquidsoap->command($station, $command);

                $queued[] = [
                    'id' => $media->getUniqueId(),
                    'title' => $media->getTitle(),
                    'artist' => $media->getArtist(),
                ];
            }
        }

        return [
            'success' => true,
            'message' => sprintf('%d tracks queued', count($queued)),
            'queued' => $queued,
        ];
    }
}
