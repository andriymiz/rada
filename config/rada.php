<?php

return [
    'pdf_max_kilobytes' => max(1, (int) env('PDF_MAX_KILOBYTES', 20480)),
    'open_data' => [
        'authority_name' => env('OPEN_DATA_AUTHORITY_NAME'),
        'authority_id' => env('OPEN_DATA_AUTHORITY_ID'),
        'authority_cattutc' => env('OPEN_DATA_AUTHORITY_CATTUTC'),
        'convocation' => env('OPEN_DATA_CONVOCATION'),
    ],
];
