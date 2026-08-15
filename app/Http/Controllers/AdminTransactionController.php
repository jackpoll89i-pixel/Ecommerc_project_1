<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class AdminTransactionController extends Controller
{
    
    public function pendingRequests()
    {
        
        $transactions = Transaction::with('wallet.user')
                            ->where('status', 'pending')
                            ->where('type', 'deposit')
                            ->get();

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    
    public function approveRequest($id)
    {
        $transaction = Transaction::findOrFail($id);

        if ($transaction->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'هذا الطلب تمت معالجته مسبقاً.'], 400);
        }

        
        DB::transaction(function () use ($transaction) {
            $transaction->update(['status' => 'approved']);
            $transaction->wallet->increment('balance', $transaction->amount);
        });

        return response()->json(['success' => true, 'message' => 'تمت الموافقة وشحن رصيد المستخدم بنجاح.']);
    }


    public function rejectRequest($id)
    {
        $transaction = Transaction::findOrFail($id);

        if ($transaction->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'هذا الطلب تمت معالجته مسبقاً.'], 400);
        }

        $transaction->update(['status' => 'rejected']);

        return response()->json(['success' => true, 'message' => 'تم رفض طلب الشحن.']);
    }
}