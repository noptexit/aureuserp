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
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Webkul\Manufacturing\Enums\BillOfMaterialConsumption;
use Webkul\Manufacturing\Enums\BillOfMaterialReadyToProduce;
use Webkul\Manufacturing\Enums\BillOfMaterialType;
use Webkul\Manufacturing\Enums\OperationTimeMode;
use Webkul\Manufacturing\Filament\Clusters\Products;
use Webkul\Manufacturing\Filament\Clusters\Products\Resources\BillsOfMaterialResource\Pages\CreateBillOfMaterial;
use Webkul\Manufacturing\Filament\Clusters\Products\Resources\BillsOfMaterialResource\Pages\EditBillOfMaterial;
use Webkul\Manufacturing\Filament\Clusters\Products\Resources\BillsOfMaterialResource\Pages\ListBillsOfMaterial;
use Webkul\Manufacturing\Filament\Clusters\Products\Resources\BillsOfMaterialResource\Pages\ViewBillOfMaterial;
use Webkul\Manufacturing\Models\BillOfMaterial;
use Webkul\Manufacturing\Models\Product;
use Webkul\Support\Filament\Forms\Components\Repeater;
use Webkul\Support\Filament\Forms\Components\Repeater\TableColumn as RepeaterTableColumn;
use Webkul\Support\Filament\Infolists\Components\RepeatableEntry;
use Webkul\Support\Filament\Infolists\Components\Repeater\TableColumn as InfolistTableColumn;
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
                                Select::make('product_id')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.general.fields.product'))
                                    ->relationship('product', 'name', fn (Builder $query) => $query->withTrashed()->whereNull('parent_id'))
                                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->name)
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                        $product = Product::query()->find($state);

                                        if (! $product) {
                                            return;
                                        }

                                        $set('uom_id', $product->uom_id);
                                        $set('company_id', $product->company_id);
                                    })
                                    ->required()
                                    ->columnSpanFull(),
                                Placeholder::make('product_variant')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.general.fields.product-variant'))
                                    ->content('—')
                                    ->columnSpanFull(),
                                FusedGroup::make([
                                    TextInput::make('quantity')
                                        ->numeric()
                                        ->minValue(0.0001)
                                        ->default(1)
                                        ->step('0.0001')
                                        ->required()
                                        ->columnSpan(2),
                                    Select::make('uom_id')
                                        ->placeholder(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.general.fields.uom'))
                                        ->options(fn (): array => UOM::query()->orderBy('name')->pluck('name', 'id')->all())
                                        ->default(UOM::first()?->id)
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                ])
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.general.fields.quantity'))
                                    ->columns(3),
                                TextInput::make('code')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.general.fields.reference'))
                                    ->maxLength(255)
                                    ->placeholder(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.general.fields.reference-placeholder')),
                                Radio::make('type')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.general.fields.type'))
                                    ->options(BillOfMaterialType::class)
                                    ->default(BillOfMaterialType::NORMAL->value)
                                    ->live()
                                    ->inline(false)
                                    ->required(),
                                Select::make('company_id')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.general.fields.company'))
                                    ->relationship('company', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->default(Auth::user()?->default_company_id)
                                    ->required(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.miscellaneous.title'))
                            ->schema([
                                Placeholder::make('kit_information')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.miscellaneous.fields.kit-information'))
                                    ->content(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.miscellaneous.fields.kit-information-content'))
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => static::matchesEnumState($get('type'), BillOfMaterialType::PHANTOM)),
                                Radio::make('ready_to_produce')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.miscellaneous.fields.ready-to-produce'))
                                    ->options(BillOfMaterialReadyToProduce::class)
                                    ->default(BillOfMaterialReadyToProduce::ALL_AVAILABLE->value)
                                    ->inline(false)
                                    ->visible(fn (Get $get): bool => ! static::matchesEnumState($get('type'), BillOfMaterialType::PHANTOM))
                                    ->required()
                                    ->columnSpanFull(),
                                Select::make('operation_type_id')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.miscellaneous.fields.routing'))
                                    ->relationship('operationType', 'name', fn (Builder $query) => $query->withTrashed())
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Get $get): bool => ! static::matchesEnumState($get('type'), BillOfMaterialType::PHANTOM))
                                    ->columnSpanFull(),
                                Select::make('consumption')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.miscellaneous.fields.consumption'))
                                    ->options(BillOfMaterialConsumption::class)
                                    ->native(false)
                                    ->default(BillOfMaterialConsumption::WARNING->value)
                                    ->visible(fn (Get $get): bool => ! static::matchesEnumState($get('type'), BillOfMaterialType::PHANTOM))
                                    ->required()
                                    ->columnSpanFull(),
                                Toggle::make('allow_operation_dependencies')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.miscellaneous.fields.operation-dependencies'))
                                    ->default(false)
                                    ->inline(false)
                                    ->columnSpanFull(),
                                TextInput::make('produce_delay')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.miscellaneous.fields.manufacturing-lead-time'))
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->suffix(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.miscellaneous.fields.days-suffix'))
                                    ->required()
                                    ->columnSpanFull(),
                                TextInput::make('days_to_prepare_mo')
                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.miscellaneous.fields.days-to-prepare-manufacturing-order'))
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->suffix(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.sections.miscellaneous.fields.days-suffix'))
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),
                    ])
                    ->columnSpan(['lg' => 1]),

                Tabs::make('bom-tabs')
                    ->tabs([
                        Tab::make(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.components.title'))
                            ->schema([
                                static::getComponentsRepeater(),
                            ]),
                        Tab::make(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.operations.title'))
                            ->schema([
                                static::getOperationsRepeater(),
                            ]),
                        Tab::make(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.by-products.title'))
                            ->schema([
                                static::getByProductsRepeater(),
                            ]),
                    ])
                    ->columnSpanFull(),
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
                Section::make(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.general.title'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Group::make()
                                    ->schema([
                                        TextEntry::make('product.name')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.general.entries.product'))
                                            ->size(TextSize::Large)
                                            ->placeholder('—'),
                                        TextEntry::make('product_variant')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.general.entries.product-variant'))
                                            ->state('—'),
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('quantity')
                                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.general.entries.quantity'))
                                                    ->numeric(decimalPlaces: 4),
                                                TextEntry::make('uom.name')
                                                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.general.entries.uom'))
                                                    ->placeholder('—'),
                                            ]),
                                    ]),
                                Group::make()
                                    ->schema([
                                        TextEntry::make('code')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.general.entries.reference'))
                                            ->placeholder('—'),
                                        TextEntry::make('type')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.general.entries.type'))
                                            ->badge(),
                                        TextEntry::make('company.name')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.sections.general.entries.company'))
                                            ->placeholder('—'),
                                    ]),
                            ]),
                    ]),
                Tabs::make('bom-details')
                    ->tabs([
                        Tab::make(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.components.title'))
                            ->schema([
                                RepeatableEntry::make('lines')
                                    ->hiddenLabel()
                                    ->contained(false)
                                    ->table([
                                        InfolistTableColumn::make('product')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.components.entries.component')),
                                        InfolistTableColumn::make('operation')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.components.entries.operation')),
                                        InfolistTableColumn::make('quantity')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.components.entries.quantity')),
                                        InfolistTableColumn::make('uom')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.components.entries.uom')),
                                    ])
                                    ->schema([
                                        TextEntry::make('product.name')->placeholder('—'),
                                        TextEntry::make('operation.name')->placeholder('—'),
                                        TextEntry::make('quantity')->numeric(decimalPlaces: 4),
                                        TextEntry::make('uom.name')->placeholder('—'),
                                    ]),
                            ]),
                        Tab::make(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.operations.title'))
                            ->schema([
                                RepeatableEntry::make('operations')
                                    ->hiddenLabel()
                                    ->contained(false)
                                    ->table([
                                        InfolistTableColumn::make('name')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.operations.entries.operation')),
                                        InfolistTableColumn::make('work-center')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.operations.entries.work-center')),
                                        InfolistTableColumn::make('time-mode')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.operations.entries.time-mode')),
                                        InfolistTableColumn::make('duration')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.operations.entries.duration')),
                                    ])
                                    ->schema([
                                        TextEntry::make('name')->placeholder('—'),
                                        TextEntry::make('workCenter.name')->placeholder('—'),
                                        TextEntry::make('time_mode')->badge(),
                                        TextEntry::make('manual_cycle_time')
                                            ->formatStateUsing(fn (mixed $state): string => static::formatFloatTime($state ?? 60)),
                                    ]),
                            ]),
                        Tab::make(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.by-products.title'))
                            ->schema([
                                RepeatableEntry::make('byproducts')
                                    ->hiddenLabel()
                                    ->contained(false)
                                    ->table([
                                        InfolistTableColumn::make('product')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.by-products.entries.product')),
                                        InfolistTableColumn::make('quantity')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.by-products.entries.quantity')),
                                        InfolistTableColumn::make('uom')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.by-products.entries.uom')),
                                        InfolistTableColumn::make('operation')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.by-products.entries.operation')),
                                    ])
                                    ->schema([
                                        TextEntry::make('product.name')->placeholder('—'),
                                        TextEntry::make('quantity')->numeric(decimalPlaces: 4),
                                        TextEntry::make('uom.name')->placeholder('—'),
                                        TextEntry::make('operation.name')->placeholder('—'),
                                    ]),
                            ]),
                        Tab::make(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.miscellaneous.title'))
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('kit_information')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.miscellaneous.entries.kit-information'))
                                            ->state(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.miscellaneous.entries.kit-information-content'))
                                            ->visible(fn (BillOfMaterial $record): bool => $record->type === BillOfMaterialType::PHANTOM)
                                            ->columnSpanFull(),
                                        TextEntry::make('ready_to_produce')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.miscellaneous.entries.ready-to-produce'))
                                            ->badge()
                                            ->visible(fn (BillOfMaterial $record): bool => $record->type === BillOfMaterialType::NORMAL),
                                        TextEntry::make('operationType.name')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.miscellaneous.entries.routing'))
                                            ->placeholder('—')
                                            ->visible(fn (BillOfMaterial $record): bool => $record->type === BillOfMaterialType::NORMAL),
                                        TextEntry::make('consumption')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.miscellaneous.entries.consumption'))
                                            ->badge()
                                            ->visible(fn (BillOfMaterial $record): bool => $record->type === BillOfMaterialType::NORMAL),
                                        TextEntry::make('produce_delay')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.miscellaneous.entries.manufacturing-lead-time'))
                                            ->suffix(' '.__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.miscellaneous.entries.days-suffix')),
                                        IconEntry::make('allow_operation_dependencies')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.miscellaneous.entries.operation-dependencies'))
                                            ->boolean(),
                                        TextEntry::make('days_to_prepare_mo')
                                            ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.miscellaneous.entries.days-to-prepare-manufacturing-order'))
                                            ->suffix(' '.__('manufacturing::filament/clusters/products/resources/bill-of-material.infolist.tabs.miscellaneous.entries.days-suffix')),
                                    ]),
                            ]),
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
            ->columns(1);
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

    protected static function getComponentsRepeater(): Repeater
    {
        return Repeater::make('lines')
            ->relationship('lines')
            ->hiddenLabel()
            ->defaultItems(0)
            ->compact()
            ->addActionLabel(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.components.add-action'))
            ->table([
                RepeaterTableColumn::make('product_id')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.components.columns.component'))
                    ->markAsRequired()
                    ->resizable(),
                RepeaterTableColumn::make('operation_id')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.components.columns.operation'))
                    ->resizable(),
                RepeaterTableColumn::make('quantity')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.components.columns.quantity'))
                    ->markAsRequired()
                    ->resizable(),
                RepeaterTableColumn::make('uom_id')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.components.columns.uom'))
                    ->markAsRequired()
                    ->resizable(),
            ])
            ->schema([
                Hidden::make('company_id'),
                Select::make('product_id')
                    ->relationship('product', 'name', fn (Builder $query) => $query->withTrashed())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                        $product = Product::query()->find($state);

                        if (! $product) {
                            return;
                        }

                        $set('uom_id', $product->uom_id);
                        $set('company_id', $get('../../company_id'));
                    }),
                Select::make('operation_id')
                    ->relationship('operation', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('quantity')
                    ->numeric()
                    ->minValue(0.0001)
                    ->default(1)
                    ->step('0.0001')
                    ->required(),
                Select::make('uom_id')
                    ->options(fn (): array => UOM::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required(),
            ]);
    }

    protected static function getOperationsRepeater(): Repeater
    {
        return Repeater::make('operations')
            ->relationship('operations')
            ->hiddenLabel()
            ->defaultItems(0)
            ->compact()
            ->addActionLabel(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.operations.add-action'))
            ->table([
                RepeaterTableColumn::make('name')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.operations.columns.operation'))
                    ->markAsRequired()
                    ->resizable(),
                RepeaterTableColumn::make('work_center_id')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.operations.columns.work-center'))
                    ->markAsRequired()
                    ->resizable(),
                RepeaterTableColumn::make('time_mode')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.operations.columns.time-mode'))
                    ->markAsRequired()
                    ->resizable(),
                RepeaterTableColumn::make('manual_cycle_time')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.operations.columns.duration'))
                    ->markAsRequired()
                    ->resizable(),
            ])
            ->schema([
                Hidden::make('company_id'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('work_center_id')
                    ->relationship('workCenter', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Radio::make('time_mode')
                    ->options(OperationTimeMode::class)
                    ->default(OperationTimeMode::MANUAL->value)
                    ->inline(false)
                    ->live()
                    ->required(),
                TextInput::make('manual_cycle_time')
                    ->default('60:00')
                    ->rule('regex:/^\d+:\d{2}$/')
                    ->placeholder('60:00')
                    ->afterStateHydrated(function (TextInput $component, mixed $state): void {
                        $component->state(static::formatFloatTime($state ?? 60));
                    })
                    ->dehydrateStateUsing(fn (?string $state): string => static::parseFloatTime($state))
                    ->required(),
            ]);
    }

    protected static function getByProductsRepeater(): Repeater
    {
        return Repeater::make('byproducts')
            ->relationship('byproducts')
            ->hiddenLabel()
            ->defaultItems(0)
            ->compact()
            ->addActionLabel(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.by-products.add-action'))
            ->table([
                RepeaterTableColumn::make('product_id')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.by-products.columns.product'))
                    ->markAsRequired()
                    ->resizable(),
                RepeaterTableColumn::make('quantity')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.by-products.columns.quantity'))
                    ->markAsRequired()
                    ->resizable(),
                RepeaterTableColumn::make('uom_id')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.by-products.columns.uom'))
                    ->markAsRequired()
                    ->resizable(),
                RepeaterTableColumn::make('operation_id')
                    ->label(__('manufacturing::filament/clusters/products/resources/bill-of-material.form.tabs.by-products.columns.operation'))
                    ->resizable(),
            ])
            ->schema([
                Hidden::make('company_id'),
                Select::make('product_id')
                    ->relationship('product', 'name', fn (Builder $query) => $query->withTrashed())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                        $product = Product::query()->find($state);

                        if (! $product) {
                            return;
                        }

                        $set('uom_id', $product->uom_id);
                        $set('company_id', $get('../../company_id'));
                    }),
                TextInput::make('quantity')
                    ->numeric()
                    ->minValue(0.0001)
                    ->default(1)
                    ->step('0.0001')
                    ->required(),
                Select::make('uom_id')
                    ->options(fn (): array => UOM::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('operation_id')
                    ->relationship('operation', 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }

    protected static function formatFloatTime(mixed $state): string
    {
        $value = (float) ($state ?? 0);
        $hours = (int) floor($value);
        $minutes = (int) round(($value - $hours) * 60);

        if ($minutes === 60) {
            $hours++;
            $minutes = 0;
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    protected static function parseFloatTime(?string $state): string
    {
        if (! is_string($state) || ! preg_match('/^(?<hours>\d+):(?<minutes>\d{2})$/', $state, $matches)) {
            return '60';
        }

        $minutes = (int) $matches['minutes'];

        if ($minutes > 59) {
            return '60';
        }

        return (string) ((int) $matches['hours'] + ($minutes / 60));
    }

    protected static function matchesEnumState(mixed $state, BackedEnum $enum): bool
    {
        if ($state instanceof BackedEnum) {
            return $state->value === $enum->value;
        }

        return $state === $enum->value;
    }
}
