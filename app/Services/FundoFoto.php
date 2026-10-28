<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Cor de fundo de uma foto de produto, para a moldura acompanhar a foto
 * (fundo branco e fundo preto convivem na loja). Amostra um anel de pontos
 * perto das quatro bordas — recuado, porque JPG costuma ter 1 px de borda
 * mais escura — e devolve a mediana por canal: a garrafa encostando numa
 * borda não puxa a cor.
 */
class FundoFoto
{
    /** Pontos por borda. */
    public const PONTOS = 24;

    /** Recuo das bordas, em fração do lado menor (mínimo 2 px). */
    public const RECUO = 0.015;

    /** Foto do disco public; null se o arquivo não existe ou não abre. */
    public static function doDisco(?string $path): ?string
    {
        $disco = Storage::disk('public');
        if (blank($path) || ! $disco->exists($path)) {
            return null;
        }

        return self::detectar($disco->get($path));
    }

    /** "#RRGGBB" (maiúsculo) do conteúdo da imagem; null se não decodifica. */
    public static function detectar(string $conteudo): ?string
    {
        $img = @imagecreatefromstring($conteudo);
        if (! $img) {
            return null;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $recuo = min(max(2, (int) round(min($w, $h) * self::RECUO)), intdiv(min($w, $h) - 1, 2));
        $x0 = $recuo;
        $y0 = $recuo;
        $x1 = $w - 1 - $recuo;
        $y1 = $h - 1 - $recuo;

        $canais = [[], [], []];
        $amostra = function (int $x, int $y) use ($img, &$canais) {
            $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
            $canais[0][] = $c['red'];
            $canais[1][] = $c['green'];
            $canais[2][] = $c['blue'];
        };

        for ($i = 0; $i < self::PONTOS; $i++) {
            $t = self::PONTOS > 1 ? $i / (self::PONTOS - 1) : 0;
            $x = (int) round($x0 + ($x1 - $x0) * $t);
            $y = (int) round($y0 + ($y1 - $y0) * $t);
            $amostra($x, $y0);
            $amostra($x, $y1);
            $amostra($x0, $y);
            $amostra($x1, $y);
        }
        imagedestroy($img);

        return sprintf('#%02X%02X%02X', ...array_map([self::class, 'mediana'], $canais));
    }

    /** Só "#RRGGBB" passa (vai num atributo style). */
    public static function valida(?string $cor): ?string
    {
        return is_string($cor) && preg_match('/^#[0-9A-Fa-f]{6}$/', $cor) ? strtoupper($cor) : null;
    }

    private static function mediana(array $v): int
    {
        sort($v);
        $n = count($v);

        return $n % 2 ? $v[intdiv($n, 2)] : (int) round(($v[$n / 2 - 1] + $v[$n / 2]) / 2);
    }
}
