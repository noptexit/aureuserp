<?php

namespace Webkul\Manufacturing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Inventory\Models\Move as BaseMove;

class Move extends BaseMove
{
    public function __construct(array $attributes = [])
    {
        $this->mergeFillable([
            'created_order_id',
            'order_id',
            'raw_material_order_id',
            'unbuild_order_id',
            'consume_unbuild_order_id',
            'work_order_id',
            'bom_line_id',
            'byproduct_id',
            'order_finished_lot_id',
            'cost_share',
            'manual_consumption',
        ]);

        $this->mergeCasts([
            'cost_share'         => 'decimal:4',
            'manual_consumption' => 'boolean',
        ]);

        parent::__construct($attributes);
    }

    public function createdOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'created_order_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function rawMaterialOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'raw_material_order_id');
    }

    public function unbuildOrder(): BelongsTo
    {
        return $this->belongsTo(UnbuildOrder::class, 'unbuild_order_id');
    }

    public function consumeUnbuildOrder(): BelongsTo
    {
        return $this->belongsTo(UnbuildOrder::class, 'consume_unbuild_order_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function bomLine(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterialLine::class, 'bom_line_id');
    }

    public function byproduct(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterialByproduct::class, 'byproduct_id');
    }

    public function orderFinishedLot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'order_finished_lot_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MoveLine::class, 'move_id');
    }
}
