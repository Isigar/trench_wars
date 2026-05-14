<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Source: .planning/phases/07-cms/07-09-PLAN.md task 1 + threat T-07-09-04.
 *
 * Validates GET /events/feed.json query string. Both `start` and `end` are
 * required date strings; `end` must be after `start` AND no more than 90 days
 * after `start` (the 90-day cap is the T-07-09-04 mitigation — prevents a
 * malicious client from requesting start=1970-01-01&end=2999-12-31 to dump
 * every event in the table).
 *
 * authorize() returns true unconditionally — /events/feed.json is a public
 * endpoint (no auth gate at the FormRequest layer). Rate limiting is enforced
 * at the routes/web.php layer via throttle:60,1.
 */
final class EventsFeedRequest extends FormRequest
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
        // The before_or_equal rule against an absolute future date would force
        // a moving target; instead we cap the range to 90 days from start. The
        // rule expression `before_or_equal:` accepts a date-string OR a field
        // reference but NOT a relative offset of another field, so we compute
        // the upper bound via a custom rule closure inline (see prepareForValidation).
        return [
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start', 'before_or_equal:' . $this->endUpperBound()],
        ];
    }

    /**
     * Compute the 90-day upper bound for `end` based on the inbound `start`.
     * If `start` is missing or malformed, fall back to a no-op upper bound
     * (the `required|date` rule on `start` will reject the request first).
     */
    private function endUpperBound(): string
    {
        $start = $this->input('start');
        if (! is_string($start) || $start === '') {
            // Permissive fallback — the `required` rule on start fires first
            // and the request never makes it past validation.
            return now()->addYears(100)->toDateString();
        }

        try {
            return Carbon::parse($start)->addDays(90)->toDateString();
        } catch (\Exception) {
            // Malformed start — fall back to permissive; `date` rule on start fires first.
            return now()->addYears(100)->toDateString();
        }
    }
}
