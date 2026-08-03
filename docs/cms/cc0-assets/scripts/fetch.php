<?php

/**
 * 从 Wikimedia Commons 拉候选封面图（仅 CC0 / 公有领域）
 *
 * 只收无署名义务的许可：站点是真实公司官网，CC-BY 要求可见署名，
 * 那是产品层面的改动（要加字段、要在前台渲染），不在本次范围内。
 */
const UA  = 'filamentboot-site-demo/0.5 (https://github.com/filamentboot/filamentboot-site)';
const API = 'https://commons.wikimedia.org/w/api.php';

/** 无署名义务的许可白名单（LicenseShortName 原样匹配） */
const FREE_LICENSES = ['CC0', 'Public domain', 'No restrictions', 'PDM', 'CC PDM 1.0'];

const MIN_WIDTH = 1400;

/** @return array<int, array<string, mixed>> */
function commonsSearch(string $term, int $limit = 20): array
{
    $url = API.'?'.http_build_query([
        'action'        => 'query',
        'format'        => 'json',
        'formatversion' => '2',
        'generator'     => 'search',
        'gsrsearch'     => 'filetype:bitmap '.$term,
        'gsrnamespace'  => '6',
        'gsrlimit'      => (string) $limit,
        'prop'          => 'imageinfo',
        'iiprop'        => 'url|size|extmetadata',
        'iiurlwidth'    => '420',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => UA,
        CURLOPT_TIMEOUT        => 40,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);

    $pages = json_decode((string) $body, true)['query']['pages'] ?? [];

    $out = [];
    foreach ($pages as $page) {
        $ii = $page['imageinfo'][0] ?? null;
        if ($ii === null) {
            continue;
        }

        $license = $ii['extmetadata']['LicenseShortName']['value'] ?? '';

        if (! in_array($license, FREE_LICENSES, true)) {
            continue;
        }
        if (($ii['width'] ?? 0) < MIN_WIDTH) {
            continue;
        }

        $out[] = [
            'title'   => $page['title'],
            'license' => $license,
            'width'   => $ii['width'],
            'height'  => $ii['height'],
            'thumb'   => $ii['thumburl'] ?? '',
            'full'    => $ii['url'],
            'page'    => $ii['descriptionurl'] ?? '',
            'author'  => strip_tags($ii['extmetadata']['Artist']['value'] ?? ''),
        ];
    }

    return $out;
}

function download(string $url, string $dest): bool
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => UA,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || $body === false || strlen((string) $body) < 2000) {
        return false;
    }

    return file_put_contents($dest, $body) !== false;
}

// slug => 检索词（一个 slug 可给多个词，按顺序合并去重）
$queries = json_decode(file_get_contents(dirname(__DIR__).'/queries.json'), true);

$baseDir  = dirname(__DIR__).'/candidates';
$manifest = [];

foreach ($queries as $slug => $terms) {
    $dir = $baseDir.'/'.$slug;
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $seen  = [];
    $items = [];

    foreach ((array) $terms as $term) {
        foreach (commonsSearch($term) as $hit) {
            if (isset($seen[$hit['title']])) {
                continue;
            }
            $seen[$hit['title']] = true;
            $items[]             = $hit;
        }
        usleep(300000); // 对 Commons 客气点
    }

    // 大图优先，最多留 12 张供挑选
    usort($items, fn (array $a, array $b): int => $b['width'] <=> $a['width']);
    $items = array_slice($items, 0, 18);

    $kept = [];
    foreach ($items as $i => $hit) {
        $file = sprintf('%s/%02d.jpg', $dir, $i + 1);

        if ($hit['thumb'] === '' || ! download($hit['thumb'], $file)) {
            continue;
        }

        $hit['index']      = $i + 1;
        $hit['thumb_file'] = $file;
        $kept[]            = $hit;
        usleep(150000);
    }

    $manifest[$slug] = $kept;
    printf("%-42s %2d 张候选\n", $slug, count($kept));
}

file_put_contents(
    dirname(__DIR__).'/candidates.json',
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
echo "\n候选清单写入 candidates.json\n";
