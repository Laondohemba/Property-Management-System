<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PMS domain settings
|--------------------------------------------------------------------------
*/

return [

    'inventory' => [
        // Days of room_type_inventory kept ahead of each property's business date. The calendar
        // must scroll at least 12 months (CAL-01); bookings are usually taken up to 2 years out.
        'horizon_days' => (int) env('INVENTORY_HORIZON_DAYS', 730),

        // Longest range one grid request or one block may cover.
        'max_range_days' => 366,
    ],

];
