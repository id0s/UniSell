<?php

namespace App\Console\Commands;

use App\Services\Suppliers\SupplierSubscriptionService;
use Illuminate\Console\Command;

class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Mark overdue supplier subscriptions as expired and lock affected products';

    public function handle(SupplierSubscriptionService $subscriptionService): int
    {
        $expiredCount = $subscriptionService->expireOverdueSubscriptions();
        $this->info("Expired {$expiredCount} supplier subscriptions.");

        return self::SUCCESS;
    }
}
