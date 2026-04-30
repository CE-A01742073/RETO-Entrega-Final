<?php
header('Content-Type: image/png');

$width = 400;
$height = 200;

$image = imagecreatetruecolor($width, $height);

// Colores
$bg1 = imagecolorallocate($image, 0, 73, 118); 
$bg2 = imagecolorallocate($image, 0, 153, 216); 
$white = imagecolorallocate($image, 255, 255, 255);

// Gradiente
for ($i = 0; $i < $height; $i++) {
    $r = 0;
    $g = 73 + ($i * 80 / $height);
    $b = 118 + ($i * 98 / $height);
    $color = imagecolorallocate($image, $r, $g, $b);
    imageline($image, 0, $i, $width, $i, $color);
}

// Ícono de libro simple
$centerX = $width / 2;
$centerY = $height / 2;

// Rectángulo del libro
imagerectangle($image, $centerX - 40, $centerY - 30, $centerX + 40, $centerY + 30, $white);
imagerectangle($image, $centerX - 38, $centerY - 28, $centerX + 38, $centerY + 28, $white);

// Líneas del libro
imageline($image, $centerX, $centerY - 30, $centerX, $centerY + 30, $white);
imageline($image, $centerX - 20, $centerY - 10, $centerX - 5, $centerY - 10, $white);
imageline($image, $centerX + 5, $centerY - 10, $centerX + 20, $centerY - 10, $white);
imageline($image, $centerX - 20, $centerY, $centerX - 5, $centerY, $white);
imageline($image, $centerX + 5, $centerY, $centerX + 20, $centerY, $white);
imageline($image, $centerX - 20, $centerY + 10, $centerX - 5, $centerY + 10, $white);
imageline($image, $centerX + 5, $centerY + 10, $centerX + 20, $centerY + 10, $white);

imagepng($image);
imagedestroy($image);
?>