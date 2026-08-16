<?php


namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\PlatformWalletService;

class CenterPaymentService
{
    public function payToCenter($payerId, $centerId, $totalAmount)
    {
        
        $commissionRate = 0.10; 
        $platformCommission = $totalAmount * $commissionRate; 
        $centerShare = $totalAmount - $platformCommission;    

        return DB::transaction(function () use ($payerId, $centerId, $totalAmount, $platformCommission, $centerShare) {
            
            
            $user = User::findOrFail($payerId);
            $center = User::findOrFail($centerId);

            
            $payerWallet = $user->wallet()->firstOrCreate([], ['balance' => 0]);
            if ($payerWallet->balance < $totalAmount) {
                throw new \Exception('رصيدك غير كافٍ لإتمام عملية الدفع.');
            }

            
            $payerWallet->decrement('balance', $totalAmount);

            
            $centerWallet = $center->wallet()->firstOrCreate([], ['balance' => 0]);
            $centerWallet->increment('balance', $centerShare);

            
            app(PlatformWalletService::class)->addProfit(
                amount: $platformCommission,
                type: 'center_commission',
                notes: "عمولة دفع للمركز رقم {$center->id} من المستخدم رقم {$user->id}"
            );

            return [
                'total_paid' => $totalAmount,
                'center_received' => $centerShare,
                'platform_commission' => $platformCommission
            ];
        });
    }
}