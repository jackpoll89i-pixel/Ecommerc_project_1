<?php


namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\PlatformWalletService;

class OrderPaymentService
{
    public function processSuccessfulSale($buyerId, $sellerId, $totalAmount, $orderId)
    {
        
        $commissionRate = 0.05; 
        $platformCommission = $totalAmount * $commissionRate; 
        $sellerShare = $totalAmount - $platformCommission;   

        return DB::transaction(function () use ($buyerId, $sellerId, $totalAmount, $platformCommission, $sellerShare, $orderId) {
            
            
            $buyer = User::findOrFail($buyerId);
            $seller = User::findOrFail($sellerId);

            
            $buyerWallet = $buyer->wallet()->firstOrCreate([], ['balance' => 0]);
            if ($buyerWallet->balance < $totalAmount) {
                throw new \Exception('رصيد المشتري غير كافٍ لإتمام عملية الشراء.');
            }

        
            $buyerWallet->decrement('balance', $totalAmount);

            
            $sellerWallet = $seller->wallet()->firstOrCreate([], ['balance' => 0]);
            $sellerWallet->increment('balance', $sellerShare);

            
            app(PlatformWalletService::class)->addProfit(
                amount: $platformCommission,
                type: 'sale_commission', 
                referenceId: $orderId,   
                notes: "عمولة بيع للطلب رقم {$orderId} المباع من قبل البائع رقم {$seller->id}"
            );

            return [
                'total_paid' => $totalAmount,
                'seller_received' => $sellerShare,
                'platform_commission' => $platformCommission
            ];
        });
    }
}