<?php

namespace App\Http\Controllers;

use App\Models\Adv;
use App\Models\User;
use App\Services\Adv\AdQueryService;
use App\Repositories\AdvRepository;
use App\Traits\ApiResponse;
use DB;
use Illuminate\Http\Request;

class RecommendedController extends Controller
{
    use ApiResponse;

    public function __construct(
        public AdQueryService $queryService,
        public AdvRepository $advrepository,
    ) {}

    public function index(Request $request)
    {
        $limitParam = (int) $request->get('limit', 20);
        $limit = max(1, min(50, $limitParam));

        if (auth()->check()) {
            // dump('User is checked: ' . auth()->id());
            return $this->getSmartRecommendations($request, $limit);
        }
        // dump('User is checked: ' . auth()->id());
        $ads = Adv::with('user:id,name', 'category:id,name')
            ->where('is_active', 1)
            ->orderBy('interactions_count', 'desc')
            ->orderBy('views_count', 'desc')
            ->limit($limit)
            ->get();

        return $this->success('success', $ads);
    }

    public function getSmartRecommendations(Request $request, $limit = 20)
    {
        $user = auth()->user();
        
        
        $recentAds = $user->activitiesAdvertisements()
            ->orderBy('user_activities.created_at', 'desc')
            ->take(3)
            ->get();

        if ($recentAds->isEmpty()) {
            return $this->getGeneralRecommendations($limit);
        }


        $smartAds = $this->getAdsBasedOnRecentActivities($recentAds, $limit);
        
        $followingAds = $this->advrepository->getFollowingUsersAds($user->id, 3);
        
        $recommendedAds = $this->mergeFollowingAdsWithRecommendations($smartAds, $followingAds, $limit);

        $recommendedAds = $recommendedAds->map(function (Adv $ad) {
            return $this->queryService->is_actionUser($ad);
        });

        return $this->success('تم جلب التوصيات الذكية بنجاح', $recommendedAds);
    }


    private function getGeneralRecommendations($limit)
    {
        $user = auth()->user();
        $ads = collect();
        
        $followingAds = $this->advrepository->getFollowingUsersAds($user->id, 3);
        
        if ($followingAds->isNotEmpty()) {
            $ads = $ads->merge($followingAds);
        }
        
        $remainingLimit = $limit - $ads->count();
        if ($remainingLimit > 0) {
            $excludeIds = $ads->pluck('id')->toArray();
            $generalAds = $this->advrepository->getGeneralRecommendations($remainingLimit);
            
            $generalAds = $generalAds->whereNotIn('id', $excludeIds);
            $ads = $ads->merge($generalAds);
        }
        
        $ads = $ads->take($limit);

        $ads = $ads->map(function (Adv $ad) {
            return $this->queryService->is_actionUser($ad);
        });

        return $this->success('تم جلب التوصيات العامة بنجاح', $ads);
    }


    private function getAdsBasedOnRecentActivities($recentAds, $limit)
    {
        $categoryIds = $recentAds->pluck('category_id')->filter()->unique();
        // dd($categoryIds);
        $locationKeywords = $recentAds->pluck('location')->filter()->unique();

        $ads = $this->advrepository->getAdsByCategoriesAndLocations(
            $categoryIds, 
            $locationKeywords, 
            $limit, 
            auth()->id()
        );

        if ($ads->count() < $limit && $categoryIds->isNotEmpty()) {
            $additionalLimit = $limit - $ads->count();
            $additionalAds = $this->advrepository->getAdditionalAdsByCategories(
                $categoryIds,
                $ads->pluck('id'),
                $additionalLimit,
                auth()->id()
            );

            $ads = $ads->merge($additionalAds);
        }

        return $ads;
    }

 
    private function mergeFollowingAdsWithRecommendations($smartAds, $followingAds, $limit)
    {
        $mergedAds = collect();
        
        if ($followingAds->isNotEmpty()) {
            $mergedAds = $mergedAds->merge($followingAds);
        }
        
        if ($smartAds->isNotEmpty()) {
            $excludeIds = $mergedAds->pluck('id')->toArray();
            $filteredSmartAds = $smartAds->whereNotIn('id', $excludeIds);
            $mergedAds = $mergedAds->merge($filteredSmartAds);
        }
        
        if ($mergedAds->count() < $limit) {
            $remainingLimit = $limit - $mergedAds->count();
            $excludeIds = $mergedAds->pluck('id')->toArray();
            $additionalAds = $this->advrepository->getGeneralRecommendations($remainingLimit);
            $filteredAdditionalAds = $additionalAds->whereNotIn('id', $excludeIds);
            $mergedAds = $mergedAds->merge($filteredAdditionalAds);
        }
        
        return $mergedAds->take($limit)->values();
    }

    public function getRecommendedForUser()
    {
        $user = auth()->user();
        // الحصول على الفئات المفضلة للمستخدم
        $preferredCategories = $this->advrepository->getUserPreferredCategories($user->id);

        // dd($preferredCategories);
        
        // إذا كان لدى المستخدم فئات مفضلة، استخدمها للحصول على التوصيات
        if (!empty($preferredCategories)) {
            $recommendedAds = $this->advrepository->getRecommendedAdsForUser(
                $preferredCategories, 
                10, 
                $user->id
            );
            
            return $this->success('تم العثور على توصيات بناءً على اهتماماتك', $recommendedAds);
        } else {
            // إذا لم يكن لدى المستخدم تفضيلات، قدم توصيات عامة
            $fallbackAds = $this->advrepository->getFallbackRecommendations(10, $user->id);
            
            return $this->success('توصيات عامة قد تهمك', $fallbackAds);
        }
    }

    public function getUserPreferredCategories(User $user)
    {
        $preferredCategories = $this->advrepository->getUserPreferredCategories($user->id);
        
        if (!empty($preferredCategories)) {
            // الحصول على أسماء الفئات أيضًا
            $categoriesWithNames = DB::table('categories')
                ->whereIn('id', $preferredCategories)
                ->pluck('name', 'id');
                
            return $this->success('الفئات المفضلة', [
                'category_ids' => $preferredCategories,
                'categories' => $categoriesWithNames
            ]);
        } else {
            return $this->success('لم يتم العثور على فئات مفضلة', [
                'category_ids' => [],
                'categories' => []
            ]);
        }
}

    //     public function getRecommendationsForUser($id): array
    // {
    //     $adv = $this->advrepository->findAdv($id);

    //     $categoryId = $adv->category_id;
    //     $price = $adv->price;

    //     $group1 = Adv::query()
    //         ->where('id', '!=', $adv->id)
    //         ->where('category_id', $categoryId)
    //         ->latest()
    //         ->take(5)
    //         ->get();

    //     $group2 = Adv::query()
    //         ->where('id', '!=', $adv->id)
    //         ->where('category_id', $categoryId)
    //         ->whereBetween('price', [$price * 0.8, $price * 1.2])
    //         ->latest()
    //         ->take(5)
    //         ->get();

    //     $group3 = Adv::query()
    //         ->where('id', '!=', $adv->id)
    //         ->where('category_id', $categoryId)
    //         ->latest()
    //         ->take(5)
    //         ->get();

    //     $keywords = collect(explode(' ', $adv->description))
    //         ->filter(fn($word) => mb_strlen($word) > 2)
    //         ->take(5);

    //     $group4 = Adv::query()
    //         ->where('id', '!=', $adv->id)
    //         ->where('category_id', $categoryId)
    //         ->where(function ($query) use ($keywords) {
    //             foreach ($keywords as $word) {
    //                 $query->orWhere('description', 'LIKE', "%{$word}%");
    //             }
    //         })
    //         ->select('*')
    //         ->selectRaw(
    //             '(' . collect($keywords)->map(function($word) {
    //                 return "CASE WHEN description LIKE '%{$word}%' THEN 1 ELSE 0 END";
    //             })->implode(' + ') . ') as match_score'
    //         )
    //         ->orderByDesc('match_score')
    //         ->latest()
    //         ->take(5)
    //         ->get();

    //     return $group1
    //         ->merge($group2)
    //         ->merge($group3)
    //         ->merge($group4)
    //         ->unique('id')
    //         ->take(10)
    //         ->values()
    //         ->all();
    // }

   







    // public function test(Request $request)
    // {
    //     $text = $request->input('text');

    //     $keywords = collect(explode(' ', $text))
    //     ->filter(fn($word) => mb_strlen($word) > 2)
    //     ->take(5);

    // $group4 = Adv::query()
    //     // ->where('id', '!=', $adv->id)
    //     // ->where('category_id', $categoryId)
    //     ->where(function ($query) use ($keywords) {
    //         foreach ($keywords as $word) {
    //             $query->orWhere('description', 'LIKE', "%{$word}%");
    //         }
    //     })
    //     ->select('*')
    //     ->selectRaw(
    //         '(' . collect($keywords)->map(function($word) {
    //             return "CASE WHEN description LIKE '%{$word}%' THEN 1 ELSE 0 END";
    //         })->implode(' + ') . ') as match_score'
    //     )
    //     ->orderByDesc('match_score')
    //     ->latest()
    //     ->take(5)
    //     ->get();

    //     return response()->json(["results"=>$group4,"keywords"=>$keywords]);
    // }
}


