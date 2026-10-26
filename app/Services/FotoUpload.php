<?php

namespace App\Services;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Foto enviada pelo admin (produto e capa de post). A gestão sobe foto de
 * celular/câmera de até ~20 MB; o original nunca vai pro storage/app/public:
 * gira pelo EXIF, reduz pra no máximo LADO_MAX no lado maior e grava JPG
 * QUALIDADE com nome aleatório de 40 caracteres (o mesmo formato do store()
 * do Laravel, que ProductImage::isUploadDoAdmin reconhece).
 *
 * HEIC (iPhone) só entra se o servidor tiver Imagick com HEIC; senão a
 * validação recusa pedindo JPG.
 */
class FotoUpload
{
    public const LADO_MAX = 2000;

    public const QUALIDADE = 82;

    public const MAX_KB = 30720; // 30 MB

    /** Acima disso a decodificação não cabe na memória com folga (48 MP de iPhone passa). */
    public const MAX_MEGAPIXELS = 100;

    /** Regras de validação do campo de foto (mesmas no produto e no post). */
    public static function regras(): array
    {
        return [
            'nullable',
            'file',
            'max:'.self::MAX_KB,
            'mimes:jpg,jpeg,png,webp,heic,heif',
            function (string $attribute, $value, Closure $fail) {
                if (! $value instanceof UploadedFile) {
                    return;
                }
                if (self::ehHeic($value)) {
                    if (! self::suportaHeic()) {
                        $fail('Foto em HEIC (formato do iPhone) não é aceita. Exporte como JPG e envie de novo.');
                    }

                    return;
                }
                $info = @getimagesize($value->getRealPath());
                if (! $info) {
                    $fail('Não foi possível ler a imagem. Envie um JPG, PNG ou WEBP.');
                } elseif ($info[0] * $info[1] > self::MAX_MEGAPIXELS * 1_000_000) {
                    $fail('Imagem com resolução grande demais ('.$info[0].' × '.$info[1].' px). Reduza e envie de novo.');
                }
            },
        ];
    }

    /** Trata e grava no disco public; devolve o path relativo ("{pasta}/{40}.jpg"). */
    public static function salvar(UploadedFile $arquivo, string $pasta): string
    {
        // Foto de 48 MP descompactada passa de 190 MB no GD.
        $limite = ini_get('memory_limit');
        if ($limite !== '-1' && self::bytes($limite) < 1024 ** 3) {
            ini_set('memory_limit', '1024M');
        }

        $jpg = self::ehHeic($arquivo) ? self::heicParaJpg($arquivo) : self::comGd($arquivo->getRealPath());

        $path = trim($pasta, '/').'/'.Str::random(40).'.jpg';
        Storage::disk('public')->put($path, $jpg);

        return $path;
    }

    private static function comGd(string $arquivo): string
    {
        $img = @imagecreatefromstring(file_get_contents($arquivo));
        if (! $img) {
            throw new RuntimeException('Imagem ilegível: '.$arquivo);
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $escala = min(1, self::LADO_MAX / max($w, $h));
        $nw = max(1, (int) round($w * $escala));
        $nh = max(1, (int) round($h * $escala));

        // Fundo branco: PNG/WEBP com transparência não vira preto no JPG.
        $saida = imagecreatetruecolor($nw, $nh);
        imagefill($saida, 0, 0, imagecolorallocate($saida, 255, 255, 255));
        imagecopyresampled($saida, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);

        // Gira depois de reduzir: girar a foto cheia dobra a memória.
        $saida = self::orienta($saida, $arquivo);

        imageinterlace($saida, true);
        ob_start();
        imagejpeg($saida, null, self::QUALIDADE);
        imagedestroy($saida);

        return ob_get_clean();
    }

    /** Aplica a rotação/espelhamento do EXIF (só JPEG tem; o JPG gravado sai sem EXIF). */
    private static function orienta(\GdImage $img, string $arquivo): \GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($arquivo) : false;
        $o = (int) ($exif['Orientation'] ?? 1);

        if (in_array($o, [2, 4, 5, 7], true)) {
            imageflip($img, IMG_FLIP_HORIZONTAL);
        }

        $graus = match ($o) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($graus !== 0) {
            $girada = imagerotate($img, $graus, 0);
            imagedestroy($img);
            $img = $girada;
        }

        return $img;
    }

    public static function ehHeic(UploadedFile $arquivo): bool
    {
        return in_array(strtolower($arquivo->getClientOriginalExtension()), ['heic', 'heif'], true)
            || in_array($arquivo->getMimeType(), ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'], true);
    }

    public static function suportaHeic(): bool
    {
        return class_exists(\Imagick::class) && \Imagick::queryFormats('HEIC') !== [];
    }

    private static function heicParaJpg(UploadedFile $arquivo): string
    {
        $img = new \Imagick($arquivo->getRealPath());
        $img->autoOrient();
        if (max($img->getImageWidth(), $img->getImageHeight()) > self::LADO_MAX) {
            $img->thumbnailImage(self::LADO_MAX, self::LADO_MAX, true);
        }
        $img->setImageBackgroundColor('white');
        $img = $img->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        $img->stripImage();
        $img->setImageFormat('jpeg');
        $img->setImageCompressionQuality(self::QUALIDADE);
        $img->setInterlaceScheme(\Imagick::INTERLACE_JPEG);

        return $img->getImagesBlob();
    }

    private static function bytes(string $v): int
    {
        $n = (int) $v;

        return match (strtolower(substr(trim($v), -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
