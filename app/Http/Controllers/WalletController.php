<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction; 
use App\Models\Wallet;

class WalletController extends Controller
{
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
}