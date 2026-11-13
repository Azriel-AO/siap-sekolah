<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceOverlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_kiosk_memuat_overlay_wajah_tanpa_pencuri_fokus(): void
    {
        $response = $this->get(route('kiosk.gerbang'));

        $response->assertOk();
        $response->assertSee('data-face-overlay', false);
        $response->assertSee('aria-hidden="true"', false);
        $response->assertSee('pointer-events-none', false);

        $this->assertFileExists(public_path('vendor/mediapipe/wasm/vision_wasm_internal.wasm'));
        $this->assertFileExists(public_path('vendor/mediapipe/models/blaze_face_short_range.tflite'));
        $this->assertGreaterThan(0, filesize(public_path('vendor/mediapipe/models/blaze_face_short_range.tflite')));
    }

    public function test_halaman_pendaftaran_memuat_overlay_wajah_tanpa_pencuri_fokus(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->get(route('pendaftaran.index'));

        $response->assertOk();
        $response->assertSee('data-face-overlay', false);
        $response->assertSee('data-face-autocapture', false);
        $response->assertSee('aria-hidden="true"', false);
        $response->assertSee('pointer-events-none', false);
        $response->assertSee('1. Hadap Lurus');
        $response->assertSee('2. Tengok Kanan');
        $response->assertSee('3. Tengok Kiri');
        $response->assertSee('4. Kedip Mata');
        $response->assertSee('Ambil Foto');

        $this->assertFileExists(public_path('vendor/mediapipe/models/face_landmarker.task'));
        $this->assertGreaterThan(1000000, filesize(public_path('vendor/mediapipe/models/face_landmarker.task')));
    }
}
