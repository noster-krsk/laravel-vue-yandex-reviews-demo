<?php

namespace App\Jobs;

use App\Models\ParserTask;
use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Парсер отзывов Яндекс.Карт через parse-yandex.js.
 * Запускает node в фоне, поллит файлы на диске и сохраняет отзывы в БД по мере получения.
 */
class ParseReviewPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(
        public int $taskId,
    ) {}

    public function handle(): void
    {
        $task = ParserTask::find($this->taskId);
        if (!$task || in_array($task->status, ['completed', 'failed'])) {
            return;
        }

        $task->update(['status' => 'running', 'started_at' => now()]);

        $orgId = $task->organization_id;
        $scriptPath = base_path('scripts/parse-yandex.js');
        $cookieDir = storage_path('app/parser_cookies/' . $orgId);
        $pidFile = storage_path('app/parser_cookies/' . $orgId . '_pid');

        if (!is_dir($cookieDir)) {
            mkdir($cookieDir, 0755, true);
        }

        // Очистим старые batch файлы
        foreach (glob($cookieDir . '/batch__page_*.json') as $f) {
            @unlink($f);
        }
        @unlink($cookieDir . '/batch__meta.json');

        Log::info("ParseReviewPageJob: starting parse-yandex.js", [
            'task' => $task->id, 'org' => $orgId,
        ]);

        // Запускаем node в фоне
        $cmd = sprintf(
            'HOME=/tmp TMPDIR=/tmp node %s %s %s %s 2>/dev/null & echo $!',
            escapeshellarg($scriptPath),
            escapeshellarg($task->yandex_url),
            escapeshellarg($cookieDir),
            escapeshellarg('batch_')
        );

        $pid = trim(shell_exec($cmd));
        file_put_contents($pidFile, $pid);
        Log::info("ParseReviewPageJob: started node PID={$pid}");

        // Поллим файлы на диске — сканируем ВСЕ файлы каждый цикл
        $maxWait = 3500;
        $waited = 0;
        $totalNew = 0;
        $processedFiles = [];  // filename => mtime — чтобы перечитывать обновлённые файлы

        while ($waited < $maxWait) {
            sleep(5);
            $waited += 5;

            $alive = $pid && file_exists("/proc/{$pid}");

            // Читаем meta
            $metaFile = $cookieDir . '/batch__meta.json';
            $meta = null;
            if (file_exists($metaFile)) {
                $raw = @file_get_contents($metaFile);
                if ($raw) $meta = json_decode($raw, true);
            }

            if ($meta && !empty($meta['organization'])) {
                $task->update([
                    'organization_data' => $meta['organization'],
                    'total_expected' => $meta['total_expected'] ?? $task->total_expected,
                ]);
            }

            // Сканируем все batch__page_*.json файлы
            $pageFiles = glob($cookieDir . '/batch__page_*.json');
            $anyNew = false;

            foreach ($pageFiles as $pageFile) {
                $fname = basename($pageFile);
                $mtime = filemtime($pageFile);

                // Пропускаем если файл не изменился с прошлого чтения
                if (isset($processedFiles[$fname]) && $processedFiles[$fname] >= $mtime) {
                    continue;
                }

                clearstatcache(true, $pageFile);
                $raw = @file_get_contents($pageFile);
                if (!$raw) continue;

                $pageData = json_decode($raw, true);
                if (!$pageData || empty($pageData['reviews'])) {
                    $processedFiles[$fname] = $mtime;
                    continue;
                }

                $pageNew = 0;
                foreach ($pageData['reviews'] as $review) {
                    $reviewId = $review['id'] ?? $review['review_id']
                        ?? ('r_' . md5(($review['author'] ?? '') . ($review['text'] ?? '')));

                    $saved = Review::updateOrCreate(
                        ['organization_id' => $orgId, 'review_id' => $reviewId],
                        [
                            'author' => $review['author'] ?? 'Аноним',
                            'text' => $review['text'] ?? '',
                            'rating' => $review['rating'] ?? 0,
                            'published_at' => $review['published_at'] ?? '',
                            'review_date' => null,
                        ]
                    );

                    if ($saved->wasRecentlyCreated) {
                        $pageNew++;
                        $totalNew++;
                    }
                }

                $processedFiles[$fname] = $mtime;
                $anyNew = true;

                if ($pageNew > 0) {
                    Log::info("ParseReviewPageJob: file {$fname} → +{$pageNew} new");
                }
            }

            // Обновляем прогресс в БД
            if ($anyNew) {
                $totalInDb = Review::where('organization_id', $orgId)->count();
                $task->update([
                    'current_page' => count($processedFiles),
                    'total_parsed' => $totalInDb,
                ]);
            }

            // Скрипт сообщил что готов
            if ($meta && !empty($meta['is_complete'])) {
                Log::info("ParseReviewPageJob: script reports complete");
                break;
            }

            // Node процесс завершился
            if (!$alive) {
                sleep(2);
                // Дочитаем последние файлы
                foreach (glob($cookieDir . '/batch__page_*.json') as $pageFile) {
                    $fname = basename($pageFile);
                    $mtime = filemtime($pageFile);
                    if (isset($processedFiles[$fname]) && $processedFiles[$fname] >= $mtime) continue;

                    $raw = @file_get_contents($pageFile);
                    if (!$raw) continue;
                    $pageData = json_decode($raw, true);
                    if (!$pageData || empty($pageData['reviews'])) continue;

                    foreach ($pageData['reviews'] as $review) {
                        $reviewId = $review['id'] ?? $review['review_id']
                            ?? ('r_' . md5(($review['author'] ?? '') . ($review['text'] ?? '')));
                        Review::updateOrCreate(
                            ['organization_id' => $orgId, 'review_id' => $reviewId],
                            [
                                'author' => $review['author'] ?? 'Аноним',
                                'text' => $review['text'] ?? '',
                                'rating' => $review['rating'] ?? 0,
                                'published_at' => $review['published_at'] ?? '',
                                'review_date' => null,
                            ]
                        );
                    }
                    $processedFiles[$fname] = $mtime;
                }
                Log::info("ParseReviewPageJob: node process exited");
                break;
            }

            // Лог прогресса каждые 30 секунд
            if ($waited % 30 === 0) {
                $totalInDb = Review::where('organization_id', $orgId)->count();
                Log::info("ParseReviewPageJob: progress", [
                    'files' => count($processedFiles),
                    'total_db' => $totalInDb,
                    'waited' => $waited . 's',
                ]);
            }
        }

        // Убиваем node если ещё жив
        if ($pid && file_exists("/proc/{$pid}")) {
            posix_kill((int)$pid, 15);
            sleep(2);
            if (file_exists("/proc/{$pid}")) {
                posix_kill((int)$pid, 9);
            }
        }
        @unlink($pidFile);

        $totalParsed = Review::where('organization_id', $orgId)->count();
        $task->update([
            'status' => 'completed',
            'total_parsed' => $totalParsed,
            'total_pages' => count($processedFiles),
            'completed_at' => now(),
            'next_run_at' => null,
            'last_error' => null,
        ]);

        Log::info("ParseReviewPageJob: COMPLETED", [
            'task' => $task->id,
            'total_parsed' => $totalParsed,
            'total_new' => $totalNew,
        ]);
    }
}
