<?php

namespace Webkul\Manufacturing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkOrderProductionAvailability: string implements HasColor, HasLabel
{
    case ASSIGNED = 'assigned';

    public static function options(): array
    {
        return [
            self::ASSIGNED->value => __('manufacturing::enums/work-order-production-availability.assigned'),
        ];
    }

    public function getLabel(): string
    {
        return self::options()[$this->value];
    }

    public function getColor(): string
    {
        return 'success';
    }
}
