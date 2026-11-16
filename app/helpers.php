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
