<?php

namespace Webkul\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Product\Models\Product as BaseProduct;
use Webkul\Field\Traits\HasCustomFields;
use Webkul\Inventory\Database\Factories\ProductFactory;
use Webkul\Inventory\Enums\LocationType;
use Webkul\Inventory\Enums\ProductTracking;
use Webkul\Inventory\Enums\MoveState;
use Webkul\Inventory\Enums\OperationType as OperationTypeEnum;
use Webkul\Security\Models\User;

class Product extends BaseProduct
{
    use HasCustomFields;

    public function __construct(array $attributes = [])
    {
        $this->mergeFillable([
            'sale_delay',
            'tracking',
            'description_picking',
            'description_pickingout',
            'description_pickingin',
            'is_storable',
            'expiration_time',
            'use_time',
            'removal_time',
            'alert_time',
            'use_expiration_date',
            'responsible_id',
        ]);

        $this->mergeCasts([
            'tracking'            => ProductTracking::class,
            'use_expiration_date' => 'boolean',
            'is_storable'         => 'boolean',
        ]);

        parent::__construct($attributes);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'inventories_product_routes', 'product_id', 'route_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function quantities(): HasMany
    {
        if ($this->is_configurable) {
            return $this->hasMany(ProductQuantity::class)
                ->orWhereIn('product_id', $this->variants()->pluck('id'));
        } else {
            return $this->hasMany(ProductQuantity::class);
        }
    }

    public function moves(): HasMany
    {
        if ($this->is_configurable) {
            return $this->hasMany(Move::class)
                ->orWhereIn('product_id', $this->variants()->pluck('id'));
        } else {
            return $this->hasMany(Move::class);
        }
    }

    public function moveLines(): HasMany
    {
        if ($this->is_configurable) {
            return $this->hasMany(MoveLine::class)
                ->orWhereIn('product_id', $this->variants()->pluck('id'));
        } else {
            return $this->hasMany(MoveLine::class);
        }
    }

    public function storageCategoryCapacities(): BelongsToMany
    {
        return $this->belongsToMany(StorageCategoryCapacity::class, 'inventories_storage_category_capacities', 'storage_category_id', 'package_type_id');
    }

    public function orderPoints(): HasMany
    {
        return $this->hasMany(OrderPoint::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getOnHandQuantityAttribute(): float
    {
        return $this->quantities()
            ->whereHas('location', function ($query) {
                $query->where('type', LocationType::INTERNAL)
                    ->where('is_scrap', false);
            })
            ->sum('quantity');
    }

    public function getDescription(OperationType $operationType): ?string
    {
        return match ($operationType->type) {
            OperationTypeEnum::INCOMING => $this->description_pickingin ?? $this->description,
            OperationTypeEnum::OUTGOING => $this->description_pickingout ?? $this->name,
            OperationTypeEnum::INTERNAL => $this->description_picking ?? $this->description,
            default =>  $this->description,
        };
    }

    public function computeQuantities(
        ?int $lotId = null,
        ?int $packageId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): array {
        if ($this->is_configurable) {
            $totals = [
                'qty_available'     => 0.0,
                'free_qty'          => 0.0,
                'incoming_qty'      => 0.0,
                'outgoing_qty'      => 0.0,
                'virtual_available' => 0.0,
            ];

            foreach ($this->variants as $variant) {
                $variantQty = $variant->computeQuantities($lotId, $packageId, $fromDate, $toDate);

                foreach ($totals as $key => $_) {
                    $totals[$key] += $variantQty[$key];
                }
            }

            return array_map(fn($value) => float_round($value, precisionRounding: $this->uom->rounding), $totals);
        }

        [$domainQuantLocation, $domainMoveInLocation, $domainMoveOutLocation] = $this->getLocationFilters();

        $domainQuantity = array_merge([['product_id', '=', $this->id]], $domainQuantLocation);

        $domainMoveIn = array_merge([['product_id', '=', $this->id]], $domainMoveInLocation);

        $domainMoveOut = array_merge([['product_id', '=', $this->id]], $domainMoveOutLocation);

        $toDate = $toDate ? Carbon::parse($toDate) : null;

        $datesInThePast = $toDate && $toDate->lt(now());

        if ($lotId !== null) {
            $domainQuantity[] = ['lot_id', '=', $lotId];
        }

        if ($packageId !== null) {
            $domainQuantity[] = ['package_id', '=', $packageId];
        }

        if ($datesInThePast) {
            $domainMoveInDone  = $domainMoveIn;

            $domainMoveOutDone = $domainMoveOut;
        }

        if ($fromDate) {
            $domainMoveIn[]  = ['scheduled_at', '>=', $fromDate];

            $domainMoveOut[] = ['scheduled_at', '>=', $fromDate];
        }

        if ($toDate) {
            $domainMoveIn[]  = ['scheduled_at', '<=', $toDate];

            $domainMoveOut[] = ['scheduled_at', '<=', $toDate];
        }

        $todoStates = [MoveState::WAITING, MoveState::CONFIRMED, MoveState::ASSIGNED, MoveState::PARTIALLY_ASSIGNED];

        $movesInRes = Move::where(array_merge([['state', 'in', $todoStates]], $domainMoveIn))
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(product_qty) as total')
            ->value('total') ?? 0.0;

        $movesOutRes = Move::where(array_merge([['state', 'in', $todoStates]], $domainMoveOut))
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(product_qty) as total')
            ->value('total') ?? 0.0;

        $quantRow = ProductQuantity::where($domainQuantity)
            ->selectRaw('SUM(quantity) as quantity, SUM(reserved_quantity) as reserved_quantity')
            ->first();

        $qtyAvailableBase = $quantRow->quantity ?? 0.0;
        $reservedQuantity = $quantRow->reserved_quantity ?? 0.0;

        $movesInResPast  = 0.0;
        $movesOutResPast = 0.0;

        if ($datesInThePast) {
            $domainMoveInDone  = array_merge([['state', '=', MoveState::DONE], ['scheduled_at', '>', $toDate]], $domainMoveInDone);
            $domainMoveOutDone = array_merge([['state', '=', MoveState::DONE], ['scheduled_at', '>', $toDate]], $domainMoveOutDone);

            Move::where($domainMoveInDone)
                ->groupBy('product_id', 'uom_id')
                ->selectRaw('uom_id, SUM(quantity) as total')
                ->get()
                ->each(function ($row) use (&$movesInResPast) {
                    $movesInResPast += $row->uom->computeQuantity($row->total, $this->uom);
                });

            Move::where($domainMoveOutDone)
                ->groupBy('product_id', 'uom_id')
                ->selectRaw('uom_id, SUM(quantity) as total')
                ->get()
                ->each(function ($row) use (&$movesOutResPast) {
                    $movesOutResPast += $row->uom->computeQuantity($row->total, $this->uom);
                });
        }

        $rounding = $this->uom->rounding;

        $qtyAvailable = $datesInThePast
            ? $qtyAvailableBase - $movesInResPast + $movesOutResPast
            : $qtyAvailableBase;

        $incomingQty = float_round($movesInRes, precisionRounding: $rounding);
        $outgoingQty = float_round($movesOutRes, precisionRounding: $rounding);

        return [
            'qty_available'     => float_round($qtyAvailable, precisionRounding: $rounding),
            'free_qty'          => float_round($qtyAvailable - $reservedQuantity, precisionRounding: $rounding),
            'incoming_qty'      => $incomingQty,
            'outgoing_qty'      => $outgoingQty,
            'virtual_available' => float_round($qtyAvailable + $incomingQty - $outgoingQty, precisionRounding: $rounding),
        ];
    }

    protected function getLocationFilters(
        int|string|array|null $location = null,
        int|string|array|null $warehouse = null,
        bool $strict = false,
        array $companyIds = []
    ): array {
        $searchIds = function (string $modelClass, array $values): array {
            $ids = [];
            $names = [];

            foreach ($values as $item) {
                if (is_int($item) || ctype_digit((string) $item)) {
                    $ids[] = (int) $item;
                } else {
                    $names[] = $item;
                }
            }

            if (! empty($names)) {
                $query = $modelClass::query();

                $query->where(function (Builder $query) use ($names) {
                    foreach ($names as $name) {
                        $query->orWhere('name', 'like', '%' . $name . '%');
                    }
                });

                $ids = array_merge($ids, $query->pluck('id')->toArray());
            }

            return array_values(array_unique($ids));
        };

        if ($location !== null && ! is_array($location)) {
            $location = [$location];
        }

        if ($warehouse !== null && ! is_array($warehouse)) {
            $warehouse = [$warehouse];
        }

        if (! empty($warehouse)) {
            $warehouseIds = $searchIds(Warehouse::class, $warehouse);

            $warehouseLocationIds = Warehouse::query()
                ->whereIn('id', $warehouseIds)
                ->pluck('view_location_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (! empty($location)) {
                $locationIds = $searchIds(Location::class, $location);

                $parentPaths = Location::query()
                    ->whereIn('id', $warehouseLocationIds)
                    ->pluck('parent_path')
                    ->filter()
                    ->values()
                    ->toArray();

                $resolvedLocationIds = Location::query()
                    ->whereIn('id', $locationIds)
                    ->get(['id', 'parent_path'])
                    ->filter(function ($location) use ($parentPaths) {
                        foreach ($parentPaths as $parentPath) {
                            if (
                                ! empty($location->parent_path)
                                && str_starts_with($location->parent_path, $parentPath)
                            ) {
                                return true;
                            }
                        }

                        return false;
                    })
                    ->pluck('id')
                    ->unique()
                    ->values()
                    ->toArray();
            } else {
                $resolvedLocationIds = $warehouseLocationIds;
            }
        } else {
            if (! empty($location)) {
                $resolvedLocationIds = $searchIds(Location::class, $location);
            } else {
                $resolvedLocationIds = Warehouse::query()
                    ->whereIn('company_id', $companyIds)
                    ->pluck('view_location_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
            }
        }

        return $this->getLocationFiltersNew($resolvedLocationIds, $strict);
    }

    protected function getLocationFiltersNew(array $locationIds, bool $strict = false): array
    {
        if (empty($locationIds)) {
            return [
                [['0', '=', '1']],
                [['0', '=', '1']],
                [['0', '=', '1']],
            ];
        }

        $locations = Location::query()
            ->whereIn('id', $locationIds)
            ->get(['id', 'parent_path']);

        if ($locations->isEmpty()) {
            return [
                [['0', '=', '1']],
                [['0', '=', '1']],
                [['0', '=', '1']],
            ];
        }

        if ($strict) {
            $ids = $locations->pluck('id')->values()->toArray();

            $sourceLocationFilter = [
                ['source_location_id', 'in', $ids],
            ];

            $destinationLocationFilter = [
                ['destination_location_id', 'in', $ids],
            ];
        } else {
            $pathsDomain = [];

            foreach ($locations as $location) {
                if (! empty($location->parent_path)) {
                    $pathsDomain[] = [
                        ['parent_path', 'like', $location->parent_path . '%'],
                    ];
                }
            }

            if (empty($pathsDomain)) {
                return [
                    [['0', '=', '1']],
                    [['0', '=', '1']],
                    [['0', '=', '1']],
                ];
            }

            $sourceLocationFilter = [
                ['location_id', 'any', $pathsDomain],
            ];

            $destinationLocationFilter = [
                '|',
                '&',
                ['final_location_id', '!=', false],
                ['final_location_id', 'any', $pathsDomain],
                '&',
                ['final_location_id', '=', false],
                ['destination_location_id', 'any', $pathsDomain],
            ];
        }

        return [
            $sourceLocationFilter,
            array_merge($destinationLocationFilter, ['!'], $sourceLocationFilter),
            array_merge($sourceLocationFilter, ['!'], $destinationLocationFilter),
        ];
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
