<?php

namespace App\Services\Products;

use App\Models\ProductPrice;
use InvalidArgumentException;

class PricingService
{
    public function calculateSellPrice(int $costPrice, string $mode, int $markupValue, ?int $manualSellPrice = null): int
    {
        return match ($mode) {
            ProductPrice::MODE_MANUAL => max(0, $manualSellPrice ?? $costPrice),
            ProductPrice::MODE_FIXED => max(0, $costPrice + $markupValue),
            ProductPrice::MODE_PERCENT => max(0, (int) round($costPrice + ($costPrice * $markupValue / 100))),
            default => throw new InvalidArgumentException("Unsupported pricing mode: {$mode}"),
        };
    }

    /**
     * Net profit formula from agents.md Section 7:
     * laba_bersih = harga_jual - harga_modal - biaya_gateway - alokasi_langganan
     */
    public function calculateNetProfit(
        int $sellPrice,
        int $costPrice,
        int $gatewayFee = 0,
        int $subscriptionAllocation = 0
    ): int {
        return $sellPrice - $costPrice - $gatewayFee - $subscriptionAllocation;
    }

    /**
     * Break-even simulation: given monthly subscription cost and net profit per transaction,
     * compute how many transactions are needed to break even.
     *
     * @return array{subscription_cost: int, profit_per_tx: int, break_even_transactions: ?int, profitable: bool}
     */
    public function simulateBreakEven(int $subscriptionCost, int $profitPerTx): array
    {
        if ($profitPerTx <= 0) {
            return [
                'subscription_cost' => $subscriptionCost,
                'profit_per_tx' => $profitPerTx,
                'break_even_transactions' => null,
                'profitable' => false,
            ];
        }

        return [
            'subscription_cost' => $subscriptionCost,
            'profit_per_tx' => $profitPerTx,
            'break_even_transactions' => (int) ceil($subscriptionCost / $profitPerTx),
            'profitable' => true,
        ];
    }
}
