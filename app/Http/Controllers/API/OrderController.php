<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * List all orders
     *
     * Retrieve a paginated list of orders belonging to the authenticated user.
     * You can optionally filter by status and date range.
     *
     * @authenticated
     * @group Orders
     *
     * @header Authorization Bearer {token}
     *
     * @queryParam status string Filter by order status. Example: pending
     * @queryParam from date Filter orders created on or after this date (YYYY-MM-DD). Example: 2025-10-01
     * @queryParam to date Filter orders created on or before this date (YYYY-MM-DD). Example: 2025-10-29
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "customer_name": "John Doe",
     *       "order_items": [
     *         {
     *           "product_name": "Shampoo",
     *           "quantity": 45,
     *           "price": 56
     *         }
     *       ],
     *       "order_status": "pending",
     *       "total_amount": 2520,
     *       "created_at": "2025-10-29T10:45:36+00:00",
     *       "updated_at": "2025-10-29T10:45:36+00:00"
     *     }
     *   ]
     * }
     */
    public function index(Request $req)
    {
        try {
            $user  = $req->user();
            $query = Order::where('user_id', $user->id);

            if ($status = $req->query('status')) {
                $query->where('order_status', $status);
            }
            if ($from = $req->query('from')) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to = $req->query('to')) {
                $query->whereDate('created_at', '<=', $to);
            }

            $orders = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'data'         => OrderResource::collection($orders),
                'current_page' => $orders->currentPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch orders.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new order
     *
     * Stores a new order for the authenticated user.
     *
     * @authenticated
     * @group Orders
     *
     * @header Authorization Bearer {token}
     *
     * @bodyParam order_items array required The list of items in the order. Example: ["item1", "item2"]
     * @bodyParam total_amount numeric required The total price of the order. Example: 1499.50
     * @bodyParam order_status string The current status of the order. Example: pending
     *
     * @response 200 {
     *   "data": {
     *     "id": 6,
     *     "customer_name": "admin",
     *     "order_items": [
     *       {
     *         "product_name": "consequatur",
     *         "quantity": 1,
     *         "price": 56
     *       }
     *     ],
     *     "order_status": "pending",
     *     "total_amount": 56
     *   }
     * }
     */
    public function store(StoreOrderRequest $req)
    {
        try {
            $user = $req->user();

            $order = Order::create([
                'user_id'       => $user->id,
                'customer_name' => $user->name,
                'order_items'   => $req->order_items,
                'total_amount'  => $req->total_amount,
                'order_status'  => $req->input('order_status', 'pending'),
            ]);

            return new OrderResource($order);

        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to create order',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Show order details
     *
     * Retrieve a specific order by its ID belonging to the authenticated user.
     *
     * @authenticated
     * @group Orders
     *
     * @header Authorization Bearer {token}
     *
     * @urlParam id integer required The ID of the order. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 6,
     *     "customer_name": "admin",
     *     "order_items": [
     *       {
     *         "product_name": "consequatur",
     *         "quantity": 1,
     *         "price": 56
     *       }
     *     ],
     *     "order_status": "pending",
     *     "total_amount": 56
     *   }
     * }
     *
     * @response 404 {
     *   "message": "Order not found."
     * }
     *
     */

    public function show(Request $req, $id)
    {
        try {
            $user  = $req->user();
            $order = Order::where('id', $id)->where('user_id', $user->id)->firstOrFail();
            return new OrderResource($order);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch order details.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing order
     *
     * Update the details of an order (except completed orders).
     *
     * @authenticated
     * @group Orders
     *
     * @header Authorization Bearer {token}
     *
     * @urlParam id integer required The ID of the order to update. Example: 1
     * @bodyParam order_items array required Updated list of order items. Example: ["itemA", "itemB"]
     * @bodyParam total_amount numeric required Updated total price of the order. Example: 1999.99
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "customer_name": "John Doe",
     *       "order_items": [
     *         {
     *           "product_name": "Shampoo",
     *           "quantity": 45,
     *           "price": 56
     *         }
     *       ],
     *       "order_status": "pending",
     *       "total_amount": 2520,
     *       "created_at": "2025-10-29T10:45:36+00:00",
     *       "updated_at": "2025-10-29T10:45:36+00:00"
     *     }
     *   ]
     * }
     */
    public function update(UpdateOrderRequest $req, $id)
    {
        try {
            $user  = $req->user();
            $order = Order::where('id', $id)->where('user_id', $user->id)->firstOrFail();

            if ($order->order_status === 'completed') {
                return response()->json(['message' => 'Completed orders cannot be updated.'], 422);
            }

            $order->update([
                'customer_name' => $user->name,
                'order_items'   => $req->order_items,
                'total_amount'  => $req->total_amount,
            ]);

            return new OrderResource($order);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update order.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an order
     *
     * Permanently delete an order belonging to the authenticated user.
     *
     * @authenticated
     * @group Orders
     *
     * @header Authorization Bearer {token}
     *
     * @urlParam id integer required The ID of the order to delete. Example: 1
     *
     * @response 200 {
     *   "message": "Deleted."
     * }
     */
    public function destroy(Request $req, $id)
    {
        try {
            $order = Order::where('id', $id)->where('user_id', $req->user()->id)->firstOrFail();
            if ($order) {
                $order->deleted_at = date("Y-m-d H:i:s");
                $order->save();
            }
            return response()->json(['message' => 'Deleted.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete order.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update order status
     *
     * Change the status of an existing order.
     * Ensures valid status transition using the OrderStatusService.
     *
     * @authenticated
     * @group Orders
     *
     * @header Authorization Bearer {token}
     *
     * @urlParam id integer required The ID of the order. Example: 1
     * @bodyParam order_status string required The new status for the order. Example: completed
     *
     * @response 200 {
     *   "data": {
     *     "id": 5,
     *     "customer_name": "palistha",
     *     "order_items": [
     *       {
     *         "price": 56,
     *         "quantity": 1,
     *         "product_name": "washing machine"
     *       }
     *     ],
     *     "order_status": "processing",
     *     "total_amount": 56
     *   }
     * }
     *
     * @response 422 {
     *   "message": "Failed to update order status."
     *   "error": "Cannot change status from processing to pending."
     * }
     */
    public function updateStatus(UpdateOrderStatusRequest $req, $id)
    {
        try {
            $order     = Order::where('id', $id)->where('user_id', $req->user()->id)->firstOrFail();
            $newStatus = $req->order_status;

            OrderStatusService::assertCanTransition($order, $newStatus);

            $order->order_status = $newStatus;
            $order->save();

            return new OrderResource($order);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        } catch (\DomainException $e) {
            // Handle invalid status transition
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update order status.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
