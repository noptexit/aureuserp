<?php

namespace Webkul\Manufacturing\Filament\Clusters\Operations\Resources\ManufacturingOrderResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Webkul\Manufacturing\Filament\Clusters\Operations\Resources\ManufacturingOrderResource;
use Webkul\Manufacturing\Filament\Clusters\Operations\Actions\ConfirmAction;
use Webkul\Manufacturing\Filament\Clusters\Operations\Actions\CancelAction;
use Webkul\Manufacturing\Filament\Clusters\Operations\Actions\PlanAction;
use Webkul\Manufacturing\Filament\Clusters\Operations\Actions\UnplanAction;
use Webkul\Support\Filament\Concerns\HasRepeaterColumnManager;
use Webkul\Support\Traits\HasRecordNavigationTabs;

class EditManufacturingOrder extends EditRecord
{
    use HasRecordNavigationTabs, HasRepeaterColumnManager;

    protected ?bool $hasDatabaseTransactions = true;

    protected static string $resource = ManufacturingOrderResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->reference ?: __('manufacturing::filament/clusters/operations/resources/manufacturing-order/pages/edit-manufacturing-order.title');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['destination_location_id'] = $data['final_location_id'] ?? $data['destination_location_id'] ?? null;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ConfirmAction::make('confirm'),
            CancelAction::make('cancel'),
            PlanAction::make('plan'),
            UnplanAction::make('unplan'),
        ];
    }
}
