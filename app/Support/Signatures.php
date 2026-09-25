<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Stores a signature drawn on a canvas (a PNG data URL) on the private disk.
 */
final class Signatures
{
    /**
     * @return string the path on the local disk
     *
     * @throws InvalidArgumentException when the data isn't a PNG image
     */
    public static function store(string $dataUrl, string $directory): string
    {
        $encoded = str_contains($dataUrl, ',') ? explode(',', $dataUrl, 2)[1] : $dataUrl;
        $contents = base64_decode($encoded, true);

        if ($contents === false || ! str_starts_with($contents, "\x89PNG")) {
            throw new InvalidArgumentException(__('The signature could not be read.'));
        }

        $path = trim($directory, '/').'/'.Str::uuid().'.png';
        Storage::disk('local')->put($path, $contents);

        return $path;
    }
}
