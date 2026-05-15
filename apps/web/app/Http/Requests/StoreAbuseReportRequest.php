<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Article;
use App\Models\Clan;
use App\Models\GameMatch;
use App\Models\Player;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Source: .planning/phases/09-polish/09-11-PLAN.md task 2 +
 *         09-RESEARCH.md "Report-abuse flow".
 *
 * Validates POST /reports payload. Backed by abuse_reports table (plan 09-02
 * migration). reason_code is application-validated (not a DB CHECK constraint)
 * so the enum can extend without schema churn — D-09-02 design decision.
 *
 * target_type is locked to the v1 reportable surface:
 *   - Clan       (clan-level harassment, brigading)
 *   - Player     (per-user harassment, cheating accusations)
 *   - Article    (CMS — spam, inappropriate content)
 *   - GameMatch  (D-04-03-A LOCKED: `GameMatch` because `Match` is a PHP 8
 *                 reserved keyword; the morph_class is the FQN)
 *
 * The target_id is a string (D-09-02-E — varchar in DB), so we accept any
 * non-empty token ≤ 64 chars. Existence of the target is enforced inside
 * ReportsController::store via the morph class lookup (404 if missing).
 *
 * body is optional (nullable) up to 2000 chars — enough for one moderate-
 * length paragraph + URL context.
 *
 * Pitfall 8 mitigation: every rule emits a specific validation message so
 * the Inertia error bag renders inline next to the field.
 *
 * @phpstan-type ValidatedPayload array{target_type: string, target_id: string, reason_code: string, body?: string|null}
 */
final class StoreAbuseReportRequest extends FormRequest
{
    /** @var list<class-string> */
    public const ALLOWED_TARGET_TYPES = [
        Clan::class,
        Player::class,
        Article::class,
        GameMatch::class,
    ];

    /** @var list<string> */
    public const ALLOWED_REASON_CODES = [
        'harassment',
        'spam',
        'cheating',
        'inappropriate_content',
        'other',
    ];

    public function authorize(): bool
    {
        // Route is already gated by `auth` middleware; this is defence in depth.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'target_type' => ['required', 'string', 'in:' . implode(',', self::ALLOWED_TARGET_TYPES)],
            'target_id' => ['required', 'string', 'max:64'],
            'reason_code' => ['required', 'string', 'in:' . implode(',', self::ALLOWED_REASON_CODES)],
            'body' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
