<?php

namespace App\Services;

use App\Models\Location;

/**
 * 計算儲位的完整路徑與子孫節點。
 * 儲位數量通常不大，一次載入全表在記憶體計算；量大時可改用 materialized path 欄位。
 */
class LocationPaths
{
    /** @var array<int, Location>|null */
    private ?array $byId = null;

    /** @return array<int, string> id => 「A廠 / 北區 / 1棟 / 2F / R-01」 */
    public function all(): array
    {
        $paths = [];
        foreach ($this->nodes() as $id => $node) {
            $paths[$id] = $this->path($id);
        }

        return $paths;
    }

    public function path(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }
        $nodes = $this->nodes();
        $names = [];
        for ($node = $nodes[$id] ?? null; $node; $node = $nodes[$node->parent_id] ?? null) {
            array_unshift($names, $node->name);
        }

        return implode(' / ', $names);
    }

    /** 自己與所有子孫節點的 id */
    public function descendantIds(int $id): array
    {
        $childrenOf = [];
        foreach ($this->nodes() as $node) {
            $childrenOf[$node->parent_id ?? 0][] = $node->id;
        }

        $ids = [];
        $queue = [$id];
        while ($queue) {
            $current = array_shift($queue);
            $ids[] = $current;
            array_push($queue, ...($childrenOf[$current] ?? []));
        }

        return $ids;
    }

    private function nodes(): array
    {
        return $this->byId ??= Location::orderBy('code')->get(['id', 'parent_id', 'name'])->keyBy('id')->all();
    }
}
