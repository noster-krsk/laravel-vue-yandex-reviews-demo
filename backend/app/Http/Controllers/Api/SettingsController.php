<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\ParserTask;
use App\Services\ReviewParserService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $settings = $request->user()
            ->settings()
            ->pluck('value', 'key');

        return response()->json($settings);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $newUrl = $request->input('yandex_url');

        $cancelledTasks = [];
        $newParsingStarted = false;

        if ($newUrl) {
            $newOrgId = ParserTask::extractOrgId($newUrl);

            if ($newOrgId) {
                // Получить текущий URL из настроек
                $oldUrl = Setting::where('user_id', $user->id)
                    ->where('key', 'yandex_url')
                    ->value('value');
                $oldOrgId = $oldUrl ? ParserTask::extractOrgId($oldUrl) : null;

                // Если организация сменилась — остановить старый парсинг
                if ($oldOrgId && $oldOrgId !== $newOrgId) {
                    $cancelledTasks = $this->cancelActiveTasks($oldOrgId, $newOrgId);
                }
            }
        }

        // Сохраняем каждую настройку
        foreach ($request->all() as $key => $value) {
            Setting::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'key' => $key
                ],
                [
                    'value' => $value
                ]
            );
        }

        // Если были отменены задачи — запустить парсинг новой организации
        if (!empty($cancelledTasks) && $newUrl) {
            try {
                $parserService = app(ReviewParserService::class);
                $parserService->startParsing($newUrl, force: false);
                $newParsingStarted = true;

                Log::info('Started new parsing after URL change', [
                    'user_id' => $user->id,
                    'new_url' => $newUrl,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to start new parsing after URL change', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $settings = $user->settings()->pluck('value', 'key');

        return response()->json([
            'message' => !empty($cancelledTasks)
                ? 'Настройки сохранены. Старый парсинг остановлен, новый запущен.'
                : 'Настройки сохранены.',
            'settings' => $settings,
            'cancelled_tasks' => $cancelledTasks,
            'new_parsing_started' => $newParsingStarted,
        ]);
    }

    /**
     * Отменить все активные задачи парсинга для старой организации
     * и убить запущенные Node.js процессы.
     */
    private function cancelActiveTasks(string $oldOrgId, string $newOrgId): array
    {
        $activeTasks = ParserTask::where('organization_id', $oldOrgId)
            ->whereIn('status', ['pending', 'running', 'paused'])
            ->get();

        if ($activeTasks->isEmpty()) {
            return [];
        }

        // Убить Node.js процессы парсера для старой организации
        $this->killParserProcesses($oldOrgId);

        $cancelled = [];

        foreach ($activeTasks as $task) {
            $oldStatus = $task->status;

            $task->update([
                'status' => 'cancelled',
                'last_error' => 'Отменено: пользователь сменил URL организации',
            ]);

            $cancelled[] = [
                'id' => $task->id,
                'organization_id' => $task->organization_id,
                'was_status' => $oldStatus,
            ];

            Log::info('Cancelled parsing task due to URL change', [
                'task_id' => $task->id,
                'old_org_id' => $oldOrgId,
                'new_org_id' => $newOrgId,
                'was_status' => $oldStatus,
            ]);
        }

        // Очистить очередь от заданий парсера
        // (новые задания для новой организации ещё не были созданы)
        DB::table('jobs')->where('queue', 'parser')->delete();

        return $cancelled;
    }

    /**
     * Убить Node.js процессы парсера для указанной организации.
     * Использует pkill для поиска и завершения процессов по org_id
     * в аргументах командной строки.
     */
    private function killParserProcesses(string $orgId): void
    {
        if (!ctype_digit($orgId)) {
            return;
        }

        try {
            // Убить node-процесс parse-yandex.js для этой организации
            exec("pkill -9 -f 'parse-yandex.*{$orgId}' 2>/dev/null");

            // Убить Chrome/Chromium процессы, привязанные к cookie-директории
            exec("pkill -9 -f 'parser_cookies/{$orgId}' 2>/dev/null");

            // Небольшая пауза для завершения процессов
            usleep(500000);

            Log::info('Killed parser processes for organization', ['org_id' => $orgId]);
        } catch (\Exception $e) {
            Log::error('Failed to kill parser processes', [
                'org_id' => $orgId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
