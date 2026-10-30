<?php

namespace App\Service\Sports;

use App\Models\Sports\Liga;
use App\Models\Sports\Partido;
use Illuminate\Database\Eloquent\Collection;

class SportsCatalogService
{
    public function ligasActivas(): Collection
    {
        return Liga::query()->where('activa', true)->orderBy('nombre')->get();
    }

    public function partidosProximos(int $limite = 100): Collection
    {
        return Partido::query()
            ->with(['local', 'visitante', 'jornada.temporada.liga', 'estado'])
            ->where('fecha_hora', '>=', now())
            ->orderBy('fecha_hora')
            ->limit(min(max($limite, 1), 250))
            ->get();
    }

    public function partido(int $id): ?Partido
    {
        return Partido::query()->with(['local', 'visitante', 'jornada.temporada.liga', 'estado'])->find($id);
    }
}
