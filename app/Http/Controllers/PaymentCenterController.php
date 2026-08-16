<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Services\CenterPaymentService;


class PaymentCenterController extends Controller
{
    public function pay(Request $request, CenterPaymentService $paymentService)
    {
        $request->validate([
            'center_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:1'
        ]);

        try {
            
            $result = $paymentService->payToCenter(
                auth()->id(),
                $request->center_id,
                $request->amount
            );

            return response()->json([
                'success' => true,
                'message' => 'تم الدفع للمركز بنجاح',
                'details' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}