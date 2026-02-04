<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with('items', 'payments')
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($validated) {

            $order = Order::create([
                'status' => Order::STATUS_PENDING,
                'total_amount' => 0,
            ]);

            $total = 0;

            foreach ($validated['items'] as $item) {
                $subtotal = $item['quantity'] * $item['unit_price'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $subtotal,
                ]);

                $total += $subtotal;
            }

            $order->update([
                'total_amount' => $total,
            ]);

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order->load('items'),
            ], 201);
        });
    }

    public function update(Request $request, int $orderId): JsonResponse
    {
        $order = Order::with('items', 'payments')->find($orderId);

        if (! $order) {
            return response()->json([
                'message' => 'Order not found.'
            ], 404);
        }

        if ($order->payments()->exists()) {
            return response()->json([
                'message' => 'Order cannot be updated because it already has payments.'
            ], 409);
        }

        $validated = $request->validate([
            'status' => 'sometimes|in:pending,confirmed,cancelled',
            'items' => 'sometimes|array|min:1',
            'items.*.product_name' => 'required_with:items|string',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
        ]);

        DB::transaction(function () use ($order, $validated) {

            if (isset($validated['status'])) {
                $order->update(['status' => $validated['status']]);
            }

            if (isset($validated['items'])) {
                $total = 0;
                $productNames = [];

                foreach ($validated['items'] as $item) {
                    $order->items()->updateOrCreate(
                        ['product_name' => $item['product_name']],
                        [
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'subtotal' => $item['quantity'] * $item['unit_price'],
                        ]
                    );

                    $total += $item['quantity'] * $item['unit_price'];
                    $productNames[] = $item['product_name'];
                }

                $order->items()
                    ->whereNotIn('product_name', $productNames)
                    ->delete();

                $order->update(['total_amount' => $total]);
            }
        });

        return response()->json([
            'message' => 'Order updated successfully',
            'order' => $order->load('items')
        ]);
    }

    public function destroy(int $orderId): JsonResponse
    {
        $order = Order::with('payments')->find($orderId);

        if (! $order) {
            return response()->json([
                'message' => 'Order not found.'
            ], 404);
        }

        if ($order->payments()->exists()) {
            return response()->json([
                'message' => 'Order cannot be deleted because payments exist.'
            ], 409);
        }

        $order->delete();

        return response()->json(null, 204);
    }
}
