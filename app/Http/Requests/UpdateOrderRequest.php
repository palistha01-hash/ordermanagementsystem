<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true; // authorize all users (controller checks ownership)
    }

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
        // Validate total_amount equals sum of order_items
        $validator->after(function ($v) {
            $items = $this->input('order_items', []);
            $sum = 0;
            foreach ($items as $item) {
                $sum += ($item['quantity'] ?? 0) * ($item['price'] ?? 0);
            }
            if (abs($sum - floatval($this->input('total_amount', 0))) > 0.01) {
                $v->errors()->add('total_amount', 'Total amount does not match sum of order_items.');
            }
        });
    }
}
