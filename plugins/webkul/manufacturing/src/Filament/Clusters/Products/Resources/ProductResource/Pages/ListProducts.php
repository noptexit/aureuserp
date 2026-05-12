<?php

namespace Webkul\Manufacturing\Filament\Clusters\Products\Resources\ProductResource\Pages;

use Webkul\Inventory\Filament\Clusters\Products\Resources\ProductResource\Pages\ListProducts as BaseListProducts;
use Webkul\Manufacturing\Filament\Clusters\Products\Resources\ProductResource;
use Illuminate\Database\Eloquent\Builder;
use Webkul\TableViews\Filament\Components\PresetView;

class ListProducts extends BaseListProducts
{
    protected static string $resource = ProductResource::class;

    public function getPresetTableViews(): array
    {
        return array_merge(parent::getPresetTableViews(), [
            'storable_products' => PresetView::make(__('inventories::filament/clusters/products/resources/product/pages/list-products.tabs.inventory-management'))
                ->icon('heroicon-s-clipboard-document-list')
                ->favorite()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_storable', true)),
        ]);
    }
}
