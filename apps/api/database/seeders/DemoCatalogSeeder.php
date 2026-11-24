<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\SupplierCredential;
use App\Models\SupplierPackage;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Products\ProductAggregatorService;
use App\Services\Suppliers\SupplierSubscriptionService;
use Illuminate\Database\Seeder;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (Supplier::exists()) {
            return;
        }

        $admin = User::where('email', 'admin@unisell.local')->first();

        $catGame = ProductCategory::firstOrCreate(['slug' => 'game-topup'], ['name' => 'Game Topup']);
        $catApp = ProductCategory::firstOrCreate(['slug' => 'aplikasi-premium'], ['name' => 'Aplikasi Premium']);
        $catVoucher = ProductCategory::firstOrCreate(['slug' => 'voucher-digital'], ['name' => 'Voucher Digital']);

        // Supplier 1: API Supplier with active subscription
        $apiSupplier = Supplier::create([
            'name' => 'NusaVoucher API',
            'website' => 'https://nusavoucher.example.com',
            'docs_url' => 'https://docs.nusavoucher.example.com',
            'auth_method' => 'api_key',
            'status' => Supplier::STATUS_ACTIVE,
            'health_status' => Supplier::HEALTH_HEALTHY,
            'balance' => 7500000,
            'last_checked_at' => now(),
        ]);

        SupplierCredential::create([
            'supplier_id' => $apiSupplier->id,
            'payload' => [
                'api_key' => 'demo_key_nusavoucher',
                'api_secret' => 'demo_secret_encrypted_in_db',
                'base_url' => 'https://api.nusavoucher.example.com',
            ],
        ]);

        $pkgPro = SupplierPackage::create([
            'supplier_id' => $apiSupplier->id,
            'name' => 'API Pro Bulanan',
            'price' => 150000,
            'period_days' => 30,
            'product_limit' => 250,
        ]);

        app(SupplierSubscriptionService::class)->subscribe($apiSupplier, $pkgPro, $admin?->id);

        $aggregator = app(ProductAggregatorService::class);
        $aggregator->syncFromSupplier($apiSupplier, $admin?->id);

        $categoryMap = [
            'VCH-ML-86' => [$catGame->id, 'Topup instan Mobile Legends 86 Diamonds langsung masuk ID.', 3500],
            'VCH-FF-140' => [$catGame->id, 'Topup cepat Free Fire 140 Diamonds resmi & otomatis 24 jam.', 3300],
            'LIC-CANVA-30D' => [$catApp->id, 'Akses Canva Pro 30 hari legal untuk desain tanpa batas.', 10000],
            'VCH-STEAM-60K' => [$catVoucher->id, 'Kode Steam Wallet IDR 60.000 dikirim otomatis setelah bayar.', 4500],
        ];

        foreach ($apiSupplier->supplierProducts as $sp) {
            [$catId, $desc, $markup] = $categoryMap[$sp->external_id] ?? [$catVoucher->id, 'Produk digital instan.', 3000];
            $aggregator->createStorefrontProduct([
                'supplier_product_id' => $sp->id,
                'category_id' => $catId,
                'name' => $sp->name,
                'description' => $desc,
                'status' => Product::STATUS_ACTIVE,
                'pricing_mode' => 'fixed',
                'markup_value' => $markup,
            ], $admin?->id);
        }

        // Supplier 2: Manual Fallback Supplier
        $manualSupplier = Supplier::create([
            'name' => 'Studio Lisensi Manual',
            'website' => 'https://studiolisensi.example.com',
            'auth_method' => 'manual',
            'status' => Supplier::STATUS_ACTIVE,
            'health_status' => Supplier::HEALTH_HEALTHY,
            'balance' => 0,
            'last_checked_at' => now(),
        ]);

        $pkgManual = SupplierPackage::create([
            'supplier_id' => $manualSupplier->id,
            'name' => 'Partner Manual Gratis',
            'price' => 0,
            'period_days' => 365,
            'product_limit' => 50,
        ]);

        app(SupplierSubscriptionService::class)->subscribe($manualSupplier, $pkgManual, $admin?->id);

        $manualSp = SupplierProduct::create([
            'supplier_id' => $manualSupplier->id,
            'external_id' => 'MAN-TEMPLATE-UI',
            'name' => 'UI Kit SaaS Neon Pack',
            'cost_price' => 45000,
            'stock_status' => 'available',
            'raw' => ['manual' => true],
            'synced_at' => now(),
        ]);

        $aggregator->createStorefrontProduct([
            'supplier_product_id' => $manualSp->id,
            'category_id' => $catApp->id,
            'name' => 'UI Kit SaaS Neon Pack',
            'description' => 'Koleksi komponen desain UI dark neon (fulfillment manual oleh admin).',
            'status' => Product::STATUS_ACTIVE,
            'pricing_mode' => 'fixed',
            'markup_value' => 25000,
        ], $admin?->id);
    }
}
