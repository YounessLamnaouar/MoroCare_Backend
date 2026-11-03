<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ListFilters
{
    public static function validate(Request $r, array $extra = []): array
    {
        $data = $r->validate(array_merge(['q' => 'nullable|string|max:100', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d', 'page' => 'nullable|integer|min:1'], $extra));
        if ($r->filled('from') && $r->filled('to') && $r->input('from') > $r->input('to')) {
            throw ValidationException::withMessages(['to' => 'End date must be on or after start date.']);
        }

        return $data;
    }

    public static function dates($query, Request $r, string $column = 'created_at')
    {
        return $query->when($r->filled('from'), fn ($q) => $q->whereDate($column, '>=', $r->input('from')))->when($r->filled('to'), fn ($q) => $q->whereDate($column, '<=', $r->input('to')));
    }
}
