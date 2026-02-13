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
        if (!$task || in_array($task->status, ['completed', 'failed', 'cancelled'])) {
            return;
        }

        $task->update(['status' => 'running', 'started_at' => now()]);

        $orgId = $task->organization_id;
        $scriptPath = base_path('scripts/parse-yandex.js');
        $cookieDir = storage_path('app/parser_cookies/' . $orgId);
        $pidFile = $cookieDir . '/node.pid';
        $nodeLog = $cookieDir . '/node.log';

        if (!is_dir($cookieDir)) {
            mkdir($cookieDir, 0755, true);
        }

        // Очистим старые batch файлы
        foreach (glob($cookieDir . '/batch__page_*.json') as $f) {
            @unlink($f);
        }
        @unlink($cookieDir . '/batch__meta.json');

        Log::info("ParseReviewPageJob: starting", ['task' => $task->id, 'org' => $orgId]);

        // Запускаем node в фоне — ВАЖНО: >/dev/null 2>&1 чтобы shell_exec не висел
        $cmd = sprintf(
            'HOME=/tmp TMPDIR=/tmp nohup node %s %s %s %s > %s 2>&1 & echo $!',
            escapeshellarg($scriptPath),
            escapeshellarg($task->yandex_url),
            escapeshellarg($cookieDir),
            escapeshellarg('batch_'),
            escapeshellarg($nodeLog)
        );

        $pid = trim(shell_exec($cmd));
        file_put_contents($pidFile, $pid);
        Log::info("ParseReviewPageJob: node started PID={$pid}");

        // Поллим файлы на диске
        $maxWait = 3500;
        $waited = 0;
        $totalNew = 0;
        $processedFiles = [];

        while ($waited < $maxWait) {
            sleep(5);
            $waited += 5;

            clearstatcache();
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
                $mtime = @filemtime($pageFile);

                if (isset($processedFiles[$fname]) && $processedFiles[$fname] >= $mtime) {
                    continue;
                }

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
                    Log::info("ParseReviewPageJob: +{$pageNew} new from {$fname}");
                }
            }

            if ($anyNew) {
                $totalInDb = Review::where('organization_id', $orgId)->count();
                $task->update([
                    'current_page' => count($processedFiles),
                    'total_parsed' => $totalInDb,
                ]);
                Log::info("ParseReviewPageJob: DB total={$totalInDb}");
            }

            // Скрипт завершён
            if ($meta && !empty($meta['is_complete'])) {
                Log::info("ParseReviewPageJob: script complete");
                break;
            }

            // Node процесс завершился
            if (!$alive) {
                sleep(2);
                clearstatcache();
                // Дочитаем последние файлы
                foreach (glob($cookieDir . '/batch__page_*.json') as $pageFile) {
                    $fname = basename($pageFile);
                    $mtime = @filemtime($pageFile);
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
                Log::info("ParseReviewPageJob: node exited");
                break;
            }

            if ($waited % 30 === 0) {
                $totalInDb = Review::where('organization_id', $orgId)->count();
                Log::info("ParseReviewPageJob: progress", [
                    'files' => count($processedFiles), 'db' => $totalInDb, 'waited' => $waited,
                ]);
            }

            // Проверяем, не отменена ли задача (пользователь сменил URL)
            if ($waited % 15 === 0) {
                $task->refresh();
                if ($task->status === 'cancelled') {
                    Log::info("ParseReviewPageJob: task cancelled during execution", ['task' => $task->id]);
                    break;
                }
            }
        }

        // Убиваем node если ещё жив
        if ($pid && file_exists("/proc/{$pid}")) {
            posix_kill((int)$pid, 15);
            sleep(2);
            if (file_exists("/proc/{$pid}")) posix_kill((int)$pid, 9);
        }
        @unlink($pidFile);

        // Если задача была отменена (пользователь сменил URL) — не перезаписываем статус
        $task->refresh();
        if ($task->status === 'cancelled') {
            Log::info("ParseReviewPageJob: task was cancelled, skipping completion", ['task' => $task->id]);
            return;
        }

        $totalParsed = Review::where('organization_id', $orgId)->count();
        $task->update([
            'status' => 'completed',
            'total_parsed' => $totalParsed,
            'total_pages' => count($processedFiles),
            'completed_at' => now(),
        ]);

        Log::info("ParseReviewPageJob: DONE total={$totalParsed} new={$totalNew}");
    }
}
