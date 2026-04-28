<?php

namespace Webkul\Manufacturing\Filament\Clusters\Operations\Resources\ManufacturingOrderResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Webkul\Manufacturing\Filament\Clusters\Operations\Resources\ManufacturingOrderResource;
use Webkul\Manufacturing\Filament\Clusters\Operations\Actions\ConfirmAction;
use Webkul\Manufacturing\Filament\Clusters\Operations\Actions\CancelAction;
use Webkul\Manufacturing\Filament\Clusters\Operations\Actions\PlanAction;
use Webkul\Manufacturing\Filament\Clusters\Operations\Actions\StartAction;
use Webkul\Manufacturing\Filament\Clusters\Operations\Actions\UnplanAction;
use Webkul\Support\Traits\HasRecordNavigationTabs;

class ViewManufacturingOrder extends ViewRecord
{
    use HasRecordNavigationTabs;

    protected static string $resource = ManufacturingOrderResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->reference ?: __('manufacturing::filament/clusters/operations/resources/manufacturing-order/pages/view-manufacturing-order.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            ConfirmAction::make('confirm'),
            PlanAction::make('plan'),
            UnplanAction::make('unplan'),
            StartAction::make('start'),
            CancelAction::make('cancel'),
        ];
    }
}
