<?php

return [
    'pdf_max_kilobytes' => max(1, (int) env('PDF_MAX_KILOBYTES', 20480)),
];
