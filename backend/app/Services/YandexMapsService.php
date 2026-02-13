<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YandexMapsService
{
    private string $apiKey;
    private string $baseUrl = 'https://search-maps.yandex.ru/v1/';

    public function __construct()
    {
        $this->apiKey = config('services.yandex.maps_api_key');
    }

    /**
     * Получить информацию об организации по URL
     */
    public function getOrganizationByUrl(string $url): ?array
    {
        // Извлекаем ID организации из URL
        // Пример: https://yandex.ru/maps/org/turetskiy_dener_1/202738933341/
        preg_match('/\/org\/[^\/]+\/(\d+)/', $url, $matches);
        
        if (!isset($matches[1])) {
            return null;
        }

        $orgId = $matches[1];
        
        return $this->getOrganizationById($orgId);
    }

    /**
     * Получить информацию об организации по ID
     */
    public function getOrganizationById(string $orgId): ?array
    {
        try {
            $response = Http::get($this->baseUrl, [
                'apikey' => $this->apiKey,
                'oid' => $orgId,
                'lang' => 'ru_RU'
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Yandex Maps API error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Yandex Maps API exception', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Получить отзывы об организации
     */
    public function getReviews(string $orgId, int $limit = 100): array
    {
        // Яндекс API возвращает отзывы вместе с информацией об организации
        $data = $this->getOrganizationById($orgId);
        
        if (!$data || !isset($data['features'][0]['properties']['CompanyMetaData'])) {
            return [];
        }

        $company = $data['features'][0]['properties']['CompanyMetaData'];
        
        // Отзывы находятся в разделе reviews
        return $company['reviews'] ?? [];
    }
}