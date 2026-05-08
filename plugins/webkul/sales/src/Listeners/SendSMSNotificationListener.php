<?php

namespace Webkul\Sale\Listeners;

use Webkul\Account\Events\MovePaid;
use Webkul\PluginManager\Package;

class SendSMSNotificationListener
{
    public function handle(MovePaid $event): void
    {
        if (! Package::isPluginInstalled('sales')) {
            return;
        }

        // dd($event->move);
    }
}
