<?php

if (! function_exists('isDebugMode')) {
    /**
     * Cek apakah Developer Debug Mode aktif (DEBUG_MODE=true pada konfigurasi).
     */
    function isDebugMode(): bool
    {
        return (bool) config('app.debug_mode', false);
    }
}

if (! function_exists('generateDummyJpegDataUrl')) {
    /**
     * Buat data URL JPEG minimal yang valid secara biner (SOI \xFF\xD8 ... EOI \xFF\xD9, >100 byte)
     * untuk keperluan bypass kamera granular pada Developer Debug Mode.
     */
    function generateDummyJpegDataUrl(string $seed = 'DBG'): string
    {
        $binary = "\xFF\xD8".str_repeat($seed !== '' ? $seed : 'D', 64)."\xFF\xD9";

        return 'data:image/jpeg;base64,'.base64_encode($binary);
    }
}
