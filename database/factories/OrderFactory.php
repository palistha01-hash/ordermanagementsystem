<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User; // Required for linking a user to the order
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $items = [];
        $total = 0;
        
        // Generate 1 to 3 random items for the order
        $itemCount = $this->faker->numberBetween(1, 3);
        
        for ($i = 0; $i < $itemCount; $i++) {
            $quantity = $this->faker->numberBetween(1, 5);
            $price = $this->faker->randomFloat(2, 5, 500); // Price between 5.00 and 500.00
            $subtotal = $quantity * $price;
            $total += $subtotal;
            
            $items[] = [
                'product_name' => $this->faker->word() . ' ' . $this->faker->suffix(),
                'quantity' => $quantity,
                'price' => round($price, 2),
            ];
        }

        // Add a small mock tax/shipping amount (e.g., 10% of subtotal)
        $finalTotal = round($total * 1.10, 2);

        return [
            // Automatically creates a User if one is not provided when calling the factory
            'user_id'       => User::factory(), 
            
            'customer_name' => $this->faker->name(),
            
            // The structured array of items (will be JSON in DB due to model casting)
            'order_items'   => $items, 
            
            // Randomly pick an order status
            'order_status'  => $this->faker->randomElement([
                'pending', 
                'processing',               
                'cancelled'
            ]),
            
            // Calculated total amount
            'total_amount'  => $finalTotal, 
        ];
    }
}
