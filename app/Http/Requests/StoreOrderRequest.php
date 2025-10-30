<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [          
            'order_items' => 'required|array|min:1',
            'order_items.*.product_name' => 'required|string',
            'order_items.*.quantity' => 'required|integer|min:1',
            'order_items.*.price' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            if (! $this->validateTotalMatches()) {
                $v->errors()->add('total_amount', 'Total amount does not match sum of order_items.');
            }
        });
    }

    protected function validateTotalMatches()
    {
        $items = $this->input('order_items', []);
        $sum = 0;
        foreach ($items as $it) {
            $qty = $it['quantity'] ?? 0;
            $price = $it['price'] ?? 0;
            $sum += ($qty * $price);
        }
        // float tolerance
        return abs($sum - floatval($this->input('total_amount', 0))) < 0.01;
    }
}
