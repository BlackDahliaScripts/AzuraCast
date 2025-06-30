<?php

declare(strict_types=1);

namespace App\Radio\Backend\Liquidsoap\Command;

use App\Entity\Station;
use App\Radio\Backend\Liquidsoap;

final class ClearQueueCommand extends AbstractCommand
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
        // Clear the request queue
        $response = $this->liquidsoap->command($station, 'request.queue.clear()');

        return [
            'success' => true,
            'message' => 'Request queue cleared',
            'response' => $response,
        ];
    }
}