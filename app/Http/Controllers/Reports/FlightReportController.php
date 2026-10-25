<?php

namespace App\Http\Controllers\Reports;

use App\Domain\FlightPlans\Rules\UtcFourDigitTime;
use App\Domain\FlightPlans\Services\FlightReportQueryService;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class FlightReportController extends Controller
{
    public function __construct(
        private FlightReportQueryService $queries,
    ) {}

    public function downloadAbbreviatedReportPdf(Request $request): Response
    {
        $this->ensureReviewerAccess();

        $generatedAt = now('UTC');
        $selectedDate = (string) ($request->query('date') ?: now('UTC')->toDateString());
        $flights = $this->queries->abbreviated(Auth::user())
            ->whereDate('date_of_flight', $selectedDate)
            ->orderByRaw('case when date_of_flight is null then 1 else 0 end')
            ->orderBy('date_of_flight')
            ->orderByRaw('case when proposed_time is null then 1 else 0 end')
            ->orderBy('proposed_time')
            ->orderBy('id')
            ->get();

        $pdf = Pdf::loadView('reports.abbreviated-flight-report-pdf', [
            'flights' => $flights,
            'generatedAt' => $generatedAt,
            'selectedDate' => $selectedDate,
            'generatedBy' => $this->resolveAtcWiresign(),
            'formatTime' => static fn (?string $time): ?string => UtcFourDigitTime::formatForDisplay($time),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('abbreviated-flight-report-'.$generatedAt->format('Y-m-d-His').'.pdf');
    }

    public function downloadPostOpsLogPdf(Request $request): Response
    {
        $this->ensureReviewerAccess();

        $generatedAt = now('UTC');
        $selectedDate = (string) ($request->query('date') ?: now('UTC')->toDateString());
        $flights = $this->queries->postOperations(Auth::user())
            ->whereDate('date_of_flight', $selectedDate)
            ->orderByRaw('case when date_of_flight is null then 1 else 0 end')
            ->orderBy('date_of_flight')
            ->orderByRaw('case when proposed_time is null then 1 else 0 end')
            ->orderBy('proposed_time')
            ->orderBy('id')
            ->get();

        $pdf = Pdf::loadView('reports.post-ops-log-pdf', [
            'flights' => $flights,
            'generatedAt' => $generatedAt,
            'selectedDate' => $selectedDate,
            'generatedBy' => $this->resolveAtcWiresign(),
            'formatTime' => static fn (?string $time): ?string => UtcFourDigitTime::formatForDisplay($time),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('post-ops-log-'.$generatedAt->format('Y-m-d-His').'.pdf');
    }

    private function ensureReviewerAccess(): void
    {
        $user = Auth::user();

        abort_unless(
            $user
            && $user->is_active
            && $user->canReviewFlightPlans(),
            403
        );
    }

    private function resolveAtcWiresign(): string
    {
        $user = Auth::user();

        return (string) ($user?->wiresign ?: $user?->name ?: '');
    }
}
