<?php

namespace Webkul\Manufacturing\Filament\Clusters\Operations\Resources\WorkOrderResource\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\CreateRecord;
use Webkul\Manufacturing\Filament\Clusters\Operations\Resources\WorkOrderResource;
use Webkul\Manufacturing\Models\Order;
use Webkul\Support\Filament\Concerns\HasRepeaterColumnManager;

class CreateWorkOrder extends CreateRecord
{
    use HasRepeaterColumnManager;

    protected ?bool $hasDatabaseTransactions = true;

    protected static string $resource = WorkOrderResource::class;

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Start;
    }

    public function getSubNavigation(): array
    {
        if (filled($cluster = static::getCluster())) {
            return $this->generateNavigationItems($cluster::getClusteredComponents());
        }

        return [];
    }

    public function getTitle(): string
    {
        return __('manufacturing::filament/clusters/operations/resources/work-order/pages/create-work-order.title');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $manufacturingOrder = Order::query()->with(['product'])->find($data['manufacturing_order_id'] ?? null);

        $data['product_id'] = $data['product_id'] ?? $manufacturingOrder?->product_id;
        $data['uom_id'] = $data['uom_id'] ?? $manufacturingOrder?->product?->uom_id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('manufacturing::filament/clusters/operations/resources/work-order/pages/create-work-order.notification.title'))
            ->body(__('manufacturing::filament/clusters/operations/resources/work-order/pages/create-work-order.notification.body'));
    }
}
