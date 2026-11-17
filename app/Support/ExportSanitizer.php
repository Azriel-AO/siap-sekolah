<?php

namespace App\Support;

/**
 * Sanitasi output sel Excel (anti CSV/Formula Injection) dan nama file unduhan (anti Header Injection).
 */
class ExportSanitizer
{
    /**
     * Cegah interpretasi rumus Excel pada nilai string yang diawali karakter pemicu formula.
     */
    public static function sanitizeCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || $value === '-') {
            return $value;
        }

        if (preg_match('/^-\d+(?:[.,]\d+)?%?$/', $value)) {
            return $value;
        }

        $firstChar = $value[0];
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Bersihkan seluruh elemen baris array sebelum ditulis ke file spreadsheet.
     *
     * @param  array<int, mixed>  $row
     * @return array<int, mixed>
     */
    public static function sanitizeRow(array $row): array
    {
        return array_map(static fn (mixed $cell): mixed => self::sanitizeCell($cell), $row);
    }

    /**
     * Normalisasi nama file agar bebas dari CRLF header injection atau path traversal.
     */
    public static function sanitizeFilename(string $filename): string
    {
        $clean = basename($filename);
        $clean = (string) preg_replace('/[^A-Za-z0-9._-]/', '-', $clean);
        $clean = trim($clean, '.-');

        return $clean !== '' ? $clean : 'export.xlsx';
    }
}
