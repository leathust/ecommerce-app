<?php

namespace App\Http\Requests;

use App\Services\CartIdentityService;

class StoreCartItemRequest extends CartMutationRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->exists('quantity')) {
            $this->merge(['quantity' => 1]);
        }
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartIdentityService::MAX_LINE_QUANTITY],
        ]);
    }

    public function messages(): array
    {
        return [
            'quantity.required' => __('frontend.cart.quantity_required'),
            'quantity.integer' => __('frontend.cart.quantity_integer'),
            'quantity.min' => __('frontend.cart.quantity_min'),
            'quantity.max' => __('frontend.cart.quantity_max', ['max' => CartIdentityService::MAX_LINE_QUANTITY]),
        ];
    }
}
