<?php

namespace Webkul\Manufacturing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Inventory\Models\MoveLine as BaseMoveLine;

class MoveLine extends BaseMoveLine
{
    public function __construct(array $attributes = [])
    {
        $this->mergeFillable([
            'work_order_id',
            'order_id',
        ]);

        parent::__construct($attributes);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
