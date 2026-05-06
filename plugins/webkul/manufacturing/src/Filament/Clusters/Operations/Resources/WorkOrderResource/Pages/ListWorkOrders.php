<?php

namespace Webkul\Manufacturing\Filament\Clusters\Operations\Resources\WorkOrderResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Webkul\Manufacturing\Filament\Clusters\Operations\Resources\WorkOrderResource;

class ListWorkOrders extends ListRecords
{
    protected static string $resource = WorkOrderResource::class;

    public function getTitle(): string
    {
        return __('manufacturing::filament/clusters/operations/resources/work-order/pages/list-work-orders.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('manufacturing::filament/clusters/operations/resources/work-order.pages.list.header-actions.create.label')),
        ];
    }
}
