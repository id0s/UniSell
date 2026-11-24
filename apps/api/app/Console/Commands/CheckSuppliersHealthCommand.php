<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\Suppliers\SupplierDriverManager;
use Illuminate\Console\Command;

class CheckSuppliersHealthCommand extends Command
{
    protected $signature = 'suppliers:check-health';

    protected $description = 'Check API health and deposit balance for all suppliers';

    public function handle(SupplierDriverManager $driverManager): int
    {
        $suppliers = Supplier::with('credential')->get();

        foreach ($suppliers as $supplier) {
            $driver = $driverManager->resolve($supplier);
            $health = $driver->checkHealth($supplier);
            $balance = $driver->getBalance($supplier);

            $supplier->update([
                'health_status' => $health['health_status'],
                'balance' => $balance,
                'last_checked_at' => now(),
            ]);

            $this->line("[{$supplier->name}] health={$health['health_status']} balance=Rp{$balance}");
        }

        return self::SUCCESS;
    }
}
