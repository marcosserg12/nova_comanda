<?php

namespace App\Http\Controllers;

use App\Models\CustomerName;
use Illuminate\Http\JsonResponse;

/** Names for the counter's "Nome do cliente" suggestions; the browser filters them while typing. */
final class CustomerNameSuggestionsController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'names' => CustomerName::suggestionList(),
            'surnames' => CustomerName::commonSurnames(),
        ]);
    }
}
