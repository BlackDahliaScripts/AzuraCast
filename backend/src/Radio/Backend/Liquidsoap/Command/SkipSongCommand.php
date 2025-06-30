<?php

declare(strict_types=1);

namespace App\Radio\Backend\Liquidsoap\Command;

use App\Entity\Station;
use App\Radio\Backend\Liquidsoap;

final class SkipSongCommand extends AbstractCommand
{
    public function __construct(
        private readonly Liquidsoap $liquidsoap,
    ) {
    }

    protected function doRun(
        Station $station,
        bool $asAutoDj = false,
        array $payload = []
    ): mixed {
        // Skip the current song
        $response = $this->liquidsoap->command($station, 'source.skip');

        return [
            'success' => true,
            'message' => 'Song skipped',
            'response' => $response,
        ];
    }
}
