<?php

namespace Webkul\Manufacturing\Observers;

use Webkul\Inventory\Models\Move as InventoryMove;
use Webkul\Manufacturing\Models\Move as ManufacturingMove;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class MoveObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(InventoryMove $move): void
    {
        $move = ManufacturingMove::find($move->id);

        if (! $move->raw_material_order_id) {
            return;
        }
    }

    public function deleted(InventoryMove $move): void {}

    public function restored(InventoryMove $move): void {}
}
