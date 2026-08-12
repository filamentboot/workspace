<?php

return [
    'official_market_index_url' => env(
        'PLUGIN_PLATFORM_OFFICIAL_MARKET_INDEX_URL',
        rtrim((string) env('APP_URL', 'http://filamentboot.local'), '/').'/plugin-market/index.json',
    ),
];
