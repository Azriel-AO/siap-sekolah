<?php

return [
    'driver' => env('FINGERPRINT_DRIVER', 'mock'),
    'base_url' => env('FINGERPRINT_BASE_URL', 'http://127.0.0.1:8787'),
    'timeout' => (int) env('FINGERPRINT_TIMEOUT', 10),
];
