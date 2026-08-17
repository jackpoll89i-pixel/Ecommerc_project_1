<?php

namespace App\Services\Adv;

use App\Models\Adv;
use App\Jobs\UpdateAdReadJob;
use App\Events\AdPublishedEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\PlatformWalletService;
use App\Http\Controllers\WalletController;
use App\Models\Wallet;


class AdCommandService
{
    public function createAd($request): Adv
    {
    if (is_array($request)) {
        $data = $request;
    } else {
        
        $data = $request->only(['title', 'description', 'price', 'phone', 'category_id', 'location', 'is_featured']);
    }

    $data['user_id'] = auth()->id();

    $ad = DB::transaction(function () use ($data, $request) {
        
        $user = auth()->user();
        $isFeatured = isset($data['is_featured']) && $data['is_featured'] == true;
        $featuredCost = 50;
        $freeAdsLimit = 1; 

        if ($isFeatured) {
            $shouldPay = true; 

    
            if ($user->is_verified) {
                
                $featuredAdsThisMonth = Adv::where('user_id', $user->id)
                    ->where('is_featured', true)
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->count();

                if ($featuredAdsThisMonth < $freeAdsLimit) {
                    $shouldPay = false; 
                }
            }

            
            if ($shouldPay) {
                $userWallet = $user->wallet()->firstOrCreate([], ['balance' => 0]);

                if ($userWallet->balance < $featuredCost) {
                    throw new \Exception('رصيدك غير كافٍ لتمييز الإعلان. قم بتوثيق حسابك للحصول على تمييز مجاني شهرياً!');
                }

                
                $userWallet->decrement('balance', $featuredCost);
                \App\Models\Transaction::create([
                        'wallet_id' => $userWallet->id,
                        'amount' => $featuredCost,
                        'type' => 'withdrawal',
                        'status' => 'approved',
                        'reference_id' => 'ad_' . uniqid(), 
                    ]);

                app(PlatformWalletService::class)->addProfit(
                    amount: $featuredCost, 
                    type: 'ad_fee', 
                    notes: "رسوم تمييز إعلان من المستخدم رقم {$user->id}"
                );
            }
        }
        

        if (!is_array($request)) {
            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $filename = time() . '.' . $request->file('image')->extension();
                $path = Storage::disk('public')->putFileAs(
                    'advs',
                    $request->file('image'),
                    $filename,
                    ['visibility' => 'public']
                );
                $data['image'] = $path;
            }
        }

        
        $createdAd = Adv::create($data);

        return $createdAd;
    });

    UpdateAdReadJob::dispatch('created', $ad);

    AdPublishedEvent::dispatch($ad, auth()->user());

    return $ad;
    }

    public function updateAd(Adv $ad, $request): Adv
    {
        // Accept either a Request/FormRequest or an array of attributes
        if (is_array($request)) {
            $data = $request;
        } else {
            $data = $request->only(['title', 'description', 'price', 'phone', 'category_id','location']);
        }

        $updatedAd = DB::transaction(function () use ($ad, $data, $request) {

            if (!is_array($request)) {
                if ($request->hasFile('image') && $request->file('image')->isValid()) {
                    $filename = time() . '.' . $request->file('image')->extension();
                    $path = Storage::disk('public')->putFileAs(
                        'advs',
                        $request->file('image'),
                        $filename,
                        ['visibility' => 'public']
                    );
                    $data['image'] = $path;
                }
            }

            $ad->update($data);
            return $ad;
        });
        UpdateAdReadJob::dispatch('updated', $updatedAd);
        return $updatedAd;
    }
    
    public function deleteAd(Adv $ad): void
    {
        DB::transaction(function () use ($ad) {
            $ad->delete();
        });
        UpdateAdReadJob::dispatch('deleted', $ad);
    }
}