<?php
require_once __DIR__ . '/../vendor/autoload.php';
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
function qr_svg(string $data, int $size = 240): string {
    return (new Writer(new ImageRenderer(new RendererStyle($size), new SvgImageBackEnd())))->writeString($data);
}
