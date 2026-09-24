<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class SafeProjectZip implements ValidationRule
{
    private const int MAX_ENTRIES = 10_000;

    private const int MAX_UNCOMPRESSED_BYTES = 2_147_483_648;

    private const int MAX_EXPANSION_RATIO = 1_000;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $path = $value instanceof UploadedFile ? $value->getRealPath() : null;

        if (! is_string($path) || ! is_file($path)) {
            $fail('The :attribute must be a valid zip archive.');

            return;
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            $fail('The :attribute could not be read as a zip archive.');

            return;
        }

        $entryCount = $zip->numFiles;

        if ($entryCount < 1) {
            $zip->close();

            $fail('The :attribute must contain at least one file.');

            return;
        }

        if ($entryCount > self::MAX_ENTRIES) {
            $zip->close();

            $fail('The :attribute contains too many files.');

            return;
        }

        $compressedSize = max((int) filesize($path), 1);
        $uncompressedTotal = 0;

        for ($index = 0; $index < $entryCount; $index++) {
            $stat = $zip->statIndex($index);

            if ($stat === false) {
                continue;
            }

            $uncompressedSize = (int) $stat['size'];

            if ($uncompressedSize > 0 && ($uncompressedSize / $compressedSize) > self::MAX_EXPANSION_RATIO) {
                $zip->close();

                $fail('The :attribute contains an abnormally compressed file.');

                return;
            }

            $uncompressedTotal += $uncompressedSize;

            if ($uncompressedTotal > self::MAX_UNCOMPRESSED_BYTES) {
                $zip->close();

                $fail('The :attribute would decompress to more than 2 GB.');

                return;
            }
        }

        if ($uncompressedTotal > 0 && ($uncompressedTotal / $compressedSize) > self::MAX_EXPANSION_RATIO) {
            $zip->close();

            $fail('The :attribute decompresses to an unusually large size.');

            return;
        }

        $zip->close();
    }
}
