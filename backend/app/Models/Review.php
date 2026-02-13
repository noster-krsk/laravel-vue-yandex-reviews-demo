<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'organization_id',
        'review_id',
        'author',
        'text',
        'rating',
        'published_at',
        'review_date',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'review_date' => 'datetime',
        ];
    }

    public function scopeForOrganization($query, string $orgId)
    {
        return $query->where('organization_id', $orgId);
    }
}
