<?php

namespace App\Services;

use App\Models\PlatformWallet;
use App\Models\PlatformTransaction;
use Illuminate\Support\Facades\DB;

class PlatformWalletService
{
    /**
     *
     */
    public function addProfit($amount, $type, $referenceId = null, $notes = null)
    {
        // we assure the two op must initializ
        DB::transaction(function () use ($amount, $type, $referenceId, $notes) {
            
            // seeder to the wallet 
            $wallet = PlatformWallet::findOrFail(1);
            
            
            $wallet->increment('balance', $amount);

            // auth for transaction 
            PlatformTransaction::create([
                'amount' => $amount,
                'type' => $type,
                'reference_id' => $referenceId,
                'notes' => $notes,
            ]);
            
        });
    }
}