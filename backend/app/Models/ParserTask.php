<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParserTask extends Model
{
    protected $fillable = [
        'organization_id',
        'yandex_url',
        'status',
        'total_expected',
        'total_parsed',
        'current_page',
        'total_pages',
        'current_phase',
        'organization_data',
        'last_error',
        'retry_count',
        'next_run_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'organization_data' => 'array',
            'total_expected' => 'integer',
            'total_parsed' => 'integer',
            'current_page' => 'integer',
            'total_pages' => 'integer',
            'retry_count' => 'integer',
            'next_run_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public static function extractOrgId(string $url): ?string
    {
        if (preg_match('/\/org\/[^\/]+\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
