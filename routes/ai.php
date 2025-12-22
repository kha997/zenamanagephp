<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AI\AIController;

/*
|--------------------------------------------------------------------------
| AI-Powered Features Routes
|--------------------------------------------------------------------------
|
| These routes handle AI-powered features including:
| - Natural Language Processing (NLP)
| - Machine Learning Recommendations
| - Intelligent Task Assignment
| - Predictive Analytics
| - Smart Search and Filtering
| - Automated Content Generation
| - Sentiment Analysis
| - Risk Assessment
|
*/

Route::prefix('ai')->group(function () {
    // Project Analysis
    Route::post('/analyze-project', [AIController::class, 'analyzeProjectDescription'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('ai.analyze-project');

    // Task Assignment
    Route::post('/suggest-assignment', [AIController::class, 'suggestTaskAssignment'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('ai.suggest-assignment');

    // Predictive Analytics
    Route::post('/predict-success', [AIController::class, 'predictProjectSuccess'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('ai.predict-success');

    // Natural Language Processing
    Route::post('/process-query', [AIController::class, 'processNaturalLanguageQuery'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('ai.process-query');

    // Content Generation
    Route::post('/generate-content', [AIController::class, 'generateContent'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('ai.generate-content');

    // Sentiment Analysis
    Route::post('/analyze-sentiment', [AIController::class, 'analyzeSentiment'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('ai.analyze-sentiment');

    // Risk Assessment
    Route::post('/assess-risks', [AIController::class, 'assessProjectRisks'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('ai.assess-risks');

    // Project Recommendations
    Route::post('/get-recommendations', [AIController::class, 'getProjectRecommendations'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('ai.get-recommendations');

    // AI Service Information
    Route::get('/statistics', [AIController::class, 'getAIStatistics'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('ai.statistics');

    Route::get('/health', [AIController::class, 'getAIHealthStatus'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('ai.health');

    Route::get('/capabilities', [AIController::class, 'getAICapabilities'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('ai.capabilities');

    Route::get('/test-connectivity', [AIController::class, 'testAIConnectivity'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('ai.test-connectivity');
});
