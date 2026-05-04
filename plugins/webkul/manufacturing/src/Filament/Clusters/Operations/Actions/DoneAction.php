<?php

namespace Webkul\Manufacturing\Filament\Clusters\Operations\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Component;
use Throwable;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Manufacturing\Facades\Manufacturing as ManufacturingFacade;
use Webkul\Manufacturing\Models\Order;
use Webkul\Support\Filament\Forms\Components\Repeater;
use Webkul\Support\Filament\Forms\Components\Repeater\TableColumn as RepeaterTableColumn;

class DoneAction extends Action
{
    protected bool|Closure $hasDatabaseTransactions = true;

    public static function getDefaultName(): ?string
    {
        return 'manufacturing.order.done';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('manufacturing::filament/clusters/operations/actions/done.label'))
            ->modal(fn (Order $record) => $this->hasAnyCondition($record))
            ->fillForm(fn (Order $record) => $this->buildFormData($record))
            ->form($this->buildForm())
            ->extraModalFooterActions(fn (Order $record) => $this->getExtraFooterActions($record))
            ->action(function (Order $record, Component $livewire): void {
                $this->executeDone($record, $livewire);
            })
            ->hidden(fn () => ! in_array($this->getRecord()->state, [
                ManufacturingOrderState::CONFIRMED,
                ManufacturingOrderState::PROGRESS,
            ]));
    }

    private function hasAnyCondition(Order $record): bool
    {
        return $this->hasConsumptionIssues($record);
        // || $this->hasSomeOtherCondition($record)
    }

    public function getModalHeading(): string|Htmlable
    {
        $record = $this->getRecord();

        if ($record instanceof Order && $this->hasConsumptionIssues($record)) {
            return __('manufacturing::filament/clusters/operations/actions/done.modal.consumption-warning.heading');
        }

        return parent::getModalHeading();
    }

    public function getModalDescription(): string|Htmlable|null
    {
        $record = $this->getRecord();

        if ($record instanceof Order && $this->hasConsumptionIssues($record)) {
            return __('manufacturing::filament/clusters/operations/actions/done.modal.consumption-warning.description');
        }

        return parent::getModalDescription();
    }

    public function getModalSubmitActionLabel(): string
    {
        $record = $this->getRecord();

        if ($record instanceof Order && $this->hasConsumptionIssues($record)) {
            return __('manufacturing::filament/clusters/operations/actions/done.modal.consumption-warning.actions.validate.label');
        }

        return parent::getModalSubmitActionLabel();
    }

    /**
     * @return array<Action>
     */
    private function getExtraFooterActions(Order $record): array
    {
        if ($this->hasConsumptionIssues($record)) {
            return [
                Action::make('set-quantities')
                    ->label(__('manufacturing::filament/clusters/operations/actions/done.modal.consumption-warning.actions.set-quantities.label'))
                    ->color('gray')
                    ->cancelParentActions(),
            ];
        }

        return [];
    }

    private function buildFormData(Order $record): array
    {
        $data = [];

        if ($this->hasConsumptionIssues($record)) {
            $data['consumed_moves_issues'] = $this->getConsumedMovesData($record);
        }

        // if ($this->hasSomeOtherCondition($record)) {
        //     $data['other_key'] = [...];
        // }

        return $data;
    }

    private function buildForm(): array
    {
        return [
            $this->makeConsumedIssuesRepeater(),
            // $this->makeSomeOtherRepeater(),
        ];
    }

    private function hasConsumptionIssues(Order $record): bool
    {
        return ! empty($record->getConsumptionIssues());
    }

    private function makeConsumedIssuesRepeater(): Repeater
    {
        return Repeater::make('consumed_moves_issues')
            ->hiddenLabel()
            ->deletable(false)
            ->addable(false)
            ->reorderable(false)
            ->visible(fn (Order $record) => $this->hasConsumptionIssues($record))
            ->table([
                RepeaterTableColumn::make('product_name')
                    ->label(__('manufacturing::filament/clusters/operations/actions/done.modal.consumption-warning.form.product')),
                RepeaterTableColumn::make('uom')
                    ->label(__('manufacturing::filament/clusters/operations/actions/done.modal.consumption-warning.form.uom')),
                RepeaterTableColumn::make('to_consume')
                    ->label(__('manufacturing::filament/clusters/operations/actions/done.modal.consumption-warning.form.to-consume')),
                RepeaterTableColumn::make('consumed')
                    ->label(__('manufacturing::filament/clusters/operations/actions/done.modal.consumption-warning.form.consumed')),
            ])
            ->schema([
                TextEntry::make('product_name')->hiddenLabel(),
                TextEntry::make('uom')->hiddenLabel(),
                TextEntry::make('to_consume')->hiddenLabel(),
                TextEntry::make('consumed')->hiddenLabel(),
            ]);
    }

    private function getConsumedMovesData(Order $record): array
    {
        return collect($record->getConsumptionIssues())
            ->map(fn ($issue) => [
                'product_name' => $issue[1]->name,
                'to_consume'   => $issue[3],
                'consumed'     => $issue[2],
                'uom'          => $issue[1]->uom->name,
            ])
            ->values()
            ->all();
    }

    // private function makeSomeOtherRepeater(): Repeater
    // {
    //     return Repeater::make('some_other_key')
    //         ->visible(fn (Order $record) => $this->hasSomeOtherCondition($record))
    //         ->hiddenLabel()
    //         ->deletable(false)
    //         ->addable(false)
    //         ->reorderable(false)
    //         ->schema([
    //             TextEntry::make('message')->hiddenLabel(),
    //         ]);
    // }

    private function executeDone(Order $record, Component $livewire): void
    {
        try {
            ManufacturingFacade::doneManufacturingOrder($record);

            $record->refresh();

            $livewire->updateForm();

            Notification::make()
                ->success()
                ->title(__('manufacturing::filament/clusters/operations/actions/done.notification.success.title'))
                ->body(__('manufacturing::filament/clusters/operations/actions/done.notification.success.body'))
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->body($e->getMessage())
                ->send();

            $this->halt(shouldRollBackDatabaseTransaction: true);
        }
    }
}
