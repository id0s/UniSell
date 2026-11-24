<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'integer', 'min:0'],
            'period_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'product_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
