<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class OfficialMarketIndexController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'name'         => 'Filamentboot Official Market',
            'generated_at' => now()->toIso8601String(),
            'entries'      => config('official-market.entries', []),
        ]);
    }
}
