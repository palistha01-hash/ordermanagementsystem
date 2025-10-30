<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'order_status' => 'required|in:pending,processing,completed,cancelled',
        ];
    }
}
