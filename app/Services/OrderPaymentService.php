<?php


namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\PlatformWalletService;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Http\Controllers\WalletController;
use App\Events\GenericNotificationEvent; 
use App\Repositories\UserRepository; 
use App\Repositories\BanUserRepository;

class OrderPaymentService
{   

    public function holdOrderAmount($buyerId, $totalAmount, $orderId)
    {
        return DB::transaction(function () use ($buyerId, $totalAmount, $orderId) {
            $buyer = User::findOrFail($buyerId);
            $buyerWallet = $buyer->wallet()->firstOrCreate([], ['balance' => 0]);

            if ($buyerWallet->balance < $totalAmount) {
                throw new \Exception('رصيد المشتري غير كافٍ لإتمام عملية الشراء.');
            }

            
            $buyerWallet->decrement('balance', $totalAmount);

            
            Transaction::create([
                'wallet_id' => $buyerWallet->id,
                'amount' => $totalAmount,
                'type' => 'escrow_hold', 
                'status' => 'approved',
                'reference_id' => 'order_hold_' . $orderId,
            ]);

            return true;
        });
    }



    public function releaseOrderAmount(Order $order)
    {
        return DB::transaction(function () use ($order) {
            $seller = User::findOrFail($order->seller_id);
            $buyer = User::findOrFail($order->buyer_id);

            
            $commissionRate = $buyer->is_verified ? 0 : 0.05; 
            
            $platformCommission = $order->total_price * $commissionRate;
            $sellerShare = $order->total_price - $platformCommission;

        

            
            $sellerWallet = $seller->wallet()->firstOrCreate([], ['balance' => 0]);
            $sellerWallet->increment('balance', $sellerShare);

            Transaction::create([
                'wallet_id' => $sellerWallet->id,
                'amount' => $sellerShare,
                'type' => 'deposit',
                'status' => 'approved',
                'reference_id' => 'order_receive_' . $order->id,
            ]);

            
            if ($platformCommission > 0) {
                app(PlatformWalletService::class)->addProfit(
                    amount: $platformCommission,
                    type: 'sale_commission',
                    referenceId: $order->id,
                    notes: "عمولة بيع للطلب رقم {$order->id}"
                );
            }

            $order->update(['status' => 'completed']);

            return ['seller_received' => $sellerShare, 'platform_commission' => $platformCommission];
        });
    }


    public function refundAndBanSeller(Order $order)
    {
        return DB::transaction(function () use ($order) {
            $buyer = User::findOrFail($order->buyer_id);
            $seller = User::findOrFail($order->seller_id);

            
            $buyerWallet = $buyer->wallet()->firstOrCreate([], ['balance' => 0]);
            $buyerWallet->increment('balance', $order->total_price);

            Transaction::create([
                'wallet_id' => $buyerWallet->id,
                'amount' => $order->total_price,
                'type' => 'refund',
                'status' => 'approved',
                'reference_id' => 'order_refund_' . $order->id,
            ]);

            
            $userRepo = app(UserRepository::class);
            $banRepo = app(BanUserRepository::class);

            $reason = "عدم تسليم المنتج للمشتري في الوقت المحدد للطلب رقم " . $order->id;

        
            $banData = [
                'reason' => $reason,
                'is_permanent' => true, 
            ];
            $banRepo->createBan($banData, $seller->id);

            
            $userRepo->update($seller, [
                'is_banned' => true,
                'banned_at' => now(),
                'ban_reason' => $reason
            ]);

            
            GenericNotificationEvent::dispatch($seller, 'Permenant_Ban', []);

        
            $userRepo->deleteUserToken($seller);

        
            $order->update(['status' => 'cancelled']);

            return true;
        });
    }

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