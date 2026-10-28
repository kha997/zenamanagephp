<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\ChangeRequest\Controllers\ChangeRequestController;

/*
|--------------------------------------------------------------------------
| Change Request API Routes
|--------------------------------------------------------------------------
|
| Định nghĩa các route API cho module Change Request
| Các route này được gắn vào prefix /api/v1 trong routes/api.php và hưởng middleware bảo mật chung.
| Compatibility surface only: canonical forward owner is
| App\Http\Controllers\Api\ChangeRequestController on /api/zena/change-requests.
|
*/

Route::prefix('change-requests')->group(function () {
        // GAP-099: only creation is served here; the other v1 change-request routes never
        // worked and were retired. Use /api/zena/change-requests.
        Route::post('/', [ChangeRequestController::class, 'store'])
            ->middleware('rbac:change_request.create')
            ->name('change-requests.store');
    });
