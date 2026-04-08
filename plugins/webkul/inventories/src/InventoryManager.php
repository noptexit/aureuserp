<?php

namespace Webkul\Inventory;

use Illuminate\Support\Facades\Auth;
use Webkul\Inventory\Enums\CreateBackorder;
use Webkul\Inventory\Enums\LocationType;
use Webkul\Inventory\Enums\MoveState;
use Webkul\Inventory\Enums\OperationState;
use Webkul\Inventory\Enums\ProcureMethod;
use Webkul\Inventory\Enums\ProductTracking;
use Webkul\Inventory\Enums\RuleAction;
use Webkul\Inventory\Enums\RuleAuto;
use Webkul\Inventory\Enums\GroupPropagation;
use Webkul\Inventory\Filament\Clusters\Operations\Resources\OperationResource;
use Webkul\Inventory\Models\Location;
use Webkul\Inventory\Models\Move;
use Webkul\Inventory\Models\MoveLine;
use Webkul\Inventory\Models\Operation;
use Webkul\Inventory\Models\Product;
use Webkul\Inventory\Models\ProductQuantity;
use Webkul\Inventory\Models\Rule;
use Webkul\PluginManager\Package;
use Webkul\Purchase\Facades\PurchaseOrder as PurchaseOrderFacade;
use Webkul\Sale\Facades\SaleOrder as SaleFacade;

class InventoryManager
{
    public function checkTransferAvailability(Operation $record): Operation
    {
        return $this->computeTransfer($record);
    }

    public function todoTransfer(Operation $record): Operation
    {
        return $this->computeTransfer($record);
    }

    public function validateTransfer(Operation $record): Operation
    {
        $record = $this->computeTransfer($record);

        // Update each move and its lines, adjusting quantities.
        foreach ($record->moves as $move) {
            $this->validateTransferMove($move);
        }

        $record = $this->computeTransferState($record);

        $record->save();

        if (Package::isPluginInstalled('purchases')) {
            foreach ($record->purchaseOrders as $purchaseOrder) {
                PurchaseOrderFacade::computePurchaseOrder($purchaseOrder);
            }
        }

        if (Package::isPluginInstalled('sales')) {
            if ($record->saleOrder) {
                SaleFacade::computeSaleOrder($record->saleOrder);
            }
        }

        $this->applyPushRules($record->moves);

        return $record;
    }

    public function validateTransferMove(Move $move): Move
    {
        $move->update([
            'state'     => MoveState::DONE,
            'is_picked' => true,
        ]);

        foreach ($move->lines()->get() as $moveLine) {
            $this->validateTransferMoveLine($moveLine);
        }

        return $move;
    }

    public function validateTransferMoveLine(MoveLine $moveLine): MoveLine
    {
        $moveLine->update(['state' => MoveState::DONE]);

        // Process source quantity
        $sourceQuantity = ProductQuantity::where('product_id', $moveLine->product_id)
            ->where('location_id', $moveLine->source_location_id)
            ->where('lot_id', $moveLine->lot_id)
            ->where('package_id', $moveLine->package_id)
            ->first();

        if ($sourceQuantity) {
            $remainingQty = $sourceQuantity->quantity - $moveLine->uom_qty;

            if ($remainingQty == 0) {
                $sourceQuantity->delete();
            } else {
                $reservedQty = $this->calculateReservedQty($moveLine->sourceLocation, $moveLine->uom_qty);

                $sourceQuantity->update([
                    'quantity'                => $remainingQty,
                    'reserved_quantity'       => $sourceQuantity->reserved_quantity - $reservedQty,
                    'inventory_diff_quantity' => $sourceQuantity->inventory_diff_quantity + $moveLine->uom_qty,
                ]);
            }
        } else {
            ProductQuantity::create([
                'product_id'              => $moveLine->product_id,
                'location_id'             => $moveLine->source_location_id,
                'lot_id'                  => $moveLine->lot_id,
                'package_id'              => $moveLine->package_id,
                'quantity'                => -$moveLine->uom_qty,
                'inventory_diff_quantity' => $moveLine->uom_qty,
                'company_id'              => $moveLine->sourceLocation->company_id,
                'creator_id'              => Auth::id(),
                'incoming_at'             => now(),
            ]);
        }

        // Process destination quantity
        $destinationQuantity = ProductQuantity::where('product_id', $moveLine->product_id)
            ->where('location_id', $moveLine->destination_location_id)
            ->where('lot_id', $moveLine->lot_id)
            ->where('package_id', $moveLine->result_package_id)
            ->first();

        $reservedQty = $this->calculateReservedQty($moveLine->destinationLocation, $moveLine->uom_qty);

        if ($destinationQuantity) {
            $destinationQuantity->update([
                'quantity'                => $destinationQuantity->quantity + $moveLine->uom_qty,
                'reserved_quantity'       => $destinationQuantity->reserved_quantity + $reservedQty,
                'inventory_diff_quantity' => $destinationQuantity->inventory_diff_quantity - $moveLine->uom_qty,
            ]);
        } else {
            ProductQuantity::create([
                'product_id'              => $moveLine->product_id,
                'location_id'             => $moveLine->destination_location_id,
                'package_id'              => $moveLine->result_package_id,
                'lot_id'                  => $moveLine->lot_id,
                'quantity'                => $moveLine->uom_qty,
                'reserved_quantity'       => $reservedQty,
                'inventory_diff_quantity' => -$moveLine->uom_qty,
                'incoming_at'             => now(),
                'creator_id'              => Auth::id(),
                'company_id'              => $moveLine->destinationLocation->company_id,
            ]);
        }

        // Update package and lot if applicable.
        if ($moveLine->result_package_id && $moveLine->resultPackage) {
            $moveLine->resultPackage->update([
                'location_id' => $moveLine->destination_location_id,
                'pack_date'   => now(),
            ]);
        }

        if ($moveLine->lot_id && $moveLine->lot) {
            $moveLine->lot->update([
                'location_id' => $moveLine->lot->total_quantity >= $moveLine->uom_qty
                    ? $moveLine->destination_location_id
                    : null,
            ]);
        }

        return $moveLine;
    }

    public function cancelTransfer(Operation $record): Operation
    {
        foreach ($record->moves as $move) {
            $move->update([
                'state'        => MoveState::CANCELED,
                'quantity'     => 0,
            ]);

            $move->lines()->delete();
        }

        $record = $this->computeTransferState($record);

        $record->save();

        return $record;
    }

    public function returnTransfer(Operation $record): Operation
    {
        $newOperation = $record->replicate()->fill([
            'state'                   => OperationState::DRAFT,
            'origin'                  => 'Return of '.$record->name,
            'operation_type_id'       => $record->operationType->returnOperationType?->id ?? $record->operation_type_id,
            'source_location_id'      => $record->destination_location_id,
            'destination_location_id' => $record->source_location_id,
            'return_id'               => $record->id,
            'user_id'                 => Auth::id(),
            'creator_id'              => Auth::id(),
        ]);

        $newOperation->save();

        foreach ($record->moves as $move) {
            $newMove = $move->replicate()->fill([
                'operation_id'            => $newOperation->id,
                'reference'               => $newOperation->name,
                'state'                   => MoveState::DRAFT,
                'is_refund'               => true,
                'product_qty'             => $move->product_qty,
                'product_uom_qty'         => $move->product_uom_qty,
                'source_location_id'      => $move->destination_location_id,
                'destination_location_id' => $move->source_location_id,
                'origin_returned_move_id' => $move->id,
                'operation_type_id'       => $newOperation->operation_type_id,
            ]);

            $newMove->save();
        }

        $newOperation->refresh();

        $newOperation = $this->computeTransfer($newOperation);

        if (Package::isPluginInstalled('purchases')) {
            $newOperation->purchaseOrders()->attach($record->purchaseOrders->pluck('id'));
        }

        $url = OperationResource::getUrl('view', ['record' => $record]);

        $newOperation->addMessage([
            'body' => "This transfer has been created from <a href=\"{$url}\" target=\"_blank\" class=\"text-primary-600 dark:text-primary-400\">{$record->name}</a>.",
            'type' => 'comment',
        ]);

        $url = OperationResource::getUrl('view', ['record' => $newOperation]);

        $record->addMessage([
            'body' => "The return <a href=\"{$url}\" target=\"_blank\" class=\"text-primary-600 dark:text-primary-400\">{$newOperation->name}</a> has been created.",
            'type' => 'comment',
        ]);

        return $newOperation;
    }

    /**
     * Process back order for the operation.
     */
    public function createBackOrder(Operation $record): void
    {
        if (! $this->canCreateBackOrder($record)) {
            return;
        }

        $newOperation = $record->replicate()->fill([
            'state'         => OperationState::DRAFT,
            'origin'        => $record->origin ?? $record->name,
            'back_order_id' => $record->id,
            'user_id'       => Auth::id(),
            'creator_id'    => Auth::id(),
        ]);

        $newOperation->save();

        foreach ($record->moves as $move) {
            if ($move->product_uom_qty <= $move->quantity) {
                continue;
            }

            $remainingQty = round($move->product_uom_qty - $move->quantity, 4);

            $newMove = $move->replicate()->fill([
                'operation_id'    => $newOperation->id,
                'reference'       => $newOperation->name,
                'state'           => MoveState::DRAFT,
                'product_qty'     => $move->uom->computeQuantity($remainingQty, $move->product->uom, true, 'HALF-UP'),
                'product_uom_qty' => $remainingQty,
                'quantity'        => $remainingQty,
            ]);

            $newMove->save();
        }

        $newOperation->refresh();

        $newOperation = $this->computeTransfer($newOperation);

        if (Package::isPluginInstalled('purchases')) {
            $newOperation->purchaseOrders()->attach($record->purchaseOrders->pluck('id'));

            foreach ($record->purchaseOrders as $purchaseOrder) {
                PurchaseOrderFacade::computePurchaseOrder($purchaseOrder);
            }
        }

        $url = OperationResource::getUrl('view', ['record' => $record]);

        $newOperation->addMessage([
            'body' => "This transfer has been created from <a href=\"{$url}\" target=\"_blank\" class=\"text-primary-600 dark:text-primary-400\">{$record->name}</a>.",
            'type' => 'comment',
        ]);

        $url = OperationResource::getUrl('view', ['record' => $newOperation]);

        $record->addMessage([
            'body' => "The backorder <a href=\"{$url}\" target=\"_blank\" class=\"text-primary-600 dark:text-primary-400\">{$newOperation->name}</a> has been created.",
            'type' => 'comment',
        ]);
    }

    public function computeTransfer(Operation $record): Operation
    {
        if (in_array($record->state, [OperationState::DONE, OperationState::CANCELED])) {
            return $record;
        }

        foreach ($record->moves as $move) {
            $this->computeTransferMove($move);
        }

        $record = $this->computeTransferState($record);

        $record->save();

        return $record;
    }

    public function computeTransferMove(Move $record): Move
    {
        $lines = $record->lines()->orderBy('created_at')->get();

        if (! is_null($record->quantity)) {
            $remainingQty = $record->uom->computeQuantity($record->quantity, $record->product->uom, true, 'HALF-UP');
        } else {
            $remainingQty = $record->product_qty;
        }

        $updatedLines = collect();

        $availableQuantity = 0;

        $isSupplierSource = $record->sourceLocation->type === LocationType::SUPPLIER;

        $productQuantities = collect();

        if (! $isSupplierSource) {
            $parentPath = $record->sourceLocation->parent_path;

            if (
                ! $parentPath
                || trim($parentPath, '/') === ''
            ) {
                $sourceLocationIds = collect([$record->source_location_id]);
            } else {
                $sourceLocationIds = Location::where('parent_path', 'LIKE', $parentPath.'%')
                    ->pluck('id');
            }

            $productQuantities = ProductQuantity::query()
                ->with(['location', 'lot', 'package'])
                ->where('product_id', $record->product_id)
                ->whereIn('location_id', $sourceLocationIds)
                ->when(
                    $record->sourceLocation->type !== LocationType::SUPPLIER
                        && $record->product->tracking === ProductTracking::LOT,
                    fn ($query) => $query->whereNotNull('lot_id')
                )
                ->get();
        }

        foreach ($lines as $line) {
            $currentLocationQty = null;

            if (! $isSupplierSource) {
                $currentLocationQty = $productQuantities
                    ->where('location_id', $line->source_location_id)
                    ->where('lot_id', $line->lot_id)
                    ->where('package_id', $line->package_id)
                    ->first()?->quantity ?? 0;

                if ($currentLocationQty <= 0) {
                    $line->delete();

                    continue;
                }
            }

            if ($remainingQty > 0) {
                $newQty = $isSupplierSource
                    ? min($line->uom_qty, $remainingQty)
                    : min($line->uom_qty, $currentLocationQty, $remainingQty);

                if ($newQty != $line->uom_qty) {
                    $line->update([
                        'qty'     => $record->product->uom->computeQuantity($newQty, $record->uom, true, 'HALF-UP'),
                        'uom_qty' => $newQty,
                        'state'   => MoveState::ASSIGNED,
                    ]);
                }

                $updatedLines->push($line->source_location_id.'-'.$line->lot_id.'-'.$line->package_id);

                $remainingQty = round($remainingQty - $newQty, 4);

                $availableQuantity += $newQty;
            } else {
                $line->delete();
            }
        }

        if ($remainingQty > 0) {
            if ($isSupplierSource) {
                while ($remainingQty > 0) {
                    $newQty = $remainingQty;

                    if ($record->product->tracking == ProductTracking::SERIAL) {
                        $newQty = 1;
                    }

                    $record->lines()->create([
                        'qty'                     => $record->product->uom->computeQuantity($newQty, $record->uom, true, 'HALF-UP'),
                        'uom_qty'                 => $newQty,
                        'source_location_id'      => $record->source_location_id,
                        'state'                   => MoveState::ASSIGNED,
                        'reference'               => $record->reference,
                        'picking_description'     => $record->description_picking,
                        'is_picked'               => $record->is_picked,
                        'scheduled_at'            => $record->scheduled_at,
                        'operation_id'            => $record->operation_id,
                        'product_id'              => $record->product_id,
                        'uom_id'                  => $record->uom_id,
                        'destination_location_id' => $record->destination_location_id,
                        'company_id'              => $record->company_id,
                        'creator_id'              => Auth::id(),
                    ]);

                    $remainingQty = round($remainingQty - $newQty, 4);

                    $availableQuantity += $newQty;
                }
            } else {
                foreach ($productQuantities as $productQuantity) {
                    if ($remainingQty <= 0) {
                        break;
                    }

                    if ($updatedLines->contains($productQuantity->location_id.'-'.$productQuantity->lot_id.'-'.$productQuantity->package_id)) {
                        continue;
                    }

                    if ($productQuantity->quantity <= 0) {
                        continue;
                    }

                    $newQty = min($productQuantity->quantity, $remainingQty);

                    $availableQuantity += $newQty;

                    $record->lines()->create([
                        'qty'                     => $record->product->uom->computeQuantity($newQty, $record->uom, true, 'HALF-UP'),
                        'uom_qty'                 => $newQty,
                        'lot_name'                => $productQuantity->lot?->name,
                        'lot_id'                  => $productQuantity->lot_id,
                        'package_id'              => $productQuantity->package_id,
                        'result_package_id'       => $newQty == $productQuantity->quantity ? $productQuantity->package_id : null,
                        'source_location_id'      => $productQuantity->location_id,
                        'state'                   => MoveState::ASSIGNED,
                        'reference'               => $record->reference,
                        'picking_description'     => $record->description_picking,
                        'is_picked'               => $record->is_picked,
                        'scheduled_at'            => $record->scheduled_at,
                        'operation_id'            => $record->operation_id,
                        'product_id'              => $record->product_id,
                        'uom_id'                  => $record->uom_id,
                        'destination_location_id' => $record->destination_location_id,
                        'company_id'              => $record->company_id,
                        'creator_id'              => Auth::id(),
                    ]);

                    $remainingQty = round($remainingQty - $newQty, 4);
                }
            }
        }

        $requestedQty = $record->product_qty;

        if ($availableQuantity <= 0) {
            $record->update([
                'state'    => MoveState::CONFIRMED,
                'quantity' => null,
            ]);

            $record->lines()->update([
                'state' => MoveState::CONFIRMED,
            ]);
        } elseif ($availableQuantity < $requestedQty) {
            $record->update([
                'state'    => MoveState::PARTIALLY_ASSIGNED,
                'quantity' => $record->product->uom->computeQuantity($availableQuantity, $record->uom, true, 'HALF-UP'),
            ]);

            $record->lines()->update([
                'state' => MoveState::PARTIALLY_ASSIGNED,
            ]);
        } else {
            $record->update([
                'state'    => MoveState::ASSIGNED,
                'quantity' => $record->product->uom->computeQuantity($availableQuantity, $record->uom, true, 'HALF-UP'),
            ]);
        }

        return $record;
    }

    public function computeTransferState(Operation $record): Operation
    {
        $record->refresh();

        if (in_array($record->state, [OperationState::DONE, OperationState::CANCELED])) {
            return $record;
        }

        if ($record->moves->every(fn ($move) => $move->state === MoveState::CONFIRMED)) {
            $record->state = OperationState::CONFIRMED;
        } elseif ($record->moves->every(fn ($move) => $move->state === MoveState::DONE)) {
            $record->state = OperationState::DONE;
        } elseif ($record->moves->every(fn ($move) => $move->state === MoveState::CANCELED)) {
            $record->state = OperationState::CANCELED;
        } elseif ($record->moves->contains(
            fn ($move) => $move->state === MoveState::ASSIGNED ||
                $move->state === MoveState::PARTIALLY_ASSIGNED
        )) {
            $record->state = OperationState::ASSIGNED;
        }

        return $record;
    }

    /**
     * Check if a back order can be processed.
     */
    public function canCreateBackOrder(Operation $record): bool
    {
        if ($record->operationType->create_backorder === CreateBackorder::NEVER) {
            return false;
        }

        return $record->moves->sum('product_uom_qty') > $record->moves->sum('quantity');
    }

    /**
     * Calculate reserved quantity for a location.
     */
    private function calculateReservedQty($location, $qty): int
    {
        if ($location->type === LocationType::INTERNAL && ! $location->is_stock_location) {
            return $qty;
        }

        return 0;
    }

    /**
     * Apply push rules for the operation.
     */
    public function applyPushRules($moves): void
    {
        $rules = [];

        foreach ($moves as $move) {
            if ($move->origin_returned_move_id) {
                continue;
            }

            $rule = $this->getPushRule($move->product, $move->destinationLocation, [
                'packaging' => $move->productPackaging,
                'warehouse' => $move->warehouse,
            ]);

            if (! $rule) {
                continue;
            }

            $ruleId = $rule->id;

            $pushedMove = $this->runPushRule($rule, $move);

            if (! isset($rules[$ruleId])) {
                $rules[$ruleId] = [
                    'rule'      => $rule,
                    'operation' => $pushedMove->operation,
                    'moves'     => [$pushedMove],
                ];
            } else {
                $rules[$ruleId]['moves'][] = $pushedMove;
            }
        }

        foreach ($rules as $ruleData) {
            $this->createPushOperation($ruleData['operation'], $ruleData['rule'], $ruleData['moves']);
        }
    }

    public function preparePushMoveCopyValues(Rule $rule, Move $moveToCopy, $newScheduledAt)
    {
        $companyId = $rule->company_id;

        $copiedQuantity = $moveToCopy->quantity;

        if (float_compare($moveToCopy->product_uom_qty, 0, precisionRounding: $moveToCopy->product_uom->rounding) < 0) {
            $copiedQuantity = $moveToCopy->product_uom_qty;
        }

        if (! $companyId) {
            $companyId = $rule->warehouse?->company_id
                ?? $rule->operationType?->warehouse?->company_id;
        }

        return [
            'state'                   => MoveState::DRAFT,
            'reference'               => null,
            'product_uom_qty'         => $copiedQuantity,
            'product_qty'             => $moveToCopy->uom->computeQuantity($copiedQuantity, $moveToCopy->product->uom, true, 'HALF-UP'),
            'origin'                  => $moveToCopy->origin ?? $moveToCopy->operation->name ?? '/',
            'operation_id'            => null,
            'source_location_id'      => $moveToCopy->destination_location_id,
            'destination_location_id' => $rule->destination_location_id,
            'final_location_id'       => $moveToCopy->final_location_id,
            'rule_id'                 => $rule->id,
            'scheduled_at'            => $newScheduledAt,
            'company_id'              => $companyId,
            'operation_type_id'       => $rule->operation_type_id,
            'propagate_cancel'        => $rule->propagate_cancel,
            'warehouse_id'            => $rule->warehouse_id,
            'procure_method'          => ProcureMethod::MAKE_TO_ORDER,
        ];
    }

    /**
     * Run a push rule on a move.
     */
    public function runPushRule(Rule $rule, Move $move)
    {
        $newScheduledAt = $move->scheduled_at->addDays($rule->delay);

        if ($rule->auto == RuleAuto::TRANSPARENT) {
            $move->update([
                'scheduled_at' => $newScheduledAt,
                'destination_location_id' => $rule->destination_location_id,
            ]);

            if ($move->move_line_ids && $move->move_line_ids->isNotEmpty()) {
                $putAwayLocation = $move->destinationLocation->getPutAwayStrategy($move->product);

                foreach ($move->lines as $moveLine) {
                    $moveLine->update([
                        'destination_location_id' => $putAwayLocation?->id ?? $move->destination_location_id,
                    ]);
                }
            }

            //TODO: Check this
            if ($rule->destination_location_id !== $move->destination_location_id) {
                return $this->applyPushRules($move);
            }
        } else {
            $newMoveValues = $this->preparePushMoveCopyValues($rule, $move, $newScheduledAt);

            $newMove = $move->replicate()->fill($newMoveValues);

            $newMove->save();

            if ($newMove->shouldBypassReservation()) {
                $newMove->update([
                    'procure_method' => ProcureMethod::MAKE_TO_STOCK,
                ]);
            }

            if (! $newMove->sourceLocation->shouldBypassReservation()) {
                $move->moveDestinations()->attach($newMove->id);
            }
        }

        return $newMove;
    }

    /**
     * Create a new operation based on a push rule and assign moves to it.
     */
    private function createPushOperation(Operation $record, Rule $rule, array $moves): void
    {
        $newOperation = Operation::create([
            'state'                   => OperationState::DRAFT,
            'origin'                  => $record->name,
            'operation_type_id'       => $rule->operation_type_id,
            'source_location_id'      => $rule->source_location_id,
            'destination_location_id' => $rule->destination_location_id,
            'scheduled_at'            => now()->addDays($rule->delay),
            'company_id'              => $rule->company_id,
            'user_id'                 => Auth::id(),
            'creator_id'              => Auth::id(),
        ]);

        foreach ($moves as $move) {
            $move->update([
                'operation_id' => $newOperation->id,
                'reference'    => $newOperation->name,
            ]);
        }

        $newOperation->refresh();

        $this->computeTransfer($newOperation);
    }

    /**
     * Traverse up the location tree to find a matching push rule.
     */
    public function getPushRule(Product $product, Location $destinationLocation, array $values = [])
    {
        $foundRule = null;

        $location = $destinationLocation;

        $filters['action'] = [RuleAction::PUSH, RuleAction::PULL_PUSH];

        while (! $foundRule && $location) {
            $filters['source_location_id'] = $location->id;

            $foundRule = $this->searchRule(
                $values['packaging'] ?? null,
                $product,
                $values['warehouse'] ?? null,
                $filters
            );

            $location = $location->parent;
        }

        return $foundRule;
    }

    /**
     * Traverse up the location tree to find a matching pull rule.
     */
    public function getRule(Product $product, Location $location, array $values = [])
    {
        $foundRule = null;

        $filters['action'] = ['!=', RuleAction::PUSH];

        while (! $foundRule && $location) {
            $filters['destination_location_id'] = $location->id;

            $foundRule = $this->searchRule(
                $values['packaging'] ?? null,
                $product,
                $values['warehouse'] ?? null,
                $filters
            );

            $location = $location->parent;
        }

        return $foundRule;
    }

    /**
     * Search for a push rule based on the provided filters.
     */
    public function searchRule($productPackaging, $product, $warehouse, array $filters)
    {
        if ($warehouse) {
            $filters['warehouse_id'] = $warehouse->id;
        }

        $routeSources = [
            [$productPackaging, 'routes'],
            [$product, 'routes'],
            [$product?->category, 'routes'],
            [$warehouse, 'routes'],
        ];

        foreach ($routeSources as [$source, $relationName]) {
            if (! $source || ! $source->{$relationName}) {
                continue;
            }

            $routeIds = $source->{$relationName}->pluck('id');

            if ($routeIds->isEmpty()) {
                continue;
            }

            $foundRule = Rule::whereIn('route_id', $routeIds)
                ->where(function ($query) use ($filters) {
                    foreach ($filters as $column => $value) {
                        if (is_array($value)) {
                            if (count($value) === 2 && $value[0] === '!=') {
                                [, $val] = $value;

                                $query->where($column, '!=', $val);
                            } else {
                                $query->whereIn($column, $value);
                            }
                        } else {
                            $query->where($column, $value);
                        }
                    }
                })
                ->orderBy('route_sort', 'asc')
                ->orderBy('sort', 'asc')
                ->first();

            if ($foundRule) {
                return $foundRule;
            }
        }

        return null;
    }

    public function runProcurements($procurements)
    {
        $actionsToRun = [];

        $procurementErrors = [];
        
        foreach ($procurements as $procurement) {
            $procurement['values']['company'] = $procurement['values']['company'] ?? $procurement['location']->company;
            $procurement['values']['priority'] = $procurement['values']['priority'] ?? '0';
            $procurement['values']['planned'] = $procurement['values']['planned'] ?? now();

            $rule = $this->getRule($procurement['product'], $procurement['location'], $procurement['values']);

            if (! $rule) {
                $error = __('No rule has been found to replenish ":product" in ":location".\nVerify the routes configuration on the product.', [
                    'product' => $procurement['product']->name,
                    'location' => $procurement['location']->full_name,
                ]);

                $procurementErrors[] = ['procurement' => $procurement, 'error' => $error];
            } else {
                $action = $rule->action === RuleAction::PULL_PUSH ? RuleAction::PULL : $rule->action;

                if (! isset($actionsToRun[$action->value])) {
                    $actionsToRun[$action->value] = [];
                }

                $actionsToRun[$action->value][] = [$procurement, $rule];
            }
        }

        foreach ($actionsToRun as $action => $procurements) {
            $method = 'run'.ucfirst($action).'Rule';

            try {
                $this->$method($procurements);
            } catch (\Exception $e) {
                $procurementErrors[] = $e->getMessage();
            }
        }

        dd($actionsToRun, $procurementErrors);
    }

    /**
     * Run a pull rule on a line.
     */
    public function runPullRule($procurements)
    {
        foreach ($procurements as [$procurement, $rule]) {
            if (! $rule->source_location_id) {
                throw new \Exception(__('No source location defined on stock rule: :name!', [
                    'name' => $rule->name
                ]));
            }
        }

        usort($procurements, function($procurement) {
            return float_compare($procurement[0]->product_qty, 0.0, precisionRounding: $procurement[0]->uom->rounding) > 0 ? 1 : 0;
        });

        foreach ($procurements as [$procurement, $rule]) {
            $procureMethod = $rule->procure_method;

            if ($rule->procure_method === ProcureMethod::MTS_ELSE_MTO) {
                $procureMethod = ProcureMethod::MAKE_TO_STOCK;
            }

            $moveValues = $this->prepareMoveValues($procurement);

            $moveValues['procure_method'] = $procureMethod;
        }
    }

    /**
     * Run a buy rule on a line.
     */
    public function runBuyRule($procurements)
    {
    }

    /**
     * Run a manufacture rule on a line.
     */
    public function runManufactureRule($procurements)
    {
    }

    public function prepareMoveValues($procurement)
    {
        dd($procurement);
    }
}
