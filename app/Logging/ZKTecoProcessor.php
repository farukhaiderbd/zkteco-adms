<?php

namespace App\Logging;

use Monolog\LogRecord;

class ZKTecoProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        // Add device context if available
        if (isset($record->context['device_serial'])) {
            $record->extra['device_serial'] = $record->context['device_serial'];
        }

        if (isset($record->context['device_ip'])) {
            $record->extra['device_ip'] = $record->context['device_ip'];
        }

        // Add endpoint information
        if (isset($record->context['endpoint'])) {
            $record->extra['endpoint'] = $record->context['endpoint'];
        }

        // Add communication type
        if (isset($record->context['comm_type'])) {
            $record->extra['comm_type'] = $record->context['comm_type'];
        }

        return $record;
    }
}
