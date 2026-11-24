<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reports_return_financial_summary_and_supplier_evaluation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@unisell.local')->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/reports')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'financial' => [
                        'omzet_idr',
                        'modal_idr',
                        'biaya_gateway_idr',
                        'biaya_langganan_idr',
                        'laba_bersih_idr',
                    ],
                    'operations' => [
                        'total_orders',
                        'delivered_orders',
                        'manual_review_orders',
                        'failure_rate_percent',
                    ],
                    'top_products',
                    'supplier_evaluations',
                ],
            ]);
    }
}
