<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiError
{
    public static function make(string $mensaje, string $codigo, int $status): JsonResponse
    {
        return response()->json(['error' => $mensaje, 'codigo' => $codigo], $status);
    }
}