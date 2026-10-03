<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// qr code em svg (vetor: imprime nitido em qualquer tamanho e nao precisa da extensao gd)
final class QrSvg {
    public static function svg(string $text): string {
        $options = new QROptions([
            'outputType'    => QROutputInterface::MARKUP_SVG,
            'outputBase64'  => false,     // devolve o <svg> puro, pra colar direto no html
            'eccLevel'      => EccLevel::M, // tolera um pouco de sujeira/dobra no papel impresso
            'addQuietzone'  => true,
            'drawLightModules' => false,
            'svgPreserveAspectRatio' => 'xMidYMid meet',
        ]);

        $svg = (new QRCode($options))->render($text);

        // tira a declaracao xml do topo: dentro de uma pagina html ela nao serve (e atrapalha)
        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }
}
