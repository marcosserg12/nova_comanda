<?php

namespace App\Actions\Orders;

use App\Models\DailyCounter;
use Carbon\CarbonImmutable;

final class NextOrderNumber
{
    /** Must run inside a DB transaction: the counter row stays locked until commit. */
    public function for(CarbonImmutable $day): int
    {
        $date = $day->toDateString();

        DailyCounter::query()->insertOrIgnore(['business_date' => $date, 'last_number' => 0]);

        $counter = DailyCounter::query()->whereKey($date)->lockForUpdate()->firstOrFail();
        $counter->last_number++;
        $counter->save();

        return (int) $counter->last_number;
    }
}
