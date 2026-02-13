<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParserTask;
use App\Models\Review;
use App\Models\Setting;
use App\Services\ReviewParserService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(
        private ReviewParserService $parserService
    ) {}

    /**
     * GET /api/reviews?page=1
     */
    public function index(Request $request)
    {
        $yandexUrl = Setting::where('key', 'yandex_url')->value('value');

        if (!$yandexUrl) {
            return response()->json([
                'error' => 'Yandex URL not configured',
                'reviews' => [],
                'statistics' => $this->emptyStats(),
                'pagination' => $this->emptyPagination(),
            ], 400);
        }

        $orgId = $this->parserService->extractOrgId($yandexUrl);
        if (!$orgId) {
            return response()->json(['error' => 'Invalid Yandex URL'], 400);
        }

        $page = max(1, (int) $request->query('page', 1));
        $totalInDb = Review::where('organization_id', $orgId)->count();
        $activeTask = $this->parserService->getActiveTask($orgId);
        $lastTask = $this->parserService->getLastCompletedTask($orgId);

        // Если нет данных и нет задачи — запускаем парсинг
        if ($totalInDb === 0 && !$activeTask) {
            $activeTask = $this->parserService->startParsing($yandexUrl);
        }

        $orgData = null;
        $totalExpected = 0;
        $isParsing = false;

        if ($activeTask) {
            $orgData = $activeTask->organization_data;
            $totalExpected = $activeTask->total_expected;
            $isParsing = in_array($activeTask->status, ['pending', 'running', 'paused']);
        }

        if (!$orgData && $lastTask) {
            $orgData = $lastTask->organization_data;
            $totalExpected = $lastTask->total_expected;
        }

        if (!$orgData) {
            $orgData = $this->parserService->fetchOrganizationQuick($yandexUrl);
            $totalExpected = $orgData['review_count'] ?? 0;
        }

        // Парсинг идёт, но данных ещё нет
        if ($totalInDb === 0 && $isParsing) {
            return response()->json([
                'status' => 'parsing',
                'message' => 'Идёт загрузка отзывов, подождите...',
                'reviews' => [],
                'statistics' => [
                    'total' => $totalExpected,
                    'average_rating' => $orgData['rating'] ?? 0,
                    'positive' => 0, 'negative' => 0, 'neutral' => 0,
                ],
                'pagination' => [
                    'total' => $totalExpected,
                    'per_page' => self::PER_PAGE,
                    'current_page' => 1,
                    'last_page' => max(1, (int) ceil($totalExpected / self::PER_PAGE)),
                    'has_more' => false,
                ],
                'organization' => $orgData,
                'cached_at' => null,
                'is_parsing' => true,
                'is_complete' => false,
                'total_parsed' => 0,
                'parser_progress' => $this->getProgress($activeTask),
            ]);
        }

        // Пагинация из БД
        $reviewsQuery = Review::where('organization_id', $orgId)
            ->orderByDesc('review_date')
            ->orderByDesc('id');

        $totalParsed = $reviewsQuery->count();
        $totalPages = max(1, (int) ceil(max($totalParsed, $totalExpected) / self::PER_PAGE));
        $parsedPages = max(1, (int) ceil($totalParsed / self::PER_PAGE));

        $reviews = $reviewsQuery
            ->skip(($page - 1) * self::PER_PAGE)
            ->take(self::PER_PAGE)
            ->get()
            ->map(fn($r) => [
                'id' => $r->review_id,
                'author' => $r->author,
                'text' => $r->text,
                'rating' => $r->rating,
                'published_at' => $r->published_at,
            ])
            ->toArray();

        $statistics = $this->calculateStatistics($orgId, $totalExpected, $orgData['rating'] ?? 0);
        $isComplete = !$isParsing && $totalParsed >= $totalExpected;

        return response()->json([
            'status' => 'ok',
            'reviews' => $reviews,
            'statistics' => $statistics,
            'pagination' => [
                'total' => max($totalParsed, $totalExpected),
                'per_page' => self::PER_PAGE,
                'current_page' => $page,
                'last_page' => $totalPages,
                'has_more' => $page < $parsedPages,
            ],
            'organization' => $orgData,
            'cached_at' => $lastTask?->completed_at?->toIso8601String() ?? $activeTask?->started_at?->toIso8601String(),
            'is_parsing' => $isParsing,
            'is_complete' => $isComplete,
            'total_parsed' => $totalParsed,
            'parser_progress' => $isParsing ? $this->getProgress($activeTask) : null,
        ]);
    }

    /**
     * POST /api/reviews/parse — принудительный перепарсинг
     */
    public function parse(Request $request)
    {
        $yandexUrl = Setting::where('key', 'yandex_url')->value('value');

        if (!$yandexUrl) {
            return response()->json(['error' => 'Yandex URL not configured'], 400);
        }

        try {
            $task = $this->parserService->startParsing($yandexUrl, force: true);

            return response()->json([
                'status' => 'parsing',
                'message' => 'Парсинг запущен. Отзывы загружаются постранично через очередь.',
                'is_parsing' => true,
                'organization' => $task->organization_data,
                'statistics' => [
                    'total' => $task->total_expected,
                    'average_rating' => $task->organization_data['rating'] ?? 0,
                    'positive' => 0, 'negative' => 0, 'neutral' => 0,
                ],
                'parser_progress' => $this->getProgress($task),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    private function calculateStatistics(string $orgId, int $totalExpected, float $orgRating): array
    {
        $total = Review::where('organization_id', $orgId)->count();
        if ($total === 0) {
            return ['total' => $totalExpected, 'average_rating' => $orgRating, 'positive' => 0, 'negative' => 0, 'neutral' => 0];
        }

        $avgRating = round(Review::where('organization_id', $orgId)->avg('rating'), 1);
        $positive = Review::where('organization_id', $orgId)->where('rating', '>=', 4)->count();
        $negative = Review::where('organization_id', $orgId)->where('rating', '<=', 2)->count();

        return [
            'total' => max($total, $totalExpected),
            'average_rating' => $avgRating,
            'positive' => $positive,
            'negative' => $negative,
            'neutral' => $total - $positive - $negative,
        ];
    }

    private function getProgress(?ParserTask $task): ?array
    {
        if (!$task) return null;
        return [
            'status' => $task->status,
            'current_page' => $task->current_page,
            'total_pages' => $task->total_pages,
            'total_parsed' => $task->total_parsed,
            'total_expected' => $task->total_expected,
            'phase' => $task->current_phase,
            'last_error' => $task->last_error,
        ];
    }

    private function emptyStats(): array
    {
        return ['total' => 0, 'average_rating' => 0, 'positive' => 0, 'negative' => 0, 'neutral' => 0];
    }

    private function emptyPagination(): array
    {
        return ['total' => 0, 'per_page' => self::PER_PAGE, 'current_page' => 1, 'last_page' => 1, 'has_more' => false];
    }
}
