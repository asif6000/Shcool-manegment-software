<?php

namespace App\Services;

use App\Models\Holiday;
use Illuminate\Support\Carbon;

class HolidayService
{
    public function on(Carbon $date): ?Holiday
    {
        return Holiday::query()
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->orderBy('start_date')
            ->first();
    }
}
