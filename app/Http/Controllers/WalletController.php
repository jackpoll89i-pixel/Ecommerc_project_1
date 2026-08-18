<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction; 
use App\Models\Wallet;

class WalletController extends Controller
{

    public function myWallet()
    {
        $user = auth()->user();
        
        
        $wallet = $user->wallet()->firstOrCreate([], ['balance' => 0]);

        
        $transactions = Transaction::where('wallet_id', $wallet->id)
                                    ->orderBy('created_at', 'desc')
                                    ->get();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب بيانات المحفظة بنجاح',
            'data' => [
                'current_balance' => $wallet->balance,
                'transactions' => $transactions
            ]
        ]);
    }


   public function chargeRequest(Request $request)
    {
    $request->validate([
        'amount' => 'required|numeric|min:1',
        'reference_id' => 'required|string',
        'receipt_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
    ]);

    $path = $request->file('receipt_image')->store('receipts', 'public');

    
    $wallet = auth()->user()->wallet()->firstOrCreate([], ['balance' => 0]);

    Transaction::create([
        'wallet_id' => $wallet->id, 
        'amount' => $request->amount,
        'type' => 'deposit',
        'reference_id' => $request->reference_id,
        'receipt_image' => $path,
        'status' => 'pending',
    ]);

    return response()->json(['message' => 'تم استلام طلب الشحن بنجاح وهو قيد المراجعة']);
    }



    public function withdrawRequest(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string', 
            'account_details' => 'required|string', 
        ]);

        $user = auth()->user();
        $wallet = $user->wallet()->firstOrCreate([], ['balance' => 0]);

        
        if ($wallet->balance < $request->amount) {
            return response()->json(['success' => false, 'message' => 'رصيدك غير كافٍ لطلب السحب'], 400);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($wallet, $request) {
            
            $wallet->decrement('balance', $request->amount);

            
            Transaction::create([
                'wallet_id' => $wallet->id,
                'amount' => $request->amount,
                'type' => 'withdrawal',
                'status' => 'pending', 
                'reference_id' => 'withdraw_' . uniqid(),
                'notes' => "طلب سحب عبر: {$request->payment_method} | التفاصيل: {$request->account_details}"
            ]);
        });

        return response()->json([
            'success' => true, 
            'message' => 'تم استلام طلب السحب بنجاح وهو قيد المراجعة من الإدارة'
        ]);
    }
}