<?php

namespace Webkul\Manufacturing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Inventory\Enums\DeliveryStep;
use Webkul\Inventory\Enums\ReceptionStep;
use Webkul\Inventory\Models\Location;
use Webkul\Inventory\Models\OperationType;
use Webkul\Inventory\Models\Route;
use Webkul\Inventory\Models\Rule;
use Webkul\Inventory\Models\Warehouse as BaseWarehouse;

class Warehouse extends BaseWarehouse
{
    public function __construct(array $attributes = [])
    {
        $this->mergeFillable([
            'manufacture_steps',
            'manufacture_to_resupply',
            'pbm_loc_id',
            'sam_loc_id',
            'manufacture_pull_id',
            'manufacture_mto_pull_id',
            'pbm_mto_pull_id',
            'sam_rule_id',
            'manu_type_id',
            'pbm_type_id',
            'sam_type_id',
            'pbm_route_id',
        ]);

        $this->mergeCasts([
            'reception_steps'         => ReceptionStep::class,
            'delivery_steps'          => DeliveryStep::class,
            'manufacture_to_resupply' => 'boolean',
        ]);

        parent::__construct($attributes);
    }

    public function manufacturePull(): BelongsTo
    {
        return $this->belongsTo(Rule::class, 'manufacture_pull_id');
    }

    public function manufactureMtoPull(): BelongsTo
    {
        return $this->belongsTo(Rule::class, 'manufacture_mto_pull_id');
    }

    public function pbmMtoPull(): BelongsTo
    {
        return $this->belongsTo(Rule::class, 'pbm_mto_pull_id');
    }

    public function samRule(): BelongsTo
    {
        return $this->belongsTo(Rule::class, 'sam_rule_id');
    }

    public function manuType(): BelongsTo
    {
        return $this->belongsTo(OperationType::class, 'manu_type_id');
    }

    public function pbmType(): BelongsTo
    {
        return $this->belongsTo(OperationType::class, 'pbm_type_id');
    }

    public function samType(): BelongsTo
    {
        return $this->belongsTo(OperationType::class, 'sam_type_id');
    }

    public function pbmRoute(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'pbm_route_id');
    }

    public function pbmLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pbm_loc_id');
    }

    public function samLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'sam_loc_id');
    }
}
