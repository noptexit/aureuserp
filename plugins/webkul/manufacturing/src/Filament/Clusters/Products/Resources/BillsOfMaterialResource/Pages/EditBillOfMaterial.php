<?php

namespace Webkul\Manufacturing\Filament\Clusters\Products\Resources\BillsOfMaterialResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Webkul\Manufacturing\Filament\Clusters\Products\Resources\BillsOfMaterialResource;

class EditBillOfMaterial extends EditRecord
{
    protected static string $resource = BillsOfMaterialResource::class;

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('manufacturing::filament/clusters/products/resources/bill-of-material/pages/edit-bill-of-material.notification.title'))
            ->body(__('manufacturing::filament/clusters/products/resources/bill-of-material/pages/edit-bill-of-material.notification.body'));
    }
}
