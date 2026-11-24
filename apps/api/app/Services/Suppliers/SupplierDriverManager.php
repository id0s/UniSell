<?php

namespace App\Services\Suppliers;

use App\Contracts\SupplierDriverInterface;
use App\Models\Supplier;

class SupplierDriverManager
{
    public function __construct(
        private readonly MockApiSupplierDriver $mockApiDriver,
        private readonly ManualSupplierDriver $manualDriver,
        private readonly SekaliPaySupplierDriver $sekaliPayDriver,
        private readonly DigiflazzSupplierDriver $digiflazzDriver,
        private readonly VipResellerSupplierDriver $vipResellerDriver,
    ) {}

    public function resolve(Supplier $supplier): SupplierDriverInterface
    {
        if ($supplier->auth_method === 'manual') {
            return $this->manualDriver;
        }

        if (
            $supplier->auth_method === 'vip_reseller'
            || str_contains(strtolower((string) $supplier->website), 'vip-reseller.co.id')
            || str_contains(strtolower($supplier->name), 'vip-reseller')
        ) {
            return $this->vipResellerDriver;
        }

        if (
            $supplier->auth_method === 'digiflazz'
            || str_contains(strtolower((string) $supplier->website), 'digiflazz.com')
            || str_contains(strtolower($supplier->name), 'digiflazz')
        ) {
            return $this->digiflazzDriver;
        }

        if (
            $supplier->auth_method === 'sekalipay'
            || str_contains(strtolower((string) $supplier->website), 'sekalipay.com')
            || str_contains(strtolower($supplier->name), 'sekalipay')
        ) {
            return $this->sekaliPayDriver;
        }

        return $this->mockApiDriver;
    }
}
