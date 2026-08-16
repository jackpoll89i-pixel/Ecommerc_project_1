<?php

namespace App\Http\Controllers;


use App\Events\GenericNotificationEvent;
use App\Traits\ApiResponse;

use App\Services\Adv\AdCommandService;
use App\Services\Adv\AdQueryService;
use App\Services\Favorites\FavoriteCommandService;
use App\Services\Likes\LikeAnAdvCommandService;

use App\Http\Requests\Adv\StoreAdRequest;
use App\Http\Requests\Adv\SearchAdRequest;
use App\Http\Requests\Adv\UpdateAdRequest;
use App\Http\Requests\AddLikeToAdvRequest;

use App\Repositories\AdvRepository;

use App\Models\Adv;
use App\Models\UserActivities;

use App\Repositories\FavoriteRepository;
use App\Repositories\LikeRepository;


use Carbon\Carbon;

class AdvController extends Controller
{
    use ApiResponse;
    public function __construct(
        public AdCommandService $commandService,
        public AdQueryService $queryService,
        public AdvRepository $advRepository,
        public LikeAnAdvCommandService $likeCommandService,
        public LikeRepository $likeRepository,
        public FavoriteCommandService $favoriteCommandService,
        public FavoriteRepository $favoriteRepository
    ) {}

    public function index()
    {
        return Adv::with('user:id,name', 'category:id,name')->get();
    }

    public function userAdvs()
    {
        $user = auth()->user();
        $advs = Adv::with('category:name','user:id,name')->where('user_id',$user->id)->get();
        return $this->success('Advs for ' . $user->name . ' are:', $advs);
    }

    public function store(StoreAdRequest $request)
    {   

        try {
        $ad = $this->commandService->createAd($request);

        return $this->success('تم نشر الإعلان بنجاح',$ad);
        }
        // exception for create ad in adcommand service
        catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 400);
        }
    }
    

    public function search(SearchAdRequest $request)
    {
        $results = $this->queryService->searchActiveAds($request->validated());
        return $this->success('نتائج البحث',$results);
    }

    public function update(UpdateAdRequest $request,$id)
    {
        $ad =  $this->advRepository->findAdv($id);
        
        $updatedAd = $this->commandService->updateAd($ad, $request);
        //**begin FCM Instruction **
         event(new GenericNotificationEvent(
            user:auth()->user(),
            type:'Update Adv',
            data:[]
        ));
        //**End FCM Instruction**
        return $this->success('تم تعديل الإعلان بنجاح',$updatedAd);
    }

    public function showVisitor($id)
    {
        $ad = Adv::with('category', 'user')->findOrFail($id);
        
        $createdAt = $ad->created_at ? Carbon::parse($ad->created_at) : Carbon::now();
        $published_duration = Carbon::now()->diffInDays($createdAt, false); 
        $published_duration = abs((int) $published_duration); 

        return $this->success('The Resuls Are :',[$ad,$published_duration]);
    }

    public function showUser($id)
    {
        $ad = Adv::with('category', 'user')->findOrFail($id);
        
        $createdAt = $ad->created_at ? Carbon::parse($ad->created_at) : Carbon::now();
        $published_duration = Carbon::now()->diffInDays($createdAt, false); 
        $published_duration = abs((int) $published_duration); 

        if (!UserActivities::where('user_id', auth()->id())->where('activity_type', 'view')->where('adv_id', $ad->id)->exists()) {
            $this->likeCommandService->addView($ad);

            $viewsCount = $ad->views_count + 1;

            $ad = $this->commandService->updateAd($ad, ['views_count' => $viewsCount]);
        }

        $adv = $this->queryService->is_actionUser($ad);

        $isOwner = auth()->id() === $ad->user_id;
        
        $recommended_Adv = $isOwner ? [] : $this->queryService->getRecommendAdvs($ad);
        

        return $this->success('The Resuls Are :',
        [$adv, 
         $published_duration,
         'Recommends Advs'=> $recommended_Adv
        ]);
    }

    public function destroy($id)
    {
        $ad =  $this->advRepository->findAdv($id);

        $this->commandService->deleteAd($ad);

        return $this->success('تم حذف الإعلان بنجاح');
    }

    public function addLike(AddLikeToAdvRequest $request)
    {
        $like = $this->likeCommandService->addLike($request->validated());

        $adv = $this->advRepository->findAdv($request['adv_id']);

        $interactionsCount = $adv->interactions_count + 1;

        $this->commandService->updateAd($adv, ['interactions_count' => $interactionsCount]);

        return $this->success('success',$like);
    }

    public function removeLike(AddLikeToAdvRequest $request)
    {

        $like = $this->likeRepository->findLike($request['adv_id']);

        $this->likeCommandService->removeLike($like);

        $adv = $this->advRepository->findAdv($request['adv_id']);

        $interactionsCount = $adv->interactions_count - 1;

        $this->commandService->updateAd($adv, ['interactions_count' => $interactionsCount]);

        return $this->success('success');
    }

    public function addToFavorite(AddLikeToAdvRequest $request)
    {
        $favorite = $this->favoriteCommandService->addToFavorite($request->validated());

        return $this->success('success',$favorite);
    }

    public function removeFromFavorite(AddLikeToAdvRequest $request)
    {

        $favorite = $this->favoriteRepository->findFavorite($request['adv_id']);

        $this->favoriteCommandService->removeFromFavorite($favorite);

        return $this->success('success');
    }

    public function getUserFavorites()
    {
        $user = auth()->user();
        $favorites = $this->favoriteRepository->findFavoritesByUserId($user->id);
        return $this->success('success',$favorites);
    }
}
