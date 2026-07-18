<?php

namespace App\Services\Adv;

use App\Models\AdvRead;
use App\Models\Adv;
use App\Models\Evaluation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdQueryService
{

    
    public function searchActiveAds(array $filters)
    {
        $query = Adv::where('is_active', 1);
        
        if (!empty($filters['description'])) {
            $query->where('description', 'LIKE', '%' . $filters['description'] . '%');
        }
        
        if (!empty($filters['location'])) {
            $location = preg_quote(trim($filters['location']));
            $query->where('location', 'LIKE', '%' . $filters['location'] . '%');
        }
        
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        
        $min_price = $filters['min_price'] ?? null;
        $max_price = $filters['max_price'] ?? null;
        
        if ($min_price && $max_price) {
            $query->whereBetween('price', [min($min_price, $max_price), max($min_price, $max_price)]);
        } elseif ($min_price) {
            $query->where('price', '>=', $min_price);
        } elseif ($max_price) {
            $query->where('price', '<=', $max_price);
        }

        $ads = $query->orderBy('views_count', 'desc')->get();

        $ads->transform(function ($adv) {
        return $this->is_actionUser($adv);
        });

        return $ads;
        
    }  

    public function is_actionUser(Adv $adv)
    {
        $user = auth()->user();
        $adId = $adv->id; 

        $adv->is_liked = \DB::table('user_activities')
            ->where('user_id', $user->id)
            ->where('adv_id', $adId)
            ->where('activity_type','like')
            ->exists();

        $adv->is_favourite = \DB::table('favorites')
            ->where('user_id', $user->id)
            ->where('adv_id', $adId)
            ->exists();

            if($user->id != $adv->user_id)
            {
                $adv->is_follow = $user->isFollowing($adv->user_id);
            }

        return $adv;
    }

    public function getRecommendAdvs(Adv $adv)
    {
        $categoryId = $adv->category_id;
        $price = $adv->price;

        $group1 = Adv::query()
            ->where('id', '!=', $adv->id)
            ->where('category_id', $categoryId)
            ->latest()
            ->take(5)
            ->get();

        $group2 = Adv::query()
            ->where('id', '!=', $adv->id)
            ->where('category_id', $categoryId)
            ->whereBetween('price', [$price * 0.8, $price * 1.2])
            ->latest()
            ->take(5)
            ->get();

        $group3 = Adv::query()
            ->where('id', '!=', $adv->id)
            ->where('category_id', $categoryId)
            ->latest()
            ->take(5)
            ->get();

        $keywords = collect(explode(' ', $adv->description))
            ->filter(fn($word) => mb_strlen($word) > 2)
            ->take(5);

        $group4 = Adv::query()
            ->where('id', '!=', $adv->id)
            ->where('category_id', $categoryId)
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $word) {
                    $query->orWhere('description', 'LIKE', "%{$word}%");
                }
            })
            ->select('*')
            ->selectRaw(
                '(' . collect($keywords)->map(function($word) {
                    return "CASE WHEN description LIKE '%{$word}%' THEN 1 ELSE 0 END";
                })->implode(' + ') . ') as match_score'
            )
            ->orderByDesc('match_score')
            ->latest()
            ->take(5)
            ->get();

            return $group1
            ->merge($group2)
            ->merge($group3)
            ->merge($group4)
            ->unique('id')
            ->take(10)
            ->values()
            ->all();
    }

    public function updateAdvRate($id)
    {
        $adv = Adv::findOrFail($id);

        $usersRate = Evaluation::where('adv_id',$adv->id)->get();
        $rate = 0;
        foreach($usersRate as $userRate)
        {
            $rate += $userRate->rating;
        }
        $rate /= $usersRate->count();

        $adv->update(['rate' => $rate]);
    }


}