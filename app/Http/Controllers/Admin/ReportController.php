<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatFeedback;
use App\Models\Report;
use App\Services\Moderation\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', 'open'));
        $perPage = in_array((int) $request->query('per_page', 15), [10, 15, 25, 50], true)
            ? (int) $request->query('per_page', 15)
            : 15;

        $query = Report::with(['reporter', 'reportedUser', 'conversation.messages'])->latest();
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $reports = $query->paginate($perPage, ['*'], 'page')->withQueryString();

        $feedbackItems = ChatFeedback::with(['reporter', 'targetUser'])
            ->latest()
            ->paginate(10, ['*'], 'feedback_page')
            ->withQueryString();

        $feedbackStats = [
            'thumbs_up' => ChatFeedback::where('feedback_type', 'thumbs_up')->count(),
            'thumbs_down' => ChatFeedback::where('feedback_type', 'thumbs_down')->count(),
            'spam' => ChatFeedback::where('feedback_type', 'spam')->count(),
            'vulgar' => ChatFeedback::where('feedback_type', 'vulgar')->count(),
            'underage' => ChatFeedback::where('feedback_type', 'underage')->count(),
            'other_report' => ChatFeedback::where('feedback_type', 'other_report')->count(),
        ];

        return view('admin.reports.index', compact('reports', 'feedbackItems', 'status', 'perPage', 'feedbackStats'));
    }

    public function resolve(Request $request, Report $report): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:dismiss,warn,mute,suspend,ban'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ]);

        $adminId = (int) Auth::id();

        if ($validated['action'] === 'dismiss') {
            $this->reportService->dismissReport($report, $validated['reason'], $adminId);
            return back()->with('status', "Report #{$report->id} dismissed.");
        }

        $targetUser = $report->reportedUser;
        if (!$targetUser) {
            return back()->withErrors(['action' => 'Reported user record not found.']);
        }

        $this->reportService->applyModerationAction(
            targetUser: $targetUser,
            action: $validated['action'],
            reason: $validated['reason'],
            durationMinutes: isset($validated['duration_minutes']) ? (int) $validated['duration_minutes'] : null,
            actorAdminId: $adminId,
            report: $report
        );

        return back()->with('status', "Report #{$report->id} resolved with action [{$validated['action']}].");
    }
}
