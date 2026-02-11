<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'reviews' => [],
            'statistics' => [
                'total' => 0,
                'average_rating' => 0,
                'positive' => 0,
                'negative' => 0
            ]
        ]);
    }
}
