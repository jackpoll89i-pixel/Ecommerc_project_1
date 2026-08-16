<?php

namespace App\Services;

use App\Models\PlatformWallet;
use App\Models\PlatformTransaction;
use Illuminate\Support\Facades\DB;

class PlatformWalletService
{
    public function addProfit($amount, $type, $referenceId = null, $notes = null)
    {
        return DB::transaction(function () use ($amount, $type, $referenceId, $notes) {
            
            
            $platformWallet = PlatformWallet::first();

            if (!$platformWallet) {
                throw new \Exception('محفظة المنصة غير موجودة.');
            }

            
            $platformWallet->increment('balance', $amount);

            
            PlatformTransaction::create([
                'platform_wallet_id' => $platformWallet->id,
                'amount' => $amount,
                'type' => $type, 
                'reference_id' => $referenceId,
                'notes' => $notes
            ]);

            return true;
        });
    }
}