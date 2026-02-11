<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\YandexSettings;
use App\Services\YandexReviewsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ReviewsController extends Controller
{
    protected $yandexService;

    public function __construct(YandexReviewsService $yandexService)
    {
        $this->yandexService = $yandexService;
    }

    public function index(Request $request)
    {
        $settings = YandexSettings::where('user_id', Auth::id())->first();
        
        $allReviews = Review::where('user_id', Auth::id())->get();
        $totalReviews = $allReviews->count();
        $averageRating = $allReviews->avg('rating') ?? 0;
        
        $reviews = Review::where('user_id', Auth::id())
            ->orderBy('date', 'desc')
            ->paginate(20);

        return Inertia::render('Reviews', [
            'reviews' => $reviews,
            'totalReviews' => $totalReviews,
            'averageRating' => round($averageRating, 1),
            'hasSettings' => $settings !== null,
        ]);
    }

    public function sync(Request $request)
    {
        $settings = YandexSettings::where('user_id', Auth::id())->first();

        if (!$settings) {
            return back()->with('error', 'Сначала настройте ссылку на Яндекс в настройках');
        }

        try {
            $reviews = $this->yandexService->fetchReviews($settings->yandex_url);
            
            $syncedCount = 0;
            $skippedCount = 0;
            
            foreach ($reviews as $reviewData) {
                if (empty($reviewData['text']) || strlen(trim($reviewData['text'])) < 10) {
                    $skippedCount++;
                    continue;
                }
                
                if (empty($reviewData['external_id'])) {
                    $reviewData['external_id'] = md5($reviewData['text'] . $reviewData['date'] . ($reviewData['reviewer_name'] ?? ''));
                }
                
                Review::updateOrCreate(
                    [
                        'user_id' => Auth::id(),
                        'yandex_settings_id' => $settings->id,
                        'external_id' => $reviewData['external_id'],
                    ],
                    [
                        'date' => $reviewData['date'],
                        'branch' => $reviewData['branch'] ?? 'Филиал 1',
                        'reviewer_name' => !empty($reviewData['reviewer_name']) ? $reviewData['reviewer_name'] : 'Аноним',
                        'reviewer_phone' => $reviewData['reviewer_phone'] ?? null,
                        'rating' => min(max($reviewData['rating'] ?? 5, 1), 5), // Ограничиваем рейтинг от 1 до 5
                        'text' => trim($reviewData['text']),
                    ]
                );
                $syncedCount++;
            }
            
            \Log::info("Synced {$syncedCount} reviews, skipped {$skippedCount} empty reviews");

            if ($syncedCount > 0) {
                $message = "Успешно синхронизировано отзывов: {$syncedCount}";
                if ($skippedCount > 0) {
                    $message .= " (пропущено пустых: {$skippedCount})";
                }
                return redirect()->route('reviews')->with('success', $message);
            } else {
                $errorMsg = 'Отзывы не найдены. ';
                $errorMsg .= 'Возможно, Яндекс Карты загружают отзывы через JavaScript. ';
                $errorMsg .= 'Проверьте логи для подробностей.';
                return back()->with('error', $errorMsg);
            }
        } catch (\Exception $e) {
            \Log::error('Sync error: ' . $e->getMessage());
            return back()->with('error', 'Ошибка при синхронизации: ' . $e->getMessage());
        }
    }
}
