<?php


namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\PlatformWalletService;

class OrderPaymentService
{
    public function processSuccessfulSale($buyerId, $sellerId, $totalAmount, $orderId)
    {
        return DB::transaction(function () use ($buyerId, $sellerId, $totalAmount, $orderId) {
            $buyer = User::findOrFail($buyerId);
            $seller = User::findOrFail($sellerId);

            
            $commissionRate = $seller->is_verified ? 0 : 0.05; 
            
            $platformCommission = $totalAmount * $commissionRate;
            $sellerShare = $totalAmount - $platformCommission;

    
            $buyerWallet = $buyer->wallet()->firstOrCreate([], ['balance' => 0]);
            if ($buyerWallet->balance < $totalAmount) {
                throw new \Exception('رصيد المشتري غير كافٍ لإتمام عملية الشراء.');
            }
            $buyerWallet->decrement('balance', $totalAmount);

            \App\Models\Transaction::create([
                'wallet_id' => $buyerWallet->id,
                'amount' => $totalAmount,
                'type' => 'withdrawal',
                'status' => 'approved',
                'reference_id' => 'order_pay_' . $orderId,
            ]);
            
            $sellerWallet = $seller->wallet()->firstOrCreate([], ['balance' => 0]);
            $sellerWallet->increment('balance', $sellerShare);


            \App\Models\Transaction::create([
                'wallet_id' => $sellerWallet->id,
                'amount' => $sellerShare,
                'type' => 'deposit',
                'status' => 'approved',
                'reference_id' => 'order_receive_' . $orderId,
            ]);
            
            if ($platformCommission > 0) {
                app(PlatformWalletService::class)->addProfit(
                    amount: $platformCommission,
                    type: 'sale_commission',
                    referenceId: $orderId,
                    notes: "عمولة بيع للطلب رقم {$orderId} من البائع رقم {$seller->id}"
                );
            }

            return [
                'total_paid' => $totalAmount,
                'seller_received' => $sellerShare,
                'platform_commission' => $platformCommission
            ];
        });
    }
}