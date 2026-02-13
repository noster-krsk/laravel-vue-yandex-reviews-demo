<?php

namespace App\Services;

use App\Jobs\ParseReviewPageJob;
use App\Models\ParserTask;
use App\Models\Review;
use Illuminate\Support\Facades\Log;

class ReviewParserService
{
    public function startParsing(string $yandexUrl, bool $force = false): ParserTask
    {
        $orgId = ParserTask::extractOrgId($yandexUrl);

        if (!$orgId) {
            throw new \InvalidArgumentException('Cannot extract organization ID from URL');
        }

        $existing = ParserTask::where('organization_id', $orgId)
            ->whereIn('status', ['pending', 'running', 'paused'])
            ->first();

        if ($existing && !$force) {
            return $existing;
        }

        if ($existing && $force) {
            $existing->update(['status' => 'failed', 'last_error' => 'Cancelled by force restart']);
        }

        $orgData = $this->fetchOrganizationQuick($yandexUrl);
        $totalExpected = $orgData['review_count'] ?? 0;

        if ($force) {
            Review::where('organization_id', $orgId)->delete();
        }

        $task = ParserTask::create([
            'organization_id' => $orgId,
            'yandex_url' => $yandexUrl,
            'status' => 'pending',
            'total_expected' => $totalExpected,
            'total_parsed' => 0,
            'current_page' => 0,
            'total_pages' => $totalExpected > 0 ? (int) ceil($totalExpected / 50) : 0,
            'current_phase' => 'ssr',
            'organization_data' => $orgData,
            'started_at' => now(),
        ]);

        ParseReviewPageJob::dispatch($task->id)
            ->onQueue('parser')
            ->delay(now()->addSeconds(2));

        Log::info('ReviewParserService: started parsing', [
            'task' => $task->id, 'org' => $orgId, 'expected' => $totalExpected,
        ]);

        return $task;
    }

    public function getActiveTask(string $orgId): ?ParserTask
    {
        return ParserTask::where('organization_id', $orgId)
            ->whereIn('status', ['pending', 'running', 'paused'])
            ->latest()
            ->first();
    }

    public function getLastCompletedTask(string $orgId): ?ParserTask
    {
        return ParserTask::where('organization_id', $orgId)
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first();
    }

    public function fetchOrganizationQuick(string $url): ?array
    {
        try {
            $reviewsUrl = rtrim(preg_replace('#/reviews/?$#', '', rtrim($url, '/')), '/') . '/reviews/';
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
                ],
            ]);
            $html = @file_get_contents($reviewsUrl, false, $ctx);
            if (!$html) return null;

            $reviewCount = 0;
            if (preg_match('/itemProp=["\']reviewCount["\'][^>]*content=["\'](\d+)["\']/i', $html, $m)) {
                $reviewCount = (int) $m[1];
            }
            if ($reviewCount === 0 && preg_match('/"reviewCount"\s*:\s*(\d+)/', $html, $m)) {
                $reviewCount = (int) $m[1];
            }

            $rating = 0;
            if (preg_match('/itemProp=["\']ratingValue["\'][^>]*content=["\']([0-9.]+)["\']/i', $html, $m)) {
                $rating = round((float) $m[1], 1);
            }
            if ($rating == 0 && preg_match('/"ratingValue"\s*:\s*([0-9.]+)/', $html, $m)) {
                $rating = round((float) $m[1], 1);
            }

            $name = '';
            if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
                $name = trim(preg_replace('/\s*[-\x{2013}\x{2014}]\s*(Яндекс\s*Карты|отзывы).*$/iu', '', $m[1]));
                $name = trim(preg_replace('/^Отзывы о «(.+?)».*$/u', '$1', $name));
            }

            return ['name' => $name, 'rating' => $rating, 'review_count' => $reviewCount];
        } catch (\Throwable $e) {
            Log::error('fetchOrganizationQuick error', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function extractOrgId(string $url): ?string
    {
        return ParserTask::extractOrgId($url);
    }
}
