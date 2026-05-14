<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Source: .planning/phases/07-cms/07-09-PLAN.md task 1 + threat T-07-09-03.
 *
 * Validates GET /search?q=. The regex permits letters (any Unicode language),
 * numbers, spaces, hyphens, underscores, apostrophes, periods, commas,
 * ampersands, and forward-slashes — covers real-world strings like 'AC/DC',
 * "O'Brien", "Hell Let Loose" without permitting SQL operators or scripting
 * punctuation. plainto_tsquery() inside SearchService is the second
 * sanitisation layer (T-07-09-03 mitigation chain).
 *
 * authorize() returns true unconditionally — /search is a public endpoint.
 * Rate limiting is enforced at the routes/web.php layer via throttle:60,1
 * (T-07-09-01 mitigation).
 */
final class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => [
                'required',
                'string',
                'min:2',
                'max:200',
                "regex:/^[\p{L}\p{N}\s\-_'.,&\/]+$/u",
            ],
        ];
    }
}
