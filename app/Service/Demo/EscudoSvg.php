<?php

namespace App\Service\Demo;

/**
 * Genera un escudo SVG original (no oficial) con los colores y las siglas de un equipo.
 */
class EscudoSvg
{
    private const CONTORNO = 'M10 6 H90 V58 C90 88 70 104 50 114 C30 104 10 88 10 58 Z';

    /**
     * @param array{0:string,1:string} $colores primario y secundario en hexadecimal
     */
    public static function generar(string $slug, string $siglas, array $colores): string
    {
        [$primario, $secundario] = $colores;
        $diseno = self::diseno($slug, $secundario);
        $texto = self::contraste($primario);
        $id = 'c-' . $slug;
        $contorno = self::CONTORNO;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 120" role="img" aria-label="{$siglas}">
  <defs><clipPath id="{$id}"><path d="{$contorno}"/></clipPath></defs>
  <path d="{$contorno}" fill="{$primario}"/>
  <g clip-path="url(#{$id})">{$diseno}</g>
  <path d="{$contorno}" fill="none" stroke="#C9A227" stroke-width="4"/>
  <circle cx="50" cy="58" r="21" fill="{$primario}" stroke="{$texto}" stroke-width="2.5"/>
  <text x="50" y="65" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="17" font-weight="700" fill="{$texto}">{$siglas}</text>
  <path d="M50 14 l3 6.2 6.8 1 -4.9 4.8 1.2 6.8 -6.1-3.2 -6.1 3.2 1.2-6.8 -4.9-4.8 6.8-1z" fill="#C9A227"/>
</svg>
SVG;
    }

    /**
     * Patrón interior del escudo; depende del equipo para que no todos se vean iguales.
     */
    private static function diseno(string $slug, string $color): string
    {
        return match (crc32($slug) % 4) {
            0 => "<polygon points=\"0,30 30,0 100,70 100,100 70,120 0,50\" fill=\"{$color}\" opacity=\"0.9\"/>",
            1 => "<rect x=\"22\" y=\"0\" width=\"14\" height=\"120\" fill=\"{$color}\"/><rect x=\"64\" y=\"0\" width=\"14\" height=\"120\" fill=\"{$color}\"/>",
            2 => "<rect x=\"50\" y=\"0\" width=\"50\" height=\"120\" fill=\"{$color}\"/>",
            default => "<polygon points=\"0,70 50,40 100,70 100,90 50,60 0,90\" fill=\"{$color}\"/>",
        };
    }

    /**
     * Blanco o negro según el brillo del color de fondo.
     */
    private static function contraste(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $brillo = (0.299 * hexdec(substr($hex, 0, 2)) + 0.587 * hexdec(substr($hex, 2, 2)) + 0.114 * hexdec(substr($hex, 4, 2))) / 255;

        return $brillo > 0.6 ? '#1B1B1B' : '#FFFFFF';
    }
}
