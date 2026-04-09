<?php

namespace Webkul\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Webkul\Inventory\Database\Factories\MoveLineFactory;
use Webkul\Inventory\Enums\MoveState;
use Webkul\Partner\Models\Partner;
use Webkul\Security\Models\User;
use Webkul\Inventory\Enums\LocationType;
use Webkul\Support\Models\Company;
use Webkul\Support\Models\UOM;

class MoveLine extends Model
{
    use HasFactory;

    protected $table = 'inventories_move_lines';

    protected $fillable = [
        'lot_name',
        'state',
        'reference',
        'picking_description',
        'qty',
        'uom_qty',
        'is_picked',
        'scheduled_at',
        'move_id',
        'operation_id',
        'product_id',
        'uom_id',
        'package_id',
        'result_package_id',
        'package_level_id',
        'lot_id',
        'partner_id',
        'source_location_id',
        'destination_location_id',
        'company_id',
        'creator_id',
    ];

    protected $casts = [
        'state'             => MoveState::class,
        'is_picked'         => 'boolean',
        'scheduled_at'      => 'datetime',
    ];

    public function move(): BelongsTo
    {
        return $this->belongsTo(Move::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UOM::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function resultPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function packageLevel(): BelongsTo
    {
        return $this->belongsTo(PackageLevel::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): MoveLineFactory
    {
        return MoveLineFactory::new();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($moveLine) {
            $moveLine->creator_id ??= Auth::id();

            $moveLine->company_id ??= $moveLine->move?->company_id;

            $moveLine->computeState();
        });

        static::saving(function ($moveLine) {
            $moveLine->computeOperationId();

            $moveLine->computeReference();

            $moveLine->computePickingDescription();

            $moveLine->computeProductId();

            $moveLine->computePartnerId();

            $moveLine->computeUOMId();

            $moveLine->computeIsPicked();

            $moveLine->computeSourceLocationId();

            $moveLine->computeDestinationLocationId();

            $moveLine->computeScheduledAt();
        });
    }

    public function computeOperationId()
    {
        $this->operation_id ??= $this->move?->operation_id;
    }

    public function computeState()
    {
        $this->state ??= $this->move?->state;
    }

    public function computeReference()
    {
        $this->reference ??= $this->move?->reference;
    }

    public function computePickingDescription()
    {
        $this->picking_description ??= $this->move?->description_picking;
    }

    public function computePartnerId()
    {
        $this->partner_id ??= $this->move?->partner_id;
    }

    public function computeProductId()
    {
        $this->product_id ??= $this->move?->product_id;
    }

    public function computeUOMId()
    {
        $this->uom_id ??= $this->product?->uom_id;
    }

    public function computeIsPicked()
    {
        $this->is_picked ??= $this->move?->is_picked;
    }

    public function computeSourceLocationId()
    {
        $this->source_location_id ??= $this->move?->source_location_id;
    }

    public function computeDestinationLocationId()
    {
        $this->destination_location_id ??= $this->move?->destination_location_id;
    }

    public function computeScheduledAt()
    {
        $this->scheduled_at ??= $this->move?->scheduled_at ?? now();
    }

    public function transferInventories()
    {
        $sourceQuantity = ProductQuantity::where('product_id', $this->product_id)
            ->where('location_id', $this->source_location_id)
            ->where('lot_id', $this->lot_id)
            ->where('package_id', $this->package_id)
            ->first();

        if ($sourceQuantity) {
            $remainingQty = $sourceQuantity->quantity - $this->uom_qty;

            if ($remainingQty == 0) {
                $sourceQuantity->delete();
            } else {
                $reservedQty = $this->calculateReservedQty($this->sourceLocation, $this->uom_qty);

                $sourceQuantity->update([
                    'quantity'                => $remainingQty,
                    'reserved_quantity'       => $sourceQuantity->reserved_quantity - $reservedQty,
                    'inventory_diff_quantity' => $sourceQuantity->inventory_diff_quantity + $this->uom_qty,
                ]);
            }
        } else {
            ProductQuantity::create([
                'product_id'              => $this->product_id,
                'location_id'             => $this->source_location_id,
                'lot_id'                  => $this->lot_id,
                'package_id'              => $this->package_id,
                'quantity'                => -$this->uom_qty,
                'inventory_diff_quantity' => $this->uom_qty,
                'company_id'              => $this->sourceLocation->company_id,
                'incoming_at'             => now(),
            ]);
        }

        $destinationQuantity = ProductQuantity::where('product_id', $this->product_id)
            ->where('location_id', $this->destination_location_id)
            ->where('lot_id', $this->lot_id)
            ->where('package_id', $this->result_package_id)
            ->first();

        $reservedQty = $this->calculateReservedQty($this->destinationLocation, $this->uom_qty);

        if ($destinationQuantity) {
            $destinationQuantity->update([
                'quantity'                => $destinationQuantity->quantity + $this->uom_qty,
                'reserved_quantity'       => $destinationQuantity->reserved_quantity + $reservedQty,
                'inventory_diff_quantity' => $destinationQuantity->inventory_diff_quantity - $this->uom_qty,
            ]);
        } else {
            ProductQuantity::create([
                'product_id'              => $this->product_id,
                'location_id'             => $this->destination_location_id,
                'package_id'              => $this->result_package_id,
                'lot_id'                  => $this->lot_id,
                'quantity'                => $this->uom_qty,
                'reserved_quantity'       => $reservedQty,
                'inventory_diff_quantity' => -$this->uom_qty,
                'incoming_at'             => now(),
                'company_id'              => $this->destinationLocation->company_id,
            ]);
        }

        if ($this->result_package_id && $this->resultPackage) {
            $this->resultPackage->update([
                'location_id' => $this->destination_location_id,
                'pack_date'   => now(),
            ]);
        }

        if ($this->lot_id && $this->lot) {
            $this->lot->update([
                'location_id' => $this->lot->total_quantity >= $this->uom_qty
                    ? $this->destination_location_id
                    : null,
            ]);
        }
    }

    private function calculateReservedQty($location, $qty): int
    {
        if ($location->type === LocationType::INTERNAL && ! $location->is_stock_location) {
            return $qty;
        }

        return 0;
    }
}
