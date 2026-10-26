<?php

namespace App\Http\Controllers\Api;

use App\Enums\LocationLevel;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\LocationPaths;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    /** 扁平清單（含完整路徑），供下拉選單使用；可用 level 篩選 */
    public function index(Request $request, LocationPaths $locationPaths)
    {
        $paths = $locationPaths->all();

        return Location::orderBy('code')->get()
            ->when($request->filled('level'), fn ($c) => $c->where('level', LocationLevel::from($request->level)))
            ->map(fn (Location $l) => [...$l->toArray(), 'path' => $paths[$l->id]])
            ->values();
    }

    /** 樹狀結構 */
    public function tree()
    {
        $all = Location::orderBy('code')->get();
        $byParent = $all->groupBy(fn ($l) => $l->parent_id ?? 0);

        $build = function (int $parentId) use (&$build, $byParent) {
            return ($byParent[$parentId] ?? collect())->map(fn (Location $l) => [
                ...$l->toArray(),
                'level_label' => $l->level->label(),
                'children' => $build($l->id),
            ])->values();
        };

        return $build(0);
    }

    public function show(Location $location)
    {
        return $location->load('parent');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->assertHierarchy($data);

        return response()->json(Location::create($data), 201);
    }

    public function update(Request $request, Location $location)
    {
        $data = $this->validated($request, $location);
        $this->assertHierarchy($data, $location);
        $location->update($data);

        return $location;
    }

    public function destroy(Location $location)
    {
        if ($location->children()->exists()) {
            throw ValidationException::withMessages(['location' => '此節點下仍有子節點，請先刪除子節點']);
        }
        if ($location->isReferencedByTransactions() || $location->stocks()->where('quantity', '>', 0)->exists()) {
            throw ValidationException::withMessages(['location' => '此儲位已有作業紀錄或庫存，無法刪除']);
        }
        $location->stocks()->delete();
        $location->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Location $location = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('locations', 'code')->ignore($location)],
            'name' => ['required', 'string', 'max:100'],
            'level' => ['required', Rule::enum(LocationLevel::class)],
            'parent_id' => ['nullable', 'integer', 'exists:locations,id'],
            'type' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'numeric', 'min:0'],
            'capacity_unit' => ['nullable', 'string', 'max:20'],
            'remark' => ['nullable', 'string'],
        ], [], [
            'code' => '儲位編號', 'name' => '儲位名稱', 'level' => '階層', 'parent_id' => '上級節點', 'capacity' => '容量上限',
        ]);
    }

    /** 確認階層順序：廠區無上級；其餘的上級必須是上一層 */
    private function assertHierarchy(array $data, ?Location $self = null): void
    {
        $level = LocationLevel::from($data['level']);
        $parent = isset($data['parent_id']) ? Location::find($data['parent_id']) : null;

        if ($level === LocationLevel::Factory) {
            if ($parent) {
                throw ValidationException::withMessages(['parent_id' => '廠區為最上層，不可設定上級']);
            }
        } elseif (! $parent || $parent->level->child() !== $level) {
            $expected = collect(LocationLevel::cases())->first(fn ($c) => $c->child() === $level);
            throw ValidationException::withMessages(['parent_id' => "{$level->label()}的上級必須是{$expected->label()}"]);
        }

        if ($self && $self->level !== $level && $self->children()->exists()) {
            throw ValidationException::withMessages(['level' => '已有子節點的項目不可變更階層']);
        }
    }
}
