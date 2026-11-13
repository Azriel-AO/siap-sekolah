<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceOverlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_kiosk_tanpa_face_guide_dan_input_wedge_memakai_aria_label(): void
    {
        $response = $this->get(route('kiosk.gerbang'));

        $response->assertOk();
        $response->assertDontSee('data-face-overlay', false);
        $response->assertDontSee('data-face-position-guide', false);
        $response->assertDontSee('Posisikan wajah di tengah');
        $response->assertSee('aria-label="Tempelkan kartu pelajar"', false);
        $response->assertDontSee('aria-hidden="true" tabindex="-1"', false);
        $response->assertSee('Kamera belum terdeteksi');
        $response->assertSee('Absen kartu &amp; jari tetap aktif', false);
    }

    public function test_halaman_pendaftaran_memuat_overlay_wajah_tanpa_pencuri_fokus(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->get(route('pendaftaran.index'));

        $response->assertOk();
        $response->assertSee('data-face-overlay', false);
        $response->assertSee('data-face-autocapture', false);
        $response->assertSee('data-face-position-guide', false);
        $response->assertSee('aria-hidden="true"', false);
        $response->assertSee('pointer-events-none', false);
        $response->assertSee('1. Hadap Lurus');
        $response->assertSee('2. Tengok Kanan');
        $response->assertSee('3. Tengok Kiri');
        $response->assertSee('4. Kedip Mata');
        $response->assertSee('Posisikan wajah di dalam kotak');
        $response->assertSee('Bagus, tahan posisi...');
        $response->assertSee('Wajah tidak terdeteksi dengan baik');
        $response->assertSee('Coba lagi');
        $response->assertSee('Gunakan tombol ambil foto manual');
        $response->assertSee('Ambil Foto');

        $this->assertFileExists(public_path('vendor/mediapipe/models/face_landmarker.task'));
        $this->assertGreaterThan(1000000, filesize(public_path('vendor/mediapipe/models/face_landmarker.task')));
    }
}
