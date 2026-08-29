<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CAAP Form ATS 2019-1 Flight Plan</title>
    <style>
        body.preview {
            background: #ccc;
        }

        .preview-wrapper {
            width: 794px;
            margin: auto;
            background: white;
            padding: 10px;
        }

        @page {
            margin-top: 4mm;
            margin-bottom: 0;
            margin-left: 6mm;
            margin-right: 6mm;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #000;
            line-height: 1.0;
            -webkit-text-size-adjust: 100%;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 8px;
            font-size: 10px;
        }

        .header-small {
            font-size: 10px;
            margin-bottom: 2px;
        }

        .form-title {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 2px;
            padding: 4px;
        }

        /* Main form table */
        .form-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
        }

        .form-table td, .form-table th {
            padding: 2px 3px;
            vertical-align: top;
            text-align: left;
            height: auto;
        }

        .addressee-box {
            width: 100%;
            height: 60px;
        }

        /* Field boxes for character entry */
        .char-box {
            display: inline-block;
            width: 15px;
            height: 15px;
            border: 1px solid #666;
            border-radius: 2px;
            margin: 1px;
            font-weight: bold;
            font-size: 11px;
            line-height: 15px;
            text-align: center;
            vertical-align: middle;
            color: #000;
        }

        .char-field {
            display: inline-block;
            height: 15px;
            border: 1px solid #666;
            border-radius: 0;
            line-height: 15px;
            white-space: nowrap;
            vertical-align: middle;
        }

        .char-field .char-box {
            border: 0;
            border-radius: 0;
            margin: 0;
            line-height: 15px;
        }

        .char-divider {
            display: inline-block;
            width: 0;
            height: 8px;
            border-left: 1px solid #666;
            vertical-align: bottom;
        }


        /* Field boxes for string entry */
        .string-box {
            display: inline-block;
            height: 15px;
            border: 1px solid #666;
            line-height: 15px;
            text-align: center;
            vertical-align: middle;
            margin: 1px;
            font-weight: bold;
            font-size: 11px;
            color: #000;
            border-radius: 2px;
            padding: 0 2px;
        }

        .string-box-left {
            text-align: left;
        }

        .multi-line-box {
            display: block;
            width: 100%;
            min-height: 45px;
            border: 1px solid #666;
            box-sizing: border-box;
            padding: 2px 4px;
            font-size: 11px;
            font-weight: bold;
            line-height: 15px;
            text-align: left;
            white-space: normal;
            word-wrap: break-word;
        }

        .checkbox {
            width: 15px;
            height: 15px;
            border: 1px solid #666;
            display: inline-block;
            text-align: center;
            line-height: 14px;
            font-weight: bold;
            font-size: 11px;
            vertical-align: middle;
        }

        /* Label for fields */
        .field-label {
            font-size: 9px;
            font-weight: bold;
            display: block;
            margin-bottom: 2px;
        }

        .section-label {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .data-field {
            font-size: 11px;
            padding: 2px;
            min-height: 16px;
        }

        /* Certification box */
        .certification-box {
            border: 1px solid #000;
            padding: 4px;
            margin-top: 2px;
            font-size: 8px;
            line-height: 1.3;
        }

        .certification-title {
            text-align: center;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .echo-preview-dashboard-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 10px 18px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            color: #374151;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.25;
            text-decoration: none;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease;
        }

        .echo-preview-dashboard-button:hover {
            background: #f3f4f6;
            border-color: #9ca3af;
            color: #111827;
        }

        .echo-preview-dashboard-button:focus-visible {
            outline: 2px solid #60a5fa;
            outline-offset: 2px;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, .25);
        }

        .echo-review-page {
            width: min(794px, calc(100% - 32px));
            margin: 0 auto 18px;
            color: #172033;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .echo-review-heading {
            padding: 18px 2px 12px;
        }

        .echo-review-eyebrow {
            margin: 0 0 4px;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .echo-review-title {
            margin: 0;
            font-size: 23px;
            line-height: 1.2;
            font-weight: 700;
        }

        .echo-review-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 14px;
            margin-top: 7px;
            color: #64748b;
            font-size: 13px;
        }

        .echo-review-status {
            color: #1d4ed8;
            font-weight: 700;
        }

        .echo-preview-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 2px 12px;
        }

        .echo-preview-header .echo-review-eyebrow { margin-bottom: 5px; }
        .echo-preview-title { margin: 0; color: #172033; font-size: 22px; line-height: 1.2; font-weight: 700; }
        .echo-preview-aircraft { margin: 6px 0 0; color: #334155; font-size: 15px; font-weight: 700; letter-spacing: .03em; }
        .echo-preview-status-badge { display: inline-block; padding: 6px 10px; border: 1px solid transparent; border-radius: 999px; font-size: 12px; font-weight: 750; line-height: 1.2; white-space: nowrap; }
        .echo-preview-status-pending { border-color: #fcd34d; background: #fffbeb; color: #a16207; }
        .echo-preview-status-accepted, .echo-preview-status-completed { border-color: #86efac; background: #f0fdf4; color: #15803d; }
        .echo-preview-status-active { border-color: #93c5fd; background: #eff6ff; color: #1d4ed8; }
        .echo-preview-status-rejected { border-color: #fca5a5; background: #fef2f2; color: #b91c1c; }
        .echo-preview-status-default { border-color: #cbd5e1; background: #f8fafc; color: #475569; }
        .echo-preview-delay-banner { width: min(794px, calc(100% - 32px)); margin: 0 auto 12px; padding: 10px 12px; border: 1px solid #fcd34d; border-radius: 8px; background: #fffbeb; color: #92400e; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 13px; font-weight: 650; box-sizing: border-box; }
        .echo-preview-delay-history { width: min(794px, calc(100% - 32px)); margin: 0 auto 14px; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; color: #475569; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 12px; box-sizing: border-box; }
        .echo-preview-delay-history-title { margin: 0 0 6px; color: #334155; font-weight: 700; }
        .echo-preview-delay-history-list { margin: 0; padding-left: 18px; }
        .echo-preview-delay-history-list li { margin: 3px 0; }

        .echo-review-toolbar {
            position: fixed;
            z-index: 20;
            right: 0;
            bottom: 16px;
            left: 0;
            width: min(794px, calc(100% - 32px));
            margin: auto;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: rgba(255, 255, 255, .97);
            box-shadow: 0 8px 28px rgba(15, 23, 42, .16);
            box-sizing: border-box;
        }

        .echo-review-toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }

        .echo-review-button {
            min-height: 44px;
            padding: 10px 16px;
            border: 1px solid transparent;
            border-radius: 8px;
            font: inherit;
            font-size: 14px;
            font-weight: 650;
            line-height: 1.2;
            cursor: pointer;
            text-decoration: none;
            transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease;
        }

        .echo-review-button:focus-visible, .echo-review-modal-close:focus-visible {
            outline: 2px solid #60a5fa;
            outline-offset: 2px;
        }

        .echo-review-button-neutral {
            border-color: #cbd5e1;
            background: #fff;
            color: #475569;
        }

        .echo-review-button-neutral:hover { background: #f8fafc; border-color: #94a3b8; }

        .echo-review-button-danger {
            border-color: #fca5a5;
            background: #fff;
            color: #b91c1c;
        }

        .echo-review-button-danger:hover { background: #fef2f2; border-color: #f87171; }

        .echo-review-button-primary {
            border-color: #2563eb;
            background: #2563eb;
            color: #fff;
        }

        .echo-review-button-primary:hover { background: #1d4ed8; }

        .echo-review-document-spacer { height: 78px; }

        .echo-review-completion {
            width: min(794px, calc(100% - 32px));
            margin: 18px auto;
            padding: 14px 16px;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            background: #f0fdf4;
            color: #166534;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 14px;
            font-weight: 650;
            box-sizing: border-box;
        }

        .echo-review-completion-close {
            margin-top: 10px;
        }

        .echo-review-modal[hidden] { display: none; }

        .echo-review-modal {
            position: fixed;
            z-index: 40;
            inset: 0;
            display: grid;
            place-items: center;
            padding: 16px;
            background: rgba(15, 23, 42, .48);
            box-sizing: border-box;
        }

        .echo-review-modal-card {
            width: min(440px, 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 20px 50px rgba(15, 23, 42, .25);
            color: #172033;
        }

        .echo-review-modal-body { padding: 22px 22px 8px; }
        .echo-review-modal-title { margin: 0 0 8px; font-size: 20px; }
        .echo-review-modal-copy { margin: 0 0 14px; color: #475569; font-size: 14px; line-height: 1.45; }
        .echo-review-wiresign { margin: 0 0 16px; color: #334155; font-size: 13px; font-weight: 600; }
        .echo-review-wiresign-chip { display: inline-block; margin-left: 4px; padding: 3px 8px; border: 1px solid #bfdbfe; border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 700; letter-spacing: .04em; line-height: 1.2; }
        .echo-review-acceptance-identity { margin: 18px 0 4px; text-align: center; }
        .echo-review-acceptance-wiresign { display: inline-block; min-width: 96px; padding: 10px 18px; border: 1px solid #bfdbfe; border-radius: 10px; background: #eff6ff; color: #1d4ed8; font-size: 30px; font-weight: 800; letter-spacing: .08em; line-height: 1.1; }
        .echo-review-acceptance-role { margin: 9px 0 18px; color: #334155; font-size: 14px; font-weight: 700; text-align: center; }
        .echo-review-acceptance-note { margin: 0 0 4px; color: #475569; font-size: 14px; line-height: 1.45; text-align: center; }
        .echo-review-modal label { display: block; margin-bottom: 6px; color: #334155; font-size: 13px; font-weight: 650; }
        .echo-review-modal textarea { width: 100%; min-height: 104px; padding: 10px 11px; border: 1px solid #cbd5e1; border-radius: 7px; color: #172033; font: inherit; font-size: 14px; resize: vertical; box-sizing: border-box; }
        .echo-review-modal textarea:focus { border-color: #60a5fa; outline: 2px solid rgba(96, 165, 250, .25); }
        .echo-review-modal-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 16px 22px 20px; }

        @media (max-width: 640px) {
            .echo-review-toolbar { position: static; margin-top: 12px; flex-direction: column; align-items: stretch; }
            .echo-review-toolbar-actions { justify-content: stretch; }
            .echo-review-toolbar-actions .echo-review-button { flex: 1 1 auto; }
            .echo-review-document-spacer { display: none; }
        }

        @media (prefers-color-scheme: dark) {
            .echo-preview-dashboard-button {
                border-color: #4b5563;
                background: #1f2937;
                color: #f9fafb;
            }

            .echo-preview-dashboard-button:hover {
                background: #374151;
                border-color: #6b7280;
                color: #fff;
            }
        }
    </style>
</head>
<body class="{{ isset($isPreview) ? 'preview' : '' }}">
    @php
        $isPdfOnly = (bool) ($isPdfOnly ?? false);
        $charBoxes = function (mixed $value): \Illuminate\Support\HtmlString {
            if ((string) $value === '') {
                return new \Illuminate\Support\HtmlString('');
            }

            $characters = str_split((string) $value, 1);
            $fieldWidth = (count($characters) * 15) + max(count($characters) - 1, 0);
            $cells = '';

            foreach ($characters as $index => $char) {
                $cells .= '<span class="char-box">'.($char === ' ' ? '&nbsp;' : e($char)).'</span>';

                if ($index < count($characters) - 1) {
                    $cells .= '<span class="char-divider"></span>';
                }
            }

            return new \Illuminate\Support\HtmlString('<span class="char-field" style="width: '.$fieldWidth.'px;">'.$cells.'</span>');
        };
    @endphp

    @if($isPdfOnly && isset($isPreview))
        <div style="width: 794px; margin: 0 auto 10px; padding: 7px 10px; border: 1px solid #92400e; background: #fffbeb; color: #92400e; font-size: 11px; font-weight: bold; text-align: center;">
            PDF ONLY - NOT FILED WITH RPUS
        </div>
    @endif

    @if(isset($isPreview))
        @php
            $previewStatus = $flight->status?->label() ?? 'Unknown';
            $previewStatusClass = match ($flight->status) {
                \App\Domain\FlightPlans\Enums\FlightPlanStatus::Pending => 'pending',
                \App\Domain\FlightPlans\Enums\FlightPlanStatus::Accepted => 'accepted',
                \App\Domain\FlightPlans\Enums\FlightPlanStatus::Active => 'active',
                \App\Domain\FlightPlans\Enums\FlightPlanStatus::Rejected => 'rejected',
                \App\Domain\FlightPlans\Enums\FlightPlanStatus::Completed => 'completed',
                default => 'default',
            };
            if ($flight->status === \App\Domain\FlightPlans\Enums\FlightPlanStatus::Pending && ($showReviewActions ?? false)) {
                $previewStatus = 'Pending ATMO Review';
            }
            $reviewCompleted = session('review_status')
                && in_array($flight->status, [
                    \App\Domain\FlightPlans\Enums\FlightPlanStatus::Accepted,
                    \App\Domain\FlightPlans\Enums\FlightPlanStatus::Rejected,
                ], true);
        @endphp

        <div class="echo-review-page echo-preview-header">
            <div>
                <p class="echo-review-eyebrow">ATMO · Echo</p>
                <h1 class="echo-preview-title">Flight Plan Preview</h1>
                <p class="echo-preview-aircraft">{{ $flight->aircraft_identification }}</p>
            </div>
            <span class="echo-preview-status-badge echo-preview-status-{{ $previewStatusClass }}">{{ $previewStatus }}</span>
        </div>

        @if($flight->revised_eobt !== null)
            <div class="echo-preview-delay-banner" role="status">
                Flight plan delayed to {{ \App\Domain\FlightPlans\Rules\UtcFourDigitTime::formatForDisplay($flight->revised_eobt) }}. Original filed EOBT: {{ \App\Domain\FlightPlans\Rules\UtcFourDigitTime::formatForDisplay($flight->proposed_time) }}.
            </div>
        @endif

        @php
            $delayEvents = $flight->relationLoaded('events')
                ? $flight->events->where('event_type', \App\Models\FlightPlanEvent::TYPE_DELAYED)
                : $flight->events()->where('event_type', \App\Models\FlightPlanEvent::TYPE_DELAYED)->latest('id')->get()->reverse();
        @endphp
        @if($delayEvents->isNotEmpty())
            <div class="echo-preview-delay-history">
                <p class="echo-preview-delay-history-title">Delay history</p>
                <ol class="echo-preview-delay-history-list">
                    @foreach($delayEvents as $delayEvent)
                        <li>
                            Delayed — {{ \App\Domain\FlightPlans\Rules\UtcFourDigitTime::formatForDisplay($delayEvent->old_values['eobt'] ?? null) ?? 'N/A' }} → {{ \App\Domain\FlightPlans\Rules\UtcFourDigitTime::formatForDisplay($delayEvent->new_values['eobt'] ?? null) ?? 'N/A' }}
                            @if($delayEvent->created_at) · {{ $delayEvent->created_at->format('d M Y H:i') }}@endif
                            @if($delayEvent->actor) · {{ $delayEvent->actor->name }}@endif
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if($reviewCompleted)
            <div class="echo-review-completion" role="status" data-review-completion>
                <div>{{ $flight->status === \App\Domain\FlightPlans\Enums\FlightPlanStatus::Accepted ? 'Flight plan accepted successfully.' : 'Flight plan rejected successfully.' }}</div>
                <button class="echo-review-button echo-review-button-neutral echo-review-completion-close" type="button" data-close-review-tab>Close This Tab</button>
            </div>
        @endif

        @if(($picDeclineDetails ?? false))
            <div style="width: 794px; margin: 0 auto 12px; padding: 12px 14px; border: 2px solid #b45309; background: #fffbeb; color: #78350f; font-size: 12px;">
                <strong style="display:block; font-size: 14px; margin-bottom: 6px;">PIC Authorization Declined</strong>
                <div>This version was declined by the Pilot-in-Command.</div>
                <div style="margin-top: 6px;"><strong>Reason:</strong> {{ $flight->pic_authorization_decline_reason }}</div>
                <div><strong>Declined by:</strong> {{ $flight->picAuthorizationDeclinedBy?->name ?? 'PIC' }}</div>
                <div><strong>Date/Time:</strong> {{ $flight->pic_authorization_declined_at?->format('M j, Y H:i:s') }}</div>
            </div>
        @endif
        @if(!(($showPreviewActions ?? true)))
            <div style="width: 794px; margin: 0 auto 10px;">
                @if(session('review_status'))
                    <div
                        id="review-status-banner"
                        style="padding: 10px 12px; border: 1px solid #166534; background: #f0fdf4; color: #166534; font-weight: bold; font-size: 12px;"
                    >
                        {{ session('review_status') }}
                    </div>
                @endif

                @if(($flight->expiration_reason ?? null) && ! session('review_status'))
                    <div
                        id="review-status-banner"
                        style="flex: 1; padding: 10px 12px; border: 1px solid #b45309; background: #fffbeb; color: #b45309; font-weight: bold; font-size: 12px;"
                    >
                        {{ $flight->expiration_reason }}
                    </div>
                @endif
            </div>
        @endif

        @if(($flight->status ?? null) === \App\Domain\FlightPlans\Enums\FlightPlanStatus::Rejected)
            <div style="width: 794px; margin: 0 auto 12px;">
                <table style="width: 100%; border-collapse: collapse; border: 2px solid #000; background: #fff;">
                    <tr>
                        <th style="width: 22%; border: 1px solid #000; padding: 6px 8px; text-align: center; font-size: 10px;">Rejected by</th>
                        <th style="width: 78%; border: 1px solid #000; padding: 6px 8px; text-align: center; font-size: 10px;">REASON</th>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #000; padding: 8px; text-align: center; font-size: 11px; font-weight: bold;">
                            {{ $flight->rejected_by_wiresign ?? '' }}
                        </td>
                        <td style="border: 1px solid #000; padding: 8px; text-align: center; font-size: 11px;">
                            {{ $flight->rejection_reason ?? '' }}
                        </td>
                    </tr>
                </table>
            </div>
        @endif

    <div class="preview-wrapper">
    @endif

    <!-- Header -->
    <div class="header">
        <div class="header-small" style="text-align: left; font-size: 9px;">CAAP Form ATS 2019-1</div>
        <div class="header-small">Republic of the Philippines</div>
        <div class="header-small" style="font-weight: bold;">CIVIL AVIATION AUTHORITY OF THE PHILIPPINES</div>
        <div class="header-small">Old MIA Rd. Pasay City, Metro Manila 1300</div>
        <div class="form-title">FLIGHT PLAN</div>
    </div>

    <!-- Main Form -->
    <table class="form-table">
        <!-- Priority and Addressee -->
        <tr>
            <td style="width: 15%; text-align: center; border: 0">
                <span class="section-label">PRIORITY</span><br>
                <span class="data-field">&lt;&lt; = FF</span>
            </td>
            <td style="width: 85%; border: 0">
                <span class="section-label">ADDRESSEE(S)</span><br>
                <div class="addressee-box">
                    <table style="width: 100%; height: 100%; border-collapse: collapse; border: 1px solid #000;">
                        <tr>
                            <td style="border: 1px solid #000;">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #000;">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #000;">&nbsp;</td>
                        </tr>
                    </table>
                </div>
                <!--- <div style="text-align: right; font-size: 11px;">&lt;&lt; =</div> -->
            </td>
        </tr>

        <!-- Date of Filing and Originator -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse; border: 0;">
                    <tr>
                        <td style="width: 15%;">
                            <span class="field-label">DATE OF FILING</span>
                            <div style="text-align: left; padding: 4px;">
                                @php
                                    $dateFormatted = str_replace('-', '/', (string) ($flight->date_of_filing ?? ''));
                                @endphp
                                <span class="string-box">{{ substr($dateFormatted, 0, 10) }}</span>
                            </div>
                        </td>
                        <td style="width: 85%; padding-left: 4px;">
                            <span class="field-label">ORIGINATOR</span>
                            <div style="text-align: left; padding: 4px;">
                                @php
                                    $originator = str_pad(substr($flight->originator ?? '', 0, 8), 8, ' ');
                                @endphp
                                {!! $charBoxes($originator) !!}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Specific Identification -->
        <tr>
            <td colspan="2" style="padding-left: 2px; font-weight: normal; font-size: 7px;">
                <span>SPECIFIC IDENTIFICATION OF ADDRESSEE(S) AND/OR ORIGINATOR</span>
            </td>
        </tr>

        <!-- Blank row -->
        <tr>
            <td colspan="2" style="padding: 2px; border: 1px solid #000; font-weight: normal; font-size: 7px;">
                &nbsp;
            </td>
        </tr>

        <!-- Message Type, Aircraft ID, Flight Rules -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 25%;">
                            <span class="field-label">3. MESSAGE TYPE</span>
                            <div style="text-align: center; padding: 4px;">
                                &lt;&lt;= FPL -
                            </div>
                        </td>
                        <td style="width: 40%; padding: 2px;">
                            <span class="field-label">7. AIRCRAFT IDENTIFICATION</span>
                            <div style="text-align: left; padding: 4px; margin-left: 10px;">
                                {!! $charBoxes($flight->aircraft_identification ?? '') !!}
                            </div>
                        </td>
                        <td style="width: 17.5%; ">
                            <span class="field-label">8. FLIGHT RULES</span>
                            <div style="text-align: left; padding: 4px; margin-left: 30px;">
                                {!! $charBoxes(str_pad(substr($flight->flight_rules ?? '', 0, 1), 1, ' ')) !!}
                            </div>
                        </td>
                        <td style="width: 17.5%; ">
                            <span class="field-label">TYPE OF FLIGHT</span>
                            <div style="text-align: left; padding: 4px; margin-left: 30px;">
                                {!! $charBoxes(str_pad(substr($flight->type_of_flight ?? '', 0, 1), 1, ' ')) !!}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Number, Type of Aircraft, Wake Turbulence, Equipment -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 10%;">
                            <span class="field-label">9. NUMBER</span>
                            <div style="padding: 4px;  text-align: left; margin-left: 10px;">
                                @php
                                    $number = str_pad(substr($flight->number ?? '', 0, 1), 1, ' ');
                                @endphp
                                {!! $charBoxes($number) !!}
                            </div>
                        </td>
                        <td style="width: 20%;">
                            <span class="field-label">TYPE OF AIRCRAFT</span>
                            <div style="padding: 4px; text-align: left; margin-left: 10px;">
                                @php
                                    $aircraft = str_pad(substr($flight->type_of_aircraft ?? '', 0, 4), 4, ' ');
                                @endphp
                                {!! $charBoxes($aircraft) !!}
                            </div>
                        </td>
                        <td style="width: 20%;">
                            <span class="field-label">WAKE TURBULENCE CAT.</span>
                            <div style="padding: 4px; text-align: left; margin-left: 35px;">
                                {!! $charBoxes(str_pad(substr($flight->wake_turbulence_cat ?? '', 0, 1), 1, ' ')) !!}
                            </div>
                        </td>
                        <td style="width: 50%; padding-left: 4px;">
                            <span class="field-label">10. EQUIPMENT</span>
                            <div style="padding: 2px; text-align: left; margin-left: 20px;">
                                <span class="string-box" style="width: 120px;">{{ substr($flight->equipment_10a ?? '', 0) }}</span>
                                <span class="char-box" style="border:0;">/</span>
                                <span class="string-box" style="width: 120px;">{{ substr($flight->equipment_10b ?? '', 0) }}</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Departure Aerodrome and Time -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 5%;">
                            <span class="field-label">&nbsp;</span>
                            <div style="padding: 4px; text-align: center;">
                                &nbsp;
                            </div>
                        </td>
                        <td style="width: 25%;">
                            <span class="field-label">13. DEPARTURE AERODROME</span>
                            <div style="padding: 4px; text-align: center;">
                                {!! $charBoxes($flight->departure_aerodrome ?? 'RPUS') !!}
                            </div>
                        </td>
                        
                        <td style="width: 15%;">
                            <span class="field-label">TIME</span>
                            <div style="padding: 4px; text-align: left;">
                                @php
                                    $time = \App\Domain\FlightPlans\Rules\UtcFourDigitTime::formatForDisplay($flight->proposed_time) ?? '';
                                @endphp
                                {!! $charBoxes(substr($time, 0, 4)) !!}
                            </div>
                        </td>
                        <td style="width: 65%;">
                            <span class="field-label">&nbsp;</span>
                            <div style="padding: 4px; text-align: center;">
                                &nbsp;
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Cruising Speed, Level, Route -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 15%;">
                            <span class="field-label">15. CRUISING SPEED</span>
                            <div style="padding: 4px;  text-align: left; margin-left: 10px;">
                                @php
                                    $cruispeed = str_pad(substr($flight->cruising_speed ?? '', 0, 5), 5, ' ');
                                @endphp
                                {!! $charBoxes($cruispeed) !!}
                            </div>
                        </td>
                        <td style="width: 15%;">
                            <span class="field-label">LEVEL</span>
                            <div style="padding: 4px;  text-align: left; margin-left: -1px;">
                                @php
                                    $lvl = str_pad(substr($flight->level ?? '', 0, 4), 4, ' ');
                                @endphp
                                {!! $charBoxes($lvl) !!}
                            </div>
                        </td>
                        <td style="width: 70%;">
                            <span class="field-label">ROUTE</span>
                            <div style="padding: 4px; text-align: left;">
                                <div class="multi-line-box">{{ $flight->route ?? '' }}</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Destination, EET, Alternates -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 25%;">
                            <span class="field-label">16. DESTINATION AERODROME</span>
                            <div style="padding: 4px; text-align: left; margin-left: 10px;">
                                {!! $charBoxes($flight->destination_aerodrome ?? '') !!}
                            </div>
                        </td>
                        <td style="width: 20%;">
                            <span class="field-label">TOTAL EET</span>
                            <div style="padding: 4px; text-align: left;">
                                @php
                                    $eet = \App\Domain\FlightPlans\Rules\UtcFourDigitTime::formatForDisplay($flight->total_eet) ?? '';
                                @endphp
                                {!! $charBoxes(substr($eet, 0, 4)) !!}
                            </div>
                        </td>
                        <td style="width: 25%;">
                            <span class="field-label">ALTN. AERODROME</span>
                            <div style="padding: 4px; text-align: left;">
                                {!! $charBoxes($flight->altn_aerodrome_1 ?? '') !!}
                            </div>
                        </td>
                        <td style="width: 30%;">
                            <span class="field-label">2nd ALTN. AERODROME</span>
                            <div style="padding: 4px; text-align: left;">
                                {!! $charBoxes(str_pad(substr($flight->altn_aerodrome_2 ?? '', 0, 4), 4, ' ')) !!}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Other Information and PBN -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td>
                            <span class="field-label">18. OTHER INFORMATION</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div style="padding: 4px; text-align: left; margin-left: 10px;">
                                @php
                                    $tagHierarchy = [
                                        ['tag' => 'DOF', 'column' => 'other_info_dof'],
                                        ['tag' => 'RMK', 'column' => 'other_info_rmk'],
                                        ['tag' => 'TYP', 'column' => 'other_info_typ'],
                                        ['tag' => 'DEP', 'column' => 'other_info_dep'],
                                        ['tag' => 'RTE', 'column' => 'other_info_route'],
                                        ['tag' => 'DEST', 'column' => 'other_info_dest'],
                                        ['tag' => 'ALTN', 'column' => 'other_info_altn_1'],
                                        ['tag' => 'ALTN2', 'column' => 'other_info_altn_2'],
                                        ['tag' => 'PBN', 'column' => 'other_info_pbn'],
                                        ['tag' => 'REG', 'column' => 'other_info_reg'],
                                        ['tag' => 'OPR', 'column' => 'other_info_opr'],
                                    ];
                                    $tagPairs = [];
                                    foreach ($tagHierarchy as $tagInfo) {
                                        $value = $flight->{$tagInfo['column']} ?? null;
                                        if ($value !== null && trim($value) !== '') {
                                            $tagPairs[] = $tagInfo['tag'] . '/' . $value;
                                        }
                                    }
                                @endphp
                                <div class="multi-line-box">{{ implode(' ', $tagPairs) }}</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Supplementary Information Header -->
        <tr>
            <td colspan="2" style="padding: 2px; border: 1px solid #000; text-align: center; font-weight: normal; font-size: 7px;">
                SUPPLEMENTARY INFORMATION (NOT TO BE TRANSMITTED IN FPL MESSAGES)
            </td>
        </tr>

        <!-- Endurance and Survival Equipment -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 35%; text-align: left;">
                            <span class="field-label">19. ENDURANCE</span>
                            <div class="field-label" style="font-weight: normal; text-align: left; padding-left: 70px;">HR  :  MIN</div>
                            <div style="padding: 4px; text-align: center;">
                                <div style="text-align: left;">
                                    @php
                                        $endur = \App\Domain\FlightPlans\Rules\UtcFourDigitTime::formatForDisplay($flight->endurance) ?? '';
                                    @endphp
                                    <span class="char-box" style="border: 0">E</span>
                                    <span class="char-box" style="border: 0">/</span>
                                    {!! $charBoxes(substr($endur, 0, 4)) !!}
                                </div>
                            </div>
                        </td>
                        <td style="width: 35%; text-align: left;">
                            <span class="field-label">PERSONS ON BOARD</span>
                            <div class="field-label" style="text-align: left;">&nbsp;</div>
                            <div style="padding: 4px; text-align: left;">
                                @php
                                    $pob = str_pad($flight->persons_on_board ?? '', 3, '0', STR_PAD_LEFT);
                                @endphp
                                <span class="char-box" style="border: 0">P</span>
                                <span class="char-box" style="border: 0">/</span>
                                {!! $charBoxes($pob) !!}
                            </div>
                        </td>
                        <td style="width: 30%; text-align: left;">
                            <span class="field-label">EMERGENCY RADIO</span>
                            <div>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">&nbsp;</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">&nbsp;</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">UHF</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">&nbsp;</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">VHF</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">&nbsp;</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">ELT</span>
                            </div>
                            <div style="padding: 4px; text-align: left;">
                                <span class="char-box" style="border: 0">R</span>
                                <span class="char-box" style="border: 0">/</span>
                                <span class="checkbox">{{ ! $flight->emergency_radio_uhf ? 'X' : '' }}</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="checkbox">{{ ! $flight->emergency_radio_vhf ? 'X' : '' }}</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="checkbox">{{ ! $flight->emergency_radio_elt ? 'X' : '' }}</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Survival Equipment Grid -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 50%; text-align: left;">
                            <span class="field-label">SURVIVAL EQUIPMENT</span>
                            <div>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">POLAR</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">DESERT</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">MARITIME</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">JUNGLE</span>
                            </div>
                            <div style="padding: 4px; text-align: left;">
                                <span class="char-box" style="border: 0">S</span>
                                <span class="char-box" style="border: 0">/</span>
                                <span class="checkbox">{{ ! $flight->survival_equipment_polar ? 'X' : '' }}</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="checkbox">{{ ! $flight->survival_equipment_desert ? 'X' : '' }}</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="checkbox">{{ ! $flight->survival_equipment_maritime ? 'X' : '' }}</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="checkbox">{{ ! $flight->survival_equipment_jungle ? 'X' : '' }}</span>
                            </div>
                        </td>
                        <td style="width: 50%; text-align: left;">
                            <span class="field-label">JACKETS</span>
                            <div>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">LIGHT</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">FLUORES</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">UHF</span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">VHF</span>
                            </div>
                            <div style="padding: 4px; text-align: left;">
                                <span class="char-box" style="border: 0">S</span>
                                <span class="char-box" style="border: 0">/</span>
                                <span class="checkbox">{{ ! $flight->jackets_light ? 'X' : '' }}</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="checkbox">{{ ! $flight->jackets_fluores ? 'X' : '' }}</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="checkbox">{{ ! $flight->jackets_uhf ? 'X' : '' }}</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="char-box" style="border: 0">&nbsp;</span>
                                <span class="checkbox">{{ ! $flight->jackets_vhf ? 'X' : '' }}</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Dinghies and Colors -->
        <tr>
            <td colspan="2">

            </td>
        </tr>

        <!-- Aircraft Colour and Markings, Remarks, and Pilot in Command with QR Code -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: {{ $isPdfOnly ? '100%' : '80%' }}; vertical-align: top;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 100%; text-align: left;">
                                        <span class="field-label">DIGHHIES</span>
                                        <div>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">NUMBER</span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: left;">CAPACITY</span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;">COVER</span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: center;"></span>
                                            <span class="char-box" style="border: 0; font-size: 9px; font-weight: normal; text-align: left;">COLOUR</span>
                                        </div>
                                        <div style="padding: 4px; text-align: left;">
                                            <span class="char-box" style="border: 0">D</span>
                                            <span class="char-box" style="border: 0">/</span>
                                                @php
                                                    if ($flight->dinghies_enabled) {
                                                        $dingnum = str_pad($flight->dinghies_number ?? '', 2, '0', STR_PAD_LEFT);
                                                    } else {
                                                        $dingnum = 'XX';
                                                    }
                                                @endphp
                                                {!! $charBoxes($dingnum) !!}
                                            <span class="char-box" style="border: 0">&nbsp;</span>
                                            <span class="char-box" style="border: 0">&nbsp;</span>
                                                @php
                                                    if ($flight->dinghies_enabled) {
                                                        $dingcap = str_pad($flight->dinghies_capacity ?? '', 3, '0', STR_PAD_LEFT);
                                                    } else {
                                                        $dingcap = 'XXX';
                                                    }
                                                @endphp
                                                {!! $charBoxes($dingcap) !!}
                                            <span class="char-box" style="border: 0">&nbsp;</span>
                                            <span class="char-box" style="border: 0">&nbsp;</span>
                                            {!! $charBoxes($flight->dinghies_enabled ? ($flight->dinghies_cover ?? 'X') : 'X') !!}
                                            <span class="char-box" style="border: 0">&nbsp;</span>
                                            <span class="char-box" style="border: 0">&nbsp;</span>
                                            <span class="string-box">{{ $flight->dinghies_enabled ? ($flight->dinghies_color ?? 'X') : 'X' }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 100%; text-align: left;">
                                        <span class="field-label">AIRCRAFT COLOUR AND MARKINGS</span>
                                        <div style="padding: 4px; text-align: left;">
                                            <span class="char-box" style="border: 0">A</span>
                                            <span class="char-box" style="border: 0">/</span>
                                            <span class="string-box string-box-left" style="width: 498px;">{{ $flight->aircraft_colour_and_markings ?? '' }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 100%; text-align: left;">
                                        <span class="field-label">REMARKS</span>
                                        <div style="padding: 4px; text-align: left;">
                                            <span class="char-box" style="border: 0">N</span>
                                            <span class="char-box" style="border: 0">/</span>
                                            <span class="string-box string-box-left" style="width: 498px;">{{ $flight->remarks ?? '' }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 100%; text-align: left;">
                                        <span class="field-label">PILOT IN COMMAND</span>
                                        <div style="padding: 4px; text-align: left;">
                                            <span class="char-box" style="border: 0">C</span>
                                            <span class="char-box" style="border: 0">/</span>
                                            <span class="string-box string-box-left" style="width: 498px;">{{ $flight->pilot_in_command ?? '' }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        @unless($isPdfOnly)
                            <!-- QR Code Section -->
                            <td style="width: 20%; padding-left: 0px; vertical-align: top; text-align: center;">
                                <span class="field-label" style="margin-bottom: 46px;"></span>
                                <div style="padding: 1px; border: 1px solid #000;">
                                    @if(isset($qrCodeBase64))
                                        <img src="{{ $qrCodeBase64 }}" style="width: 140px; height: 140px;" />
                                    @elseif(isset($isPreview) && ($showPreviewActions ?? true))
                                        <div style="width: 120px; height: 120px; border: 1px dashed #666; display: flex; align-items: center; justify-content: center; margin: 0 auto; padding: 8px; box-sizing: border-box; font-size: 9px; line-height: 1.3; text-align: center; color: #444;">
                                            QR will be generated when the flight plan is filed.
                                        </div>
                                    @endif
                                </div>
                            </td>
                        @endunless
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Certification -->
        <tr>
            <td colspan="2">
                <div class="certification-box">
                    <div class="certification-title">CERTIFICATION</div>
                    <p style="margin: 0;">
                        This is to certify that the above entries are true and correct and that, pilot-in-command of this aircraft, pledge not to fly over prohibited and restricted areas; will not willfully deviate from the filed flight plan, except when necessary in the interest of safety; will operate only in accordance with existing Civil and Military regulations; and will not operate in any manner inimical to the security of the Republic of the Philippines. The herein Pilot-in-Command is qualified to fly the route mentioned in this Flight Plan.
                    </p>
                </div>
            </td>
        </tr>

        <!-- Filed By -->
        <tr style="padding-bottom: 4px;">
            <td colspan="2" style="padding: 4px;">
                <span class="field-label">FILED BY:</span>
                <br />
                <br />
            </td>
        </tr>

        <!-- Signature Section -->
        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 23%;  padding: 2px; border-bottom: 1px solid #000; text-align: center;">
                            <span style="font-weight: bold;">{{ $flight->pilot_in_command ?? '' }}</span>
                        </td>
                        <td style="width: 1%; text-align: center;">
                            &nbsp;
                        </td>
                        <td style="width: 23%; padding: 2px; border-bottom: 1px solid #000; text-align: center;">
                            <span style="font-weight: bold;">{{ $flight->pilot_license_no ?? '' }}</span>
                            <span style="font-weight: bold;">&nbsp;/&nbsp;</span>
                            <span style="font-weight: bold;">{{ $flight->pilot_ratings ?? '' }}</span>
                            <span style="font-weight: bold;">&nbsp;/&nbsp;</span>
                            <span style="font-weight: bold;">{{ $flight->license_expiry_date ?? '' }}</span>
                        </td>
                        <td style="width: 6%; text-align: center;">
                            OR
                        </td>
                        <td style="width: 23%; padding: 2px; border-bottom: 1px solid #000; text-align: center;">
                            <span style="font-weight: bold;">{{ $flight->authorized_representative_name ?? '' }}</span>
                        </td>
                        <td style="width: 1%; text-align: center;">
                            &nbsp;
                        </td>
                        <td style="width: 23%; padding: 2px; border-bottom: 1px solid #000; text-align: center;">
                            <span style="font-weight: bold;">{{ $flight->authorized_representative_id_license ?? '' }}</span>
                            <span style="font-weight: bold;">&nbsp;/&nbsp;</span>
                            <span style="font-weight: bold;">{{ $flight->authorized_representative_expiry_date ?? '' }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 23%; padding: 2px; font-size: 8px; font-weight: normal; text-align: center;">
                            <span style="font-weight: normal;">PILOT'S NAME AND SIGNATURE</span>
                        </td>
                        <td style="width: 1%; text-align: center;">
                            &nbsp;
                        </td>
                        <td style="width: 23%; padding: 2px; font-size: 8px; font-weight: normal; text-align: center;">
                            <span style="font-weight: normal;">LICENSE NO. / RATING / EXPIRY DATE</span>
                        </td>
                        <td style="width: 6%; text-align: center;">
                            <span style="font-weight: normal;">&nbsp;</span>
                        </td>
                        <td style="width: 23%; padding: 2px; font-size: 8px; font-weight: normal; text-align: center;">
                            <span style="font-weight: normal;">AUTH. REP'S NAME AND SIGNATURE</span>
                        </td>
                        <td style="width: 1%; text-align: center;">
                            &nbsp;
                        </td>
                        <td style="width: 23%; padding: 2px; font-size: 8px; font-weight: normal; text-align: center;">
                            <span style="font-weight: normal;">LICENSE NO. / EXPIRY DATE</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- CAAP Acceptance -->
        <tr style="border: 1px solid #000;">
            <td colspan="2" style="padding: 1px; font-weight: bold; text-align: center; font-size: 8px;">
                CAAP ACCEPTANCE
            </td>
        </tr>

        <tr>
            <td colspan="2">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="font-size: 11px;">
                        <td style="width: 33.33%; #000; padding: 4px;">
                            <span class="field-label">RECEIVED BY:</span>
                            <div style="border-bottom: 1px solid #000; min-height: 16px; font-weight: bold; text-align: center;">
                                {{ $flight->received_by ?? '' }}
                            </div>
                        </td>
                        <td style="width: 33.33%; #000; padding: 4px;">
                            <span class="field-label">DATE/TIME FILED:</span>
                            <div style="border-bottom: 1px solid #000; min-height: 16px; font-weight: bold; text-align: center;">
                                @php
                                    $receivedDate = $flight->received_date ?? null;
                                    $receivedTime = $flight->received_time ?? null;
                                @endphp
                                @if($receivedDate || $receivedTime)
                                    {{ trim(collect([
                                        $receivedDate,
                                        $receivedTime ? (\App\Domain\FlightPlans\Rules\UtcFourDigitTime::formatForDisplay($receivedTime) ?? trim((string) $receivedTime)).' Z' : null,
                                    ])->filter()->implode(' ')) }}
                                @endif
                            </div>
                        </td>
                        <td style="width: 33.34%; padding: 4px;">
                            <span class="field-label">FACILITY/AIRPORT</span>
                            <div style="border-bottom: 1px solid #000; min-height: 16px; font-weight: bold; text-align: center;">
                                {{ $flight->received_facility ?? '' }}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

    </table>    

    @if(isset($isPreview))
    </div>
    @endif

    @if(isset($isPreview) && ($showPreviewActions ?? true))
    <div style="display:flex; flex-direction:column; align-items:center; gap:8px; margin-top:10px;">
        @if($previewActionHelp ?? null)
            <div style="width: 794px; text-align:center; color:#444; font-size:11px;">
                {{ $previewActionHelp }}
            </div>
        @endif

        <div style="display:flex; justify-content:center; gap:12px;">
        <form method="POST" action="{{ $previewActionUrl ?? route('flightplan.approve') }}">
            @csrf
            <button type="submit">{{ $previewActionLabel ?? 'GENERATE PDF' }}</button>
        </form>

        <form method="POST" action="{{ route('flightplan.edit-preview') }}">
            @csrf
            <button type="submit">EDIT</button>
        </form>

        <form method="POST" action="{{ route('flightplan.discard-preview') }}">
            @csrf
            <button type="submit">DISCARD</button>
        </form>
        </div>
    </div>
    @endif

    @if(isset($isPreview) && (($showReviewActions ?? false) || ($reviewCompleted ?? false)))
        @if($showReviewActions ?? false)
        <div class="echo-review-document-spacer" aria-hidden="true"></div>
        <div class="echo-review-toolbar" aria-label="Flight plan review actions">
            <button class="echo-review-button echo-review-button-neutral" type="button" data-close-review-tab>&larr; Close Review</button>
            <div class="echo-review-toolbar-actions">
                <button class="echo-review-button echo-review-button-danger" type="button" data-open-review-modal="reject-flight-plan">Reject</button>
                <button class="echo-review-button echo-review-button-primary" type="button" data-open-review-modal="accept-flight-plan">&#10003; Accept Flight Plan</button>
            </div>
        </div>

        <div class="echo-review-modal" id="reject-flight-plan" role="dialog" aria-modal="true" aria-labelledby="reject-flight-plan-title" hidden>
            <form class="echo-review-modal-card" method="POST" action="{{ $rejectActionUrl }}">
                @csrf
                <div class="echo-review-modal-body">
                    <h2 class="echo-review-modal-title" id="reject-flight-plan-title">Reject Flight Plan</h2>
                    <p class="echo-review-modal-copy">Return this flight plan to the filer with a reason.</p>
                    <p class="echo-review-wiresign">Reviewer: <span class="echo-review-wiresign-chip">{{ $acceptedByWiresign ?: 'ATMO' }}</span></p>
                    <label for="rejection-reason">Reason for rejection</label>
                    <textarea id="rejection-reason" name="rejection_reason" maxlength="255" required autofocus></textarea>
                </div>
                <div class="echo-review-modal-footer">
                    <button class="echo-review-button echo-review-button-neutral" type="button" data-close-review-modal>Cancel</button>
                    <button class="echo-review-button echo-review-button-danger" type="submit">Reject Flight Plan</button>
                </div>
            </form>
        </div>

        <div class="echo-review-modal" id="accept-flight-plan" role="dialog" aria-modal="true" aria-labelledby="accept-flight-plan-title" hidden>
            <form class="echo-review-modal-card" method="POST" action="{{ $acceptActionUrl }}">
                @csrf
                <div class="echo-review-modal-body">
                    <h2 class="echo-review-modal-title" id="accept-flight-plan-title">Accept Flight Plan?</h2>
                    <p class="echo-review-modal-copy">You are about to accept this flight plan as:</p>
                    <div class="echo-review-acceptance-identity">
                        <div class="echo-review-acceptance-wiresign">{{ $acceptedByWiresign ?: 'ATMO' }}</div>
                        <p class="echo-review-acceptance-role">Air Traffic Management Officer</p>
                    </div>
                    <p class="echo-review-acceptance-note">By continuing, <strong>{{ $acceptedByWiresign ?: 'this ATMO' }}</strong> will be recorded as the accepting ATMO for this flight plan.</p>
                </div>
                <div class="echo-review-modal-footer">
                    <button class="echo-review-button echo-review-button-neutral" type="button" data-close-review-modal>Cancel</button>
                    <button class="echo-review-button echo-review-button-primary" type="submit">Accept Flight Plan</button>
                </div>
            </form>
        </div>
        @endif

        <script>
            (() => {
                const refreshOpenerAndClose = () => {
                    try {
                        if (window.opener && !window.opener.closed) window.opener.location.reload();
                    } catch (error) {
                        // A cross-origin opener may be unavailable; closing remains best effort.
                    }

                    window.close();
                };
                const modals = document.querySelectorAll('.echo-review-modal');
                const openModal = (modal) => {
                    modal.hidden = false;
                    const focusTarget = modal.querySelector('textarea, button[type="submit"]');
                    if (focusTarget) focusTarget.focus();
                };
                const closeModal = (modal) => { modal.hidden = true; };

                document.querySelectorAll('[data-open-review-modal]').forEach((button) => {
                    button.addEventListener('click', () => openModal(document.getElementById(button.dataset.openReviewModal)));
                });
                document.querySelectorAll('[data-close-review-modal]').forEach((button) => {
                    button.addEventListener('click', () => closeModal(button.closest('.echo-review-modal')));
                });
                modals.forEach((modal) => modal.addEventListener('click', (event) => {
                    if (event.target === modal) closeModal(modal);
                }));
                document.querySelectorAll('[data-close-review-tab]').forEach((button) => {
                    button.addEventListener('click', refreshOpenerAndClose);
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') modals.forEach((modal) => { if (!modal.hidden) closeModal(modal); });
                });

                if (document.querySelector('[data-review-completion]')) {
                    refreshOpenerAndClose();
                }
            })();
        </script>
    @endif

    @if(isset($isPreview) && !($showReviewActions ?? false) && !($reviewCompleted ?? false))
    <div style="display:flex; justify-content:center; margin-top:10px;">
        <button class="echo-review-button echo-review-button-neutral" type="button" data-close-review-tab>&larr; Close Preview</button>
    </div>
    @endif

    @if(isset($isPreview) && !($showReviewActions ?? false) && !($reviewCompleted ?? false))
        <script>
            (() => {
                const closePreview = () => {
                    try {
                        if (window.opener && !window.opener.closed) window.opener.location.reload();
                    } catch (error) {
                        // A cross-origin opener may be unavailable; closing remains best effort.
                    }

                    window.close();
                };

                document.querySelectorAll('[data-close-review-tab]').forEach((button) => {
                    button.addEventListener('click', closePreview);
                });
            })();
        </script>
    @endif

</body>
</html>
