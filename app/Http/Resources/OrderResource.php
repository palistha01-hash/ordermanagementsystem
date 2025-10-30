<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'customer_name' => $this->customer_name,
            'order_items' => $this->order_items,
            'order_status' => $this->order_status,
            'total_amount' => (float) $this->total_amount,      
        ];
    }
}