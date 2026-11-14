<?php

declare(strict_types=1);

namespace App\Modules\Reporting\DTOs;

use Spatie\LaravelData\Data;

class DashboardPropertyData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $code,
        public string $timezone,
        public string $currency,
        public string $business_date,
        public string $check_in_time,
        public string $check_out_time,
        public string $local_time,
    ) {}
}
