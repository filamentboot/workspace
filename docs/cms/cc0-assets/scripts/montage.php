<?php

/**
 * 把候选缩略图拼成网格供人眼挑选（无 ImageMagick，用 GD）
 *
 * 每格左上角写序号，序号对应 candidates.json 里的 index，挑完按序号回填。
 *
 * 用法：php montage.php <slug 前缀> <输出文件>
 *   php montage.php cases sheet-cases.jpg
 */
$prefix = $argv[1] ?? 'cases';
$output = $argv[2] ?? ('sheet-'.$prefix.'.jpg');

$manifest = json_decode(file_get_contents(dirname(__DIR__).'/candidates.json'), true);

$cellW  = 300;
$cellH  = 200;
$pad    = 6;
$labelH = 22;

// 只取属于该前缀的 slug
$groups = array_filter(
    $manifest,
    fn (string $slug): bool => str_starts_with($slug, $prefix.'/'),
    ARRAY_FILTER_USE_KEY
);

// 可选：只取该前缀下的一段 slug，避免联系表过高看不清
$offset = isset($argv[3]) ? (int) $argv[3] : 0;
$limit  = isset($argv[4]) ? (int) $argv[4] : count($groups);
$groups = array_slice($groups, $offset, $limit, true);

if ($groups === []) {
    exit("没有匹配 {$prefix} 的候选\n");
}

$cols = 6;
$rows = 0;
foreach ($groups as $items) {
    $rows += (int) ceil(max(1, count($items)) / $cols);
    $rows += 0; // 每个 slug 另占一行标题，下面单独算
}
$titleH = 26;

$sheetW = $cols * ($cellW + $pad) + $pad;
$sheetH = $pad;
foreach ($groups as $items) {
    $sheetH += $titleH + (int) ceil(max(1, count($items)) / $cols) * ($cellH + $labelH + $pad);
}

$sheet = imagecreatetruecolor($sheetW, $sheetH);
$bg    = imagecolorallocate($sheet, 24, 24, 27);
$fg    = imagecolorallocate($sheet, 240, 240, 245);
$dim   = imagecolorallocate($sheet, 130, 200, 255);
imagefill($sheet, 0, 0, $bg);

$y = $pad;

foreach ($groups as $slug => $items) {
    imagestring($sheet, 5, $pad, $y + 6, $slug.'  ('.count($items).')', $dim);
    $y += $titleH;

    foreach ($items as $n => $item) {
        $col = $n % $cols;
        $row = intdiv($n, $cols);

        $x  = $pad + $col * ($cellW + $pad);
        $cy = $y + $row * ($cellH + $labelH + $pad);

        $src = @imagecreatefromjpeg($item['thumb_file']);
        if ($src !== false) {
            $sw = imagesx($src);
            $sh = imagesy($src);

            // 等比缩放塞进格子（contain，不裁切，方便判断构图）
            $scale = min($cellW / $sw, $cellH / $sh);
            $dw    = (int) ($sw * $scale);
            $dh    = (int) ($sh * $scale);

            imagecopyresampled(
                $sheet,
                $src,
                $x + intdiv($cellW - $dw, 2),
                $cy + intdiv($cellH - $dh, 2),
                0,
                0,
                $dw,
                $dh,
                $sw,
                $sh
            );
            imagedestroy($src);
        }

        $label = sprintf('#%d  %dx%d  %s', $item['index'], $item['width'], $item['height'], $item['license']);
        imagestring($sheet, 3, $x + 2, $cy + $cellH + 4, $label, $fg);
    }

    $y += (int) ceil(max(1, count($items)) / $cols) * ($cellH + $labelH + $pad);
}

imagejpeg($sheet, dirname(__DIR__).'/'.$output, 88);
imagedestroy($sheet);

echo "写入 {$output}  ({$sheetW}x{$sheetH})\n";
