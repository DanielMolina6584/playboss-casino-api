<?php

namespace App\Service\Sports;

use App\Models\Market\Mercado;
use Illuminate\Database\Eloquent\Collection;

class MarketCatalogService
{
    public function paraPartido(int $partidoId): Collection
    {
        return Mercado::query()->where('match_id', $partidoId)->where('status', 'ACTIVE')->get();
    }
}
