<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class CertificatePreviewService
{
    public function path(string $original): string
    {
        return $original.'.preview.jpg';
    }

    public function generate(string $original): ?string
    {
        $disk = Storage::disk('certificates');
        $preview = $this->path($original);
        if ($disk->exists($preview)) return $preview;
        if (! function_exists('imagecreatefromstring')) return null;
        $bytes = $disk->get($original);
        $size = @getimagesizefromstring($bytes);
        if (! $size || $size[0] > 4096 || $size[1] > 4096) return null;
        $source = @imagecreatefromstring($bytes);
        if (! $source) return null;
        $target = null;
        try {
            $scale = min(1, 480 / max($size[0], $size[1]));
            $width = max(1, (int) round($size[0] * $scale));
            $height = max(1, (int) round($size[1] * $scale));
            $target = imagecreatetruecolor($width, $height);
            imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
            imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
            ob_start();
            try {
                imagejpeg($target, null, 75);
                $result = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            $disk->put($preview, $result);
            return $preview;
        } finally {
            imagedestroy($source);
            if ($target) imagedestroy($target);
        }
    }
}
