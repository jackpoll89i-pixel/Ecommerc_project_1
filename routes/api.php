<?php


use App\Http\Controllers\AdminStatsController;
use App\Http\Controllers\AuthunticateRequestController;
use App\Http\Controllers\BanController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TestNotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdvController;
use App\Http\Controllers\ForgetPasswordController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\RecommendedController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\AdminPlatformWalletController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

require __DIR__. '/apiRoutes/Report/reportRoute.php';
require __DIR__. '/apiRoutes/Evaluation/evaluationRoute.php';

Route::post('/register',[AuthController::class,'Register']);
Route::post('/login',[AuthController::class,'login'])->middleware('throttle:5,1');
Route::post('/verify-code',[AuthController::class,'VerifyCode']);
Route::post('/resend-code',[AuthController::class,'ResendCode'])->middleware('throttle:3,10');
Route::post('/forget-password',[ForgetPasswordController::class,'forgotPassword']);
Route::post('/check-code',[ForgetPasswordController::class,'checkCode']);

Route::middleware('banned')->group(function () {

    Route::prefix('user')->group(function () {

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/update',    [AuthController::class, 'EditInformation']); // edit user info
            Route::get('/favorites', [AdvController::class, 'getUserFavorites']);
            Route::get('/', [AuthController::class, 'getUser']);
            Route::post('/wallet/charge', [WalletController::class, 'chargeRequest']);
        });
    });


    Route::prefix('adv')->group(function () {
        
        Route::get('/recommended-for-visitor', [RecommendedController::class, 'index']);
        Route::get('/show-visitor/{id}', [AdvController::class, 'showVisitor']);


        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/recommended-for-user', [RecommendedController::class, 'index']);
            Route::post('/create',       [AdvController::class, 'store']);
            Route::put('/update/{id}',    [AdvController::class, 'update']);
            Route::delete('/delete/{id}', [AdvController::class, 'destroy']);
            Route::post('/search',[AdvController::class,'search']);

            Route::post('/add-like', [AdvController::class, 'addLike']); // addLike
            Route::post('/remove-like', [AdvController::class, 'removeLike']); // removeLike

            Route::post('/add-favorite', [AdvController::class, 'addToFavorite']); // addFavorite
            Route::post('/remove-favorite', [AdvController::class, 'removeFromFavorite']); // removeFavorite

            Route::get('/all-user-advs',       [AdvController::class, 'userAdvs']);

            Route::get('/show-user/{id}', [AdvController::class, 'showUser']);

            Route::get('/get-recommendations-favourite', [RecommendedController::class, 'getRecommendedForUser']);
        });
    });

    Route::prefix('category')->group(function () {
        Route::get('/',     [CategoryController::class, 'index']);
        Route::get('get-adv-bycategory/{id}',     [CategoryController::class, 'getAdvByCategory']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/create',       [CategoryController::class, 'store']);
            Route::put('/update/{id}',    [CategoryController::class, 'update']);
            Route::delete('/delete/{id}', [CategoryController::class, 'destroy']);
        
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/reset-password',[ForgetPasswordController::class,'resetPassword']);

        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
        Route::post('/logout',        [AuthController::class, 'logout']);

        Route::get('/notifications', [NotificationController::class, 'index']);

        Route::get('/show-notification/{id}', [NotificationController::class, 'show']);

        Route::post('/store-fcm-token', [AuthController::class, 'storeFCM_Token']);

        Route::post('/mark-is-read',[NotificationController::class,'markAsRead']);

        Route::post('/follow/{id}',[FollowController::class,'follow']);
        
        Route::delete('/unfollow/{id}',[FollowController::class,'unfollow']);

        Route::post('/authenticate-request', [AuthunticateRequestController::class, 'store']);
        Route::get('/my-authenticate-request', [AuthunticateRequestController::class, 'myRequest']);

    });

});

    
    Route::prefix('admin')->group(function () {
        Route::get('/authenticate-requests', [AuthunticateRequestController::class, 'index']);
        Route::post('/authenticate-requests/{requestId}/approve', [AuthunticateRequestController::class, 'approve']);
        Route::post('/authenticate-requests/{requestId}/reject', [AuthunticateRequestController::class, 'reject']);

        Route::post('/ban/{userId}', [BanController::class, 'banUser']);
        Route::post('/unban/{userId}', [BanController::class, 'unbanUser']);
        Route::get('/user-bans', [BanController::class, 'getBanUsers']);

        Route::get('/',     [AdvController::class, 'index']);

        Route::get('/dashboard-stats', [AdminStatsController::class, 'getDashboardStats']);
    
        Route::post('/clear-stats-cache', [AdminStatsController::class, 'clearStatsCache']);

        // عرض الطلب lol
        Route::get('/transactions/pending', [AdminTransactionController::class, 'pendingRequests']);
        // الموافقة على الطلب
        Route::post('/transactions/{id}/approve', [AdminTransactionController::class, 'approveRequest']);
        // رفض الطلب
        Route::post('/transactions/{id}/reject', [AdminTransactionController::class, 'rejectRequest']);
        // عرض ارباح المنصة
        Route::get('/platform-wallet', [AdminPlatformWalletController::class, 'index']);
    });
