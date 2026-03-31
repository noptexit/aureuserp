<?php

namespace Webkul\Manufacturing\Filament\Clusters\Products\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\QueryException;
use Webkul\Manufacturing\Enums\BillOfMaterialConsumption;
use Webkul\Manufacturing\Enums\BillOfMaterialReadyToProduce;
use Webkul\Manufacturing\Enums\BillOfMaterialType;
use Webkul\Manufacturing\Filament\Clusters\Products;
use Webkul\Manufacturing\Filament\Clusters\Products\Resources\BillsOfMaterialResource\Pages\CreateBillOfMaterial;
use Webkul\Manufacturing\Filament\Clusters\Products\Resources\BillsOfMaterialResource\Pages\EditBillOfMaterial;
use Webkul\Manufacturing\Filament\Clusters\Products\Resources\BillsOfMaterialResource\Pages\ListBillsOfMaterial;
use Webkul\Manufacturing\Filament\Clusters\Products\Resources\BillsOfMaterialResource\Pages\ViewBillOfMaterial;
use Webkul\Manufacturing\Models\BillOfMaterial;
use Webkul\Product\Models\Product;
use Webkul\Support\Models\UOM;

class BillsOfMaterialResource extends Resource
{
    protected static ?string $model = BillOfMaterial::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 2;

    protected static ?string $cluster = Products::class;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getModelLabel(): string
    {
        return __('manufacturing::models/bill-of-material.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('manufacturing::filament/clusters/products/resources/bill-of-material.navigation.title');
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Start;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.general.title'))
                            ->schema([
                                TextInput::make('code')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.general.fields.reference'))
                                    ->required()
                                    ->maxLength(255)
                                    ->autofocus()
                                    ->placeholder(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.general.fields.reference-placeholder'))
                                    ->extraInputAttributes(['style' => 'font-size: 1.5rem;height: 3rem;'])
                                    ->columnSpanFull(),
                                Select::make('product_id')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.general.fields.product'))
                                    ->relationship('product', 'name', fn (Builder $query) => $query->withTrashed())
                                    ->getOptionLabelFromRecordUsing(fn (Product $record): string => $record->name)
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.general.fields.quantity'))
                                    ->numeric()
                                    ->minValue(0.0001)
                                    ->default(1)
                                    ->step('0.0001')
                                    ->required(),
                                Select::make('uom_id')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.general.fields.uom'))
                                    ->options(fn (): array => UOM::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('operation_type_id')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.general.fields.operation-type'))
                                    ->relationship('operationType', 'name', fn (Builder $query) => $query->withTrashed())
                                    ->searchable()
                                    ->preload(),
                                Select::make('company_id')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.general.fields.company'))
                                    ->relationship('company', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),
                Group::make()
                    ->schema([
                        Section::make(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.settings.title'))
                            ->schema([
                                Select::make('type')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.settings.fields.type'))
                                    ->options(BillOfMaterialType::class)
                                    ->default(BillOfMaterialType::NORMAL->value)
                                    ->required(),
                                Select::make('ready_to_produce')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.settings.fields.ready-to-produce'))
                                    ->options(BillOfMaterialReadyToProduce::class)
                                    ->default(BillOfMaterialReadyToProduce::ALL_AVAILABLE->value)
                                    ->required(),
                                Select::make('consumption')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.settings.fields.flexible-consumption'))
                                    ->options(BillOfMaterialConsumption::class)
                                    ->default(BillOfMaterialConsumption::WARNING->value)
                                    ->required(),
                                Toggle::make('allow_operation_dependencies')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.settings.fields.operation-dependencies'))
                                    ->default(false),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderableColumns()
            ->columns([
                TextColumn::make('code')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.columns.reference'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.name')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.columns.product'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.columns.quantity'))
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                TextColumn::make('uom.name')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.columns.uom'))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.columns.type'))
                    ->badge(),
                TextColumn::make('company.name')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.columns.company'))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.columns.deleted-at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.columns.updated-at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product_id')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.filters.product'))
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('type')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.filters.type'))
                    ->options(BillOfMaterialType::options()),
                SelectFilter::make('company_id')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.filters.company'))
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->hidden(fn (BillOfMaterial $record): bool => $record->trashed()),
                EditAction::make()
                    ->hidden(fn (BillOfMaterial $record): bool => $record->trashed()),
                RestoreAction::make()
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.actions.restore.notification.title'))
                            ->body(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.actions.restore.notification.body')),
                    ),
                DeleteAction::make()
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.actions.delete.notification.title'))
                            ->body(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.actions.delete.notification.body')),
                    ),
                ForceDeleteAction::make()
                    ->action(function (BillOfMaterial $record, ForceDeleteAction $action): void {
                        try {
                            $record->forceDelete();
                        } catch (QueryException) {
                            Notification::make()
                                ->danger()
                                ->title(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.actions.force-delete.notification.error.title'))
                                ->body(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.actions.force-delete.notification.error.body'))
                                ->send();

                            $action->cancel();
                        }
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.actions.force-delete.notification.success.title'))
                            ->body(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.actions.force-delete.notification.success.body')),
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make()
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.bulk-actions.restore.notification.title'))
                                ->body(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.bulk-actions.restore.notification.body')),
                        ),
                    DeleteBulkAction::make()
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.bulk-actions.delete.notification.title'))
                                ->body(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.bulk-actions.delete.notification.body')),
                        ),
                    ForceDeleteBulkAction::make()
                        ->action(function (Collection $records, ForceDeleteBulkAction $action): void {
                            try {
                                $records->each(fn (Model $record): ?bool => $record->forceDelete());
                            } catch (QueryException) {
                                Notification::make()
                                    ->danger()
                                    ->title(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.bulk-actions.force-delete.notification.error.title'))
                                    ->body(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.bulk-actions.force-delete.notification.error.body'))
                                    ->send();

                                $action->cancel();
                            }
                        })
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.bulk-actions.force-delete.notification.success.title'))
                                ->body(__('manufacturing::filament/clusters/products/resources/bill-of-material.table.bulk-actions.force-delete.notification.success.body')),
                        ),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.general.title'))
                            ->schema([
                                TextEntry::make('code')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.general.entries.reference'))
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::Bold)
                                    ->icon('heroicon-o-squares-2x2')
                                    ->columnSpanFull(),
                                TextEntry::make('product.name')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.general.entries.product'))
                                    ->placeholder('—'),
                                TextEntry::make('quantity')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.general.entries.quantity'))
                                    ->numeric(decimalPlaces: 4),
                                TextEntry::make('uom.name')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.general.entries.uom'))
                                    ->placeholder('—'),
                                TextEntry::make('operationType.name')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.general.entries.operation-type'))
                                    ->placeholder('—'),
                                TextEntry::make('company.name')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.general.entries.company'))
                                    ->placeholder('—'),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),
                Group::make()
                    ->schema([
                        Section::make(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.settings.title'))
                            ->schema([
                                TextEntry::make('type')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.settings.entries.type'))
                                    ->badge(),
                                TextEntry::make('ready_to_produce')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.settings.entries.ready-to-produce'))
                                    ->badge(),
                                TextEntry::make('consumption')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.settings.entries.flexible-consumption'))
                                    ->badge(),
                                IconEntry::make('allow_operation_dependencies')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.settings.entries.operation-dependencies'))
                                    ->boolean(),
                            ]),
                        Section::make(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.record-information.title'))
                            ->schema([
                                TextEntry::make('creator.name')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.record-information.entries.created-by'))
                                    ->placeholder('—')
                                    ->icon('heroicon-o-user'),
                                TextEntry::make('created_at')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.record-information.entries.created-at'))
                                    ->dateTime()
                                    ->icon('heroicon-m-calendar'),
                                TextEntry::make('updated_at')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.record-information.entries.last-updated'))
                                    ->dateTime()
                                    ->icon('heroicon-m-clock'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListBillsOfMaterial::route('/'),
            'create' => CreateBillOfMaterial::route('/create'),
            'view'   => ViewBillOfMaterial::route('/{record}'),
            'edit'   => EditBillOfMaterial::route('/{record}/edit'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewBillOfMaterial::class,
            EditBillOfMaterial::class,
        ]);
    }
}
