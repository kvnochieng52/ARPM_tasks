<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index()
    {
        // Eager load relationships to avoid N+1 query problem
        $orders = Order::with([
            'customer',
            'items.product',
            'cartItems' => function ($query) {
                $query->orderByDesc('created_at')->limit(1);
            }
        ])
            ->select('id', 'customer_id', 'status', 'created_at', 'completed_at')
            ->get();

        $orderData = $orders->map(function ($order) {
            // Calculate total amount and count items
            $totalAmount = $order->items->sum(function ($item) {
                return $item->price * $item->quantity;
            });

            // Get last added cart item 
            $lastAddedToCart = $order->cartItems->first()->created_at ?? null;

            return [
                'order_id' => $order->id,
                'customer_name' => $order->customer->name,
                'total_amount' => $totalAmount,
                'items_count' => $order->items->count(),
                'last_added_to_cart' => $lastAddedToCart,
                'completed_order_exists' => $order->status === 'completed',
                'created_at' => $order->created_at,
                'completed_at' => $order->completed_at,
            ];
        });

        // Sort by completed_at date.
        $sortedOrderData = $orderData->sortByDesc('completed_at')->values()->all();

        return view('orders.index', ['orders' => $sortedOrderData]);
    }
}
