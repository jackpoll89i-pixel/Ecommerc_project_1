<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PlatformWallet;
use App\Models\PlatformTransaction;

class AdminPlatformWalletController extends Controller
{
    
    public function index()
    {
        // seeder ==> id = 1
        $wallet = PlatformWallet::first();

        // all trans by latest
        $transactions = PlatformTransaction::orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب بيانات محفظة المنصة بنجاح',
            'data' => [
                'total_balance' => $wallet ? $wallet->balance : 0,
                'transactions' => $transactions
            ]
        ]);
    }
}