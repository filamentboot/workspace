{{--
 * XML 站点地图索引模板（跨主题共享，主题无关）
 *
 * $sitemaps：分片地址列表，形如 [['loc' => 'https://…/sitemap-content.xml'], …]
 *
 * 索引里**不写 lastmod**：它得是「该分片内最新一条内容的修改时间」，
 * 算它要把每个分片的内容全查一遍——为了一个搜索引擎并不要求的字段，
 * 把索引的成本抬到和分片本身一样高，不划算。
 --}}
<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($sitemaps as $sitemap)
    <sitemap>
        <loc>{{ $sitemap['loc'] }}</loc>
    </sitemap>
@endforeach
</sitemapindex>
