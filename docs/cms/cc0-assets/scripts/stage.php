<?php

/**
 * 按 selection.json 下载原图，居中裁成 3:2 / 1600px 宽，落到 staged/
 *
 * 3:2 是能同时喂饱三档转换的最小公倍形状：
 * thumb 400x300 与 card 800x600 是 4:3，og 1200x630 接近 2:1，
 * 源图 1600x1067 裁出这三档都不会放大像素。
 */
const UA       = 'filamentboot-site-demo/0.5 (https://github.com/filamentboot/filamentboot-site)';
const TARGET_W = 1600;
const TARGET_H = 1067;

$root = dirname(__DIR__);

// 合并所有轮次的候选：组名互不重叠（round1 用 cases//news/ 前缀，后续轮用 pool//fix/），
// 所以直接平铺成一张表，selection.json 里写组名即可，不必区分来自哪一轮。
$pool = [];
foreach (glob($root.'/candidates*.json') as $file) {
    $pool = array_merge($pool, json_decode(file_get_contents($file), true) ?: []);
}

if ($pool === []) {
    exit("没有候选数据，先跑 fetch.php\n");
}

$picks = json_decode(file_get_contents($root.'/selection.json'), true);

$stageDir   = $root.'/staged';
$provenance = [];

foreach ($picks as $slot => [$group, $index]) {
    $items = $pool[$group] ?? [];

    $hit = null;
    foreach ($items as $item) {
        if ((int) $item['index'] === (int) $index) {
            $hit = $item;
            break;
        }
    }

    if ($hit === null) {
        printf("!! %-42s 找不到 %s #%d\n", $slot, $group, $index);

        continue;
    }

    $dest = $stageDir.'/'.$slot.'.jpg';
    if (! is_dir(dirname($dest))) {
        mkdir(dirname($dest), 0755, true);
    }

    // 下载原图（可能很大，Commons 允许，给足超时）
    $ch = curl_init($hit['full']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => UA,
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $bytes = curl_exec($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || $bytes === false) {
        printf("!! %-42s 下载失败 HTTP %s\n", $slot, $code);

        continue;
    }

    $src = @imagecreatefromstring((string) $bytes);
    if ($src === false) {
        printf("!! %-42s 解码失败\n", $slot);

        continue;
    }

    $sw = imagesx($src);
    $sh = imagesy($src);

    // cover 裁切：先按短边铺满目标比例，再从中心裁
    $scale = max(TARGET_W / $sw, TARGET_H / $sh);
    $rw    = (int) ceil($sw * $scale);
    $rh    = (int) ceil($sh * $scale);

    $resized = imagecreatetruecolor($rw, $rh);
    imagecopyresampled($resized, $src, 0, 0, 0, 0, $rw, $rh, $sw, $sh);
    imagedestroy($src);

    $out = imagecreatetruecolor(TARGET_W, TARGET_H);
    imagecopy($out, $resized, 0, 0, intdiv($rw - TARGET_W, 2), intdiv($rh - TARGET_H, 2), TARGET_W, TARGET_H);
    imagedestroy($resized);

    imagejpeg($out, $dest, 86);
    imagedestroy($out);

    $provenance[$slot] = [
        'title'   => $hit['title'],
        'license' => $hit['license'],
        'author'  => trim($hit['author']),
        'page'    => $hit['page'],
        'source'  => $sw.'x'.$sh,
    ];

    printf("%-42s %-14s %s\n", $slot, $hit['license'], substr($hit['title'], 5, 52));
    usleep(200000);
}

file_put_contents(
    $root.'/provenance.json',
    json_encode($provenance, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

printf("\n%d 张落到 staged/，来源写入 provenance.json\n", count($provenance));
