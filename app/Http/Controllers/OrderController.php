<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\OrderPaymentService; 

class OrderController extends Controller
{
   
    public function store(Request $request, OrderPaymentService $orderPaymentService)
    {
        $request->validate([
            'product_id' => 'required|integer', 
            'seller_id' => 'required|exists:users,id',
            'total_price' => 'required|numeric|min:1',
        ]);

        $buyerId = auth()->id();

        try {
        
            $order = Order::create([
                'buyer_id' => $buyerId,
                'seller_id' => $request->seller_id,
                'product_id' => $request->product_id,
                'total_price' => $request->total_price,
                'status' => 'pending', 
            ]);

            
            $orderPaymentService->holdOrderAmount($buyerId, $request->total_price, $order->id);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الطلب وحجز المبلغ بنجاح لحين استلام المنتج',
                'order' => $order
            ]);

        } catch (\Exception $e) {
        
            if(isset($order)) {
                $order->delete();
            }
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    
    public function completeOrder($orderId, OrderPaymentService $orderPaymentService)
    {   
        $order = Order::findOrFail($orderId);

        
        if ($order->buyer_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك بتأكيد هذا الطلب'], 403);
        }

        
        if ($order->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'هذا الطلب مكتمل أو ملغى مسبقاً'], 400);
        }

        try {
            
            $result = $orderPaymentService->releaseOrderAmount($order);

            
            
            $order->update(['status' => 'completed']);

            return response()->json([
                'success' => true,
                'message' => 'تم تأكيد الاستلام وتحويل الأرباح للبائع بنجاح',
                'details' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}