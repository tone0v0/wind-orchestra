<?php
// generate_icons.php
$dir = __DIR__ . '/assets';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

// 1. SVGアイコン作成
$svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="512" height="512">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#1e3a8a"/>
      <stop offset="100%" stop-color="#0f172a"/>
    </linearGradient>
    <linearGradient id="gold" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#fde047"/>
      <stop offset="50%" stop-color="#eab308"/>
      <stop offset="100%" stop-color="#ca8a04"/>
    </linearGradient>
  </defs>
  
  <!-- 背景角丸 -->
  <rect width="512" height="512" rx="100" fill="url(#bg)"/>
  
  <!-- 外枠装飾 -->
  <rect x="24" y="24" width="464" height="464" rx="80" fill="none" stroke="url(#gold)" stroke-width="4" stroke-opacity="0.3"/>
  
  <!-- 音符・ホルン・トランペットモチーフのエンブレム -->
  <g transform="translate(256, 230) scale(1.15)">
    <!-- ト音記号風シンボル -->
    <path d="M-15,40 C-35,40 -50,25 -50,0 C-50,-35 -20,-75 25,-125 C30,-130 35,-128 35,-120 C35,-90 20,-60 10,-35 C25,-30 45,-15 45,15 C45,45 20,70 -15,70 C-45,70 -75,45 -75,5 C-75,-45 -40,-100 20,-150 C25,-154 32,-150 32,-145 C32,-110 5,-60 -10,-10 C5,-15 25,-10 32,5 C38,18 25,40 -15,40 Z" fill="url(#gold)" stroke="#ca8a04" stroke-width="2"/>
    <!-- サークルアクセント -->
    <circle cx="20" cy="80" r="14" fill="url(#gold)"/>
    <circle cx="-50" cy="70" r="8" fill="url(#gold)"/>
  </g>
  
  <!-- 下部テキスト -->
  <text x="256" y="435" text-anchor="middle" font-family="-apple-system, BlinkMacSystemFont, 'Hiragino Kaku Gothic ProN', sans-serif" font-size="34" font-weight="bold" fill="#ffffff" letter-spacing="4">
    大原吹奏楽
  </text>
</svg>
SVG;

file_put_contents($dir . '/icon.svg', $svg);

// 2. PHP GDでPNG生成
function createPngIcon($size, $filename) {
    if (!extension_loaded('gd')) return false;

    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);

    // 背景色（ダークネイビー）
    $navy1 = imagecolorallocate($img, 30, 58, 138);
    $navy2 = imagecolorallocate($img, 15, 23, 42);
    $gold = imagecolorallocate($img, 234, 179, 8);
    $gold_light = imagecolorallocate($img, 253, 224, 71);
    $white = imagecolorallocate($img, 255, 255, 255);

    // 単色塗りつぶし
    imagefilledrectangle($img, 0, 0, $size, $size, $navy1);

    // 丸型エンブレム枠
    $cx = (int)($size / 2);
    $cy = (int)($size / 2) - (int)($size * 0.05);
    $r = (int)($size * 0.35);

    // 金の円枠
    imagearc($img, $cx, $cy, $r * 2, $r * 2, 0, 360, $gold);
    imagearc($img, $cx, $cy, ($r * 2) - 4, ($r * 2) - 4, 0, 360, $gold_light);

    // 中央に音符モチーフを描画
    $scale = $size / 512;
    // 音符の丸
    $note_r = (int)(22 * $scale);
    imagefilledellipse($img, $cx - (int)(30 * $scale), $cy + (int)(40 * $scale), $note_r * 2, (int)($note_r * 1.5), $gold);
    imagefilledellipse($img, $cx + (int)(40 * $scale), $cy + (int)(25 * $scale), $note_r * 2, (int)($note_r * 1.5), $gold);

    // 縦棒
    $bar_w = max(2, (int)(8 * $scale));
    imagefilledrectangle($img, $cx - (int)(15 * $scale), $cy - (int)(60 * $scale), $cx - (int)(15 * $scale) + $bar_w, $cy + (int)(40 * $scale), $gold);
    imagefilledrectangle($img, $cx + (int)(55 * $scale), $cy - (int)(75 * $scale), $cx + (int)(55 * $scale) + $bar_w, $cy + (int)(25 * $scale), $gold);

    // 上の梁
    imagefilledrectangle($img, $cx - (int)(15 * $scale), $cy - (int)(75 * $scale), $cx + (int)(55 * $scale) + $bar_w, $cy - (int)(55 * $scale), $gold);

    imagepng($img, $filename);
    imagedestroy($img);
    return true;
}

createPngIcon(192, $dir . '/icon-192.png');
createPngIcon(512, $dir . '/icon-512.png');
createPngIcon(180, $dir . '/apple-touch-icon.png');

echo "Icons generated successfully!";
