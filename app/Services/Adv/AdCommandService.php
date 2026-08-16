<?php

namespace App\Services\Adv;

use App\Models\Adv;
use App\Jobs\UpdateAdReadJob;
use App\Events\AdPublishedEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        $isFeatured = isset($data['is_featured']) && $data['is_featured'] ? true : false;
        $featuredCost = 50; // unique adv

        if ($isFeatured) {

            if (!$user->wallet || $user->wallet->balance < $featuredCost) {
                
                throw new \Exception('رصيدك غير كافٍ لتمييز الإعلان.');
            }
            
            $user->wallet->decrement('balance', $featuredCost);
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

        
        if ($isFeatured) {
            
            $platformWalletService = app(\App\Services\PlatformWalletService::class);
            $platformWalletService->addProfit($featuredCost, 'featured_ad', $createdAd->id, 'عمولة إعلان مميز');
        }

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