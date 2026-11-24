<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'website' => ['nullable', 'url', 'max:255'],
            'docs_url' => ['nullable', 'url', 'max:255'],
            'auth_method' => ['required', 'in:api_key,bearer,basic,manual,sekalipay,digiflazz,vip_reseller'],
            'status' => ['sometimes', 'in:active,inactive,suspended'],
            'balance' => ['sometimes', 'integer', 'min:0'],
            'credentials' => ['nullable', 'array'],
            'credentials.api_id' => ['nullable', 'string', 'max:200'],
            'credentials.username' => ['nullable', 'string', 'max:200'],
            'credentials.api_key' => ['nullable', 'string', 'max:500'],
            'credentials.api_secret' => ['nullable', 'string', 'max:500'],
            'credentials.base_url' => ['nullable', 'url', 'max:255'],
            'credentials.sandbox' => ['sometimes', 'boolean'],
        ];
    }
}
