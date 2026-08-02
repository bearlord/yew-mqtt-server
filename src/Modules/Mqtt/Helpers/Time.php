<?php

namespace App\Modules\Mqtt\Helpers;

use Carbon\Carbon;
use Yew\Yew;

class Time
{
    /** Current UTC datetime with microsecond precision ("Y-m-d H:i:s.u"). */
    public static function utcDatetimeMicro(): string
    {
        return Carbon::now()->setTimezone('UTC')->format('Y-m-d H:i:s.u');
    }

    /** Convert a UTC "Y-m-d H:i:s[.u]" string to the local (or configured) timezone. */
    public static function utcToLocal(string $utcTime, ?string $timezone = null): string
    {
        if (empty($timezone)) {
            $timezone = Yew::$app->getConfig()->get('yew.timezone', 'UTC');
        }

        // Already in UTC: no conversion needed, return the input untouched.
        if (strcasecmp($timezone, 'UTC') === 0) {
            return $utcTime;
        }

        // Treat the input as a UTC string, so Carbon does not apply the local
        // timezone to the bare string, then shift it to the target timezone.
        return Carbon::parse($utcTime, 'UTC')
            ->shiftTimezone($timezone)
            ->format('Y-m-d H:i:s.u');
    }

}