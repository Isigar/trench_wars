<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAbuseReportRequest;
use App\Models\AbuseReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: .planning/phases/09-polish/09-11-PLAN.md task 2 +
 *         09-RESEARCH.md "Report-abuse flow" +
 *         CLAUDE.md §6 (activity_log writes are append-only via activity()
 *         ->causedBy()->performedOn()->log() — D-09-03-A applies: the AbuseReport
 *         model does NOT use LogsActivity, so we hand-roll the audit row here).
 *
 * Public POST /reports — abuse-report submission surface. Two endpoints:
 *
 *   GET  /reports/create — Inertia 'Report/Create' page (form). Accepts
 *     ?target_type=&target_id=&target_name= query string for pre-fill so the
 *     <ReportButton/> inline component can deep-link with target context.
 *
 *   POST /reports — creates abuse_reports row + writes activity_log row.
 *     Target existence is verified by morph-class lookup; missing target =>
 *     404 (T-09-02-04 mitigation — no silent reports against nonexistent IDs).
 *
 * Route wiring: routes/web.php registers both endpoints under
 * `['auth', 'throttle:report-abuse']` (5/hour per user, T-09-11-03 mitigation).
 * The throttle middleware short-circuits before this controller runs.
 *
 * Threat refs:
 *   - T-09-11-03 (Tampering / report storm) — throttle:report-abuse at route layer.
 *   - T-09-11-05 (Information Disclosure — body contains victim PII) — accept;
 *     report bodies are visible only to moderators via AbuseReportResource.
 *   - T-09-11-06 (Repudiation — moderator denies action) — every transition
 *     writes an activity_log row (this controller writes the submission row).
 */
final class ReportsController extends Controller
{
    public function create(Request $request): Response
    {
        $targetType = (string) $request->query('target_type', '');
        $targetId = (string) $request->query('target_id', '');
        $targetName = (string) $request->query('target_name', '');

        return Inertia::render('Report/Create', [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_name' => $targetName,
            'reason_codes' => StoreAbuseReportRequest::ALLOWED_REASON_CODES,
        ]);
    }

    public function store(StoreAbuseReportRequest $request): RedirectResponse
    {
        /** @var array{target_type: string, target_id: string, reason_code: string, body?: string|null} $validated */
        $validated = $request->validated();

        $targetType = $validated['target_type'];
        $targetId = $validated['target_id'];

        // Verify the morph-target row exists; abort 404 if not (T-09-02-04).
        // We use the morph class FQN (validated against the allow-list in
        // StoreAbuseReportRequest::ALLOWED_TARGET_TYPES) as the model class.
        /** @var class-string<Model> $modelClass */
        $modelClass = $targetType;
        /** @var Model|null $target */
        $target = $modelClass::query()->find($targetId);

        abort_if($target === null, 404, 'Report target not found.');

        $reporter = $request->user();
        abort_if($reporter === null, 401, 'Reporter not authenticated.');

        DB::transaction(function () use ($validated, $targetType, $targetId, $target, $reporter): void {
            AbuseReport::query()->create([
                'reporter_user_id' => $reporter->getAuthIdentifier(),
                'target_type' => $targetType,
                'target_id' => $targetId,
                'reason_code' => $validated['reason_code'],
                'body' => $validated['body'] ?? null,
                'status' => 'pending',
            ]);

            // D-09-03-A: AbuseReport does NOT use LogsActivity. The audit row
            // is hand-rolled here so the activity description is human-readable
            // (causer=reporter, subject=target, log='abuse.reported').
            $bodyPreview = isset($validated['body']) ? Str::limit($validated['body'], 200) : null;

            activity()
                ->causedBy($reporter)
                ->performedOn($target)
                ->withProperties([
                    'reason_code' => $validated['reason_code'],
                    'body_preview' => $bodyPreview,
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                ])
                ->log('abuse.reported');
        });

        return redirect()
            ->route('home')
            ->with('flash', ['type' => 'success', 'key' => 'reports.flash.submitted']);
    }
}
