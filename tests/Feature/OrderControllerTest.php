<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function getOrderPayload(string $customerName)
    {
        return [
            'customer_name' => $customerName,

            'order_items'   => [
                ['product_name' => 'Widget Pro', 'quantity' => 2, 'price' => 100],
                ['product_name' => 'Service Plan', 'quantity' => 1, 'price' => 50],
            ],
            'total_amount'  => 250.00,
            'order_status'  => 'pending',
        ];
    }

    // --- UNAUTHENTICATED ACCESS TESTS ---

    public function test_unauthenticated_users_cannot_access_any_order_endpoints()
    {
        $order   = Order::factory()->create();
        $payload = $this->getOrderPayload('Guest');

        // Test Index (GET /api/orders)
        $this->getJson('/api/orders')->assertUnauthorized();

        // Test Store (POST /api/orders)
        $this->postJson('/api/orders', $payload)->assertUnauthorized();

        // Test Show (GET /api/orders/{id})
        $this->getJson("/api/orders/{$order->id}")->assertUnauthorized();

        // Test Update (PUT /api/orders/{id})
        $this->putJson("/api/orders/{$order->id}", $payload)->assertUnauthorized();

        // Test Delete (DELETE /api/orders/{id})
        $this->deleteJson("/api/orders/{$order->id}")->assertUnauthorized();
    }

    // --- STORE Method Tests (Create) ---

    public function test_authenticated_user_can_create_order()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $payload = $this->getOrderPayload($user->name);

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.customer_name', $user->name);

        $this->assertDatabaseHas('orders', [
            'customer_name' => $user->name,
            'user_id'       => $user->id,
            'total_amount'  => 250.00,
        ]);
    }

    public function test_order_creation_fails_with_missing_required_fields()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // Missing total_amount and order_items
        $payload = [
            'customer_name' => $user->name,
            'order_status'  => 'pending',
            // fields are missing here
        ];

        $response = $this->postJson('/api/orders', $payload);

        // Expect Validation Errors (422 Unprocessable Entity)
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_items', 'total_amount']);
    }

    // --- INDEX Method Tests (List & Filter) ---

    public function test_user_can_view_only_their_orders()
    {
        $owner     = User::factory()->create();
        $otherUser = User::factory()->create();

        // Create 3 orders for the owner, 1 for another user
        Order::factory()->count(3)->create(['user_id' => $owner->id, 'order_status' => 'pending']);
        Order::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($owner, ['*']);

        $response = $this->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonPath('total', 3) // Should only see their own 3 orders
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_filter_orders_by_status()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // Create orders with different statuses
        Order::factory()->count(2)->create(['user_id' => $user->id, 'order_status' => 'pending']);
        Order::factory()->count(5)->create(['user_id' => $user->id, 'order_status' => 'completed']);

        // Test filter for 'completed' orders
        $response = $this->getJson('/api/orders?status=completed');

        $response->assertStatus(200)
            ->assertJsonPath('total', 5)
            ->assertJsonCount(5, 'data');
    }

    public function test_user_can_filter_orders_by_date_range()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $threeDaysAgo = Carbon::today()->subDays(3);

        // Order 1: 3 days ago (outside range)
        Order::factory()->create(['user_id' => $user->id, 'created_at' => $threeDaysAgo]);
        // Order 2: Yesterday (in range)
        Order::factory()->create(['user_id' => $user->id, 'created_at' => $yesterday]);
        // Order 3: Today (in range)
        Order::factory()->create(['user_id' => $user->id, 'created_at' => $today]);

        // Filter from YESTERDAY to TODAY
        $from = $yesterday->toDateString();
        $to = $today->toDateString();
        $response = $this->getJson("/api/orders?from={$from}&to={$to}");

        $response->assertStatus(200)
                 ->assertJsonPath('total', 2) // Should only get 2 orders (yesterday and today)
                 ->assertJsonCount(2, 'data');
    }

    public function test_user_can_view_their_single_order()
    {
        $user  = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_user_cannot_view_another_users_order()
    {
        $user      = User::factory()->create();
        $otherUser = User::factory()->create();
        $order     = Order::factory()->create(['user_id' => $otherUser->id]); // Order belongs to otherUser

        Sanctum::actingAs($user, ['*']); 

        $response = $this->getJson("/api/orders/{$order->id}");

        $response->assertStatus(404) 
            ->assertJsonPath('message', 'Order not found.');
    }

    // --- UPDATE Method Tests (Edit) ---

    public function test_user_can_update_their_order()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
    
        Sanctum::actingAs($user, ['*']);
    
        $newPayload = [
            'customer_name' => $user->name,
            'order_items' => [
                ['product_name' => 'Laptop', 'quantity' => 2, 'price' => 200],
                ['product_name' => 'Mouse', 'quantity' => 1, 'price' => 100],
            ],
        ];
    
   
        $newPayload['total_amount'] = collect($newPayload['order_items'])
            ->sum(fn($i) => $i['quantity'] * $i['price']);
    
        $response = $this->putJson("/api/orders/{$order->id}", $newPayload);
    
        $response->assertStatus(200)
                 ->assertJsonPath('data.total_amount', $newPayload['total_amount']);
    
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'total_amount' => $newPayload['total_amount'],
        ]);
    }
    


    // --- DESTROY Method Tests (Soft Delete) ---

    public function test_user_can_soft_delete_their_order()
    {
        $user  = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->deleteJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Deleted.');

        $this->assertSoftDeleted('orders', [
            'id'      => $order->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_delete_another_users_order()
    {
        $user      = User::factory()->create();
        $otherUser = User::factory()->create();
        $order     = Order::factory()->create(['user_id' => $otherUser->id]);
        Sanctum::actingAs($user, ['*']);
        $response = $this->deleteJson("/api/orders/{$order->id}");

        $response->assertStatus(404) // Should fail with 404
            ->assertJsonPath('message', 'Order not found.');
    }


}
