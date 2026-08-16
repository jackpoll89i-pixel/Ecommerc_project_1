<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\OrderPaymentService; 

class OrderController extends Controller
{
    public function completeOrder($orderId, OrderPaymentService $orderPaymentService)
    {
        $order = Order::findOrFail($orderId);

        try {
            
            $result = $orderPaymentService->processSuccessfulSale(
                $order->buyer_id,
                $order->seller_id,
                $order->total_price,
                $order->id
            );

            $order->update(['status' => 'completed']);

            return response()->json([
                'success' => true,
                'message' => 'تمت عملية البيع وتحويل الأرباح للمنصة بنجاح',
                'details' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}