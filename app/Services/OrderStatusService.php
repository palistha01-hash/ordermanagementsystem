<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Validation\ValidationException;

class OrderStatusService
{
    public static function canTransition(Order $order, string $newStatus): bool
    {
        $current = $order->order_status;

        // If already completed, no changes allowed
        if ($current === 'completed') {
            return false;
        }

        // Only pending -> cancelled allowed for cancellation rule
        if ($newStatus === 'cancelled') {
            return $current === 'pending';
        }

        // Allowed transitions:
        $allowed = [
            'pending' => ['processing', 'cancelled'],
            'processing' => ['completed'],
            // cancelled/completed handled earlier
        ];

        return isset($allowed[$current]) && in_array($newStatus, $allowed[$current], true);
    }

    public static function assertCanTransition(Order $order, string $newStatus)
    {
        if (! self::canTransition($order, $newStatus)) {
            throw ValidationException::withMessages(['order_status' => ["Cannot change status from {$order->order_status} to {$newStatus}."]]);
        }
    }
}