<?php

return [
    // Produksi: FINGERPRINT_DRIVER=bridge (sensor ZK9500 via FingerBridge).
    // 'mock' hanya untuk test otomatis / presentasi tanpa alat.
    'driver' => env('FINGERPRINT_DRIVER', 'mock'),
    'base_url' => env('FINGERPRINT_BASE_URL', 'http://127.0.0.1:8787'),
    'timeout' => (int) env('FINGERPRINT_TIMEOUT', 25),

    // Ambang skor kecocokan (identify). Dibaca oleh FingerBridge.exe dari .env ini
    // (atau env var / bridge\fingerbridge.config). Bawaan 40; naikkan bila salah-kenal,
    // turunkan bila jari sah sering tidak dikenali.
    'match_threshold' => (int) env('FINGERPRINT_MATCH_THRESHOLD', 40),
];
