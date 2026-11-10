<section class="echo-avsec-summary" aria-label="PNP-AVSEC clearance">
    <h2 data-avsec-status-color="{{ $avsecStatus['color'] }}" @style(['color: '.\Filament\Support\Facades\FilamentColor::getColor($avsecStatus['color'])[600]])>{{ $avsecStatus['label'] }}</h2>
    <p>PNP-AVSEC clearance is recorded from the signed offline clearance form. Flight plan acceptance remains with ATMO.</p>
    @if(! $avsecManifestAvailable)
        <p><strong>Manifest Required</strong> — Pilot or Dispatch must complete the linked manifest before a clearance decision can be recorded.</p>
    @elseif(! $canDecideClearance && ! $flight->avsecVerification && ! $avsecDenial)
        <p>Current Pilot signatures on both documents and an active filing are required before recording clearance.</p>
    @endif
    @if($avsecDenial)
        <p><strong>Denial reason:</strong> {{ $avsecDenial->reason }}</p>
        <p>{{ $avsecDenial->new_values['verifier_name'] ?? $avsecDenial->new_values['actor_name'] ?? 'AVSEC officer' }} · {{ $avsecDenial->created_at->timezone('Asia/Manila')->format('Y-m-d H:i') }} (Asia/Manila)</p>
        @if($avsecDenial->new_values['pnp_reference_number'] ?? null)
            <p>Clearance reference: {{ $avsecDenial->new_values['pnp_reference_number'] }}</p>
        @endif
    @endif
    @if(session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif
    @if($errors->any())
        <div role="alert">{{ implode(' ', $errors->all()) }}</div>
    @endif
</section>
<footer class="echo-review-toolbar echo-avsec-toolbar" aria-label="AVSEC preview actions">
    <a class="echo-review-button echo-review-button-neutral" href="{{ $backActionUrl }}" onclick="window.close(); return true;">Close Preview</a>
    <div class="echo-review-toolbar-actions">
        @if($manifestReviewUrl)
            <a class="echo-review-button echo-review-button-neutral" href="{{ $manifestReviewUrl }}" target="_blank" rel="noopener noreferrer">Review Manifest</a>
        @else
            <button class="echo-review-button echo-review-button-neutral" disabled>Review Manifest — Manifest Required</button>
        @endif
        <button class="echo-review-button echo-review-button-primary" type="button" data-open-review-modal="grant-avsec-clearance" @disabled(! $canDecideClearance)>Grant Clearance</button>
        <button class="echo-review-button echo-review-button-danger" type="button" data-open-review-modal="deny-avsec-clearance" @disabled(! $canDecideClearance)>Deny Clearance</button>
    </div>
</footer>
@if($canDecideClearance)
    @foreach(['grant' => 'Grant Clearance', 'deny' => 'Deny Clearance'] as $decision => $label)
        <div class="echo-review-modal" id="{{ $decision }}-avsec-clearance" role="dialog" aria-modal="true" aria-labelledby="{{ $decision }}-avsec-title" hidden>
            <form class="echo-review-modal-card" method="POST" action="{{ route('flights.avsec-clearance', $flight) }}">
                @csrf
                <input type="hidden" name="decision" value="{{ $decision }}">
                <input type="hidden" name="confirmation" value="1">
                <input type="hidden" name="reviewed_fingerprint" value="{{ $reviewedFingerprint }}">
                <div class="echo-review-modal-body">
                    <h2 class="echo-review-modal-title" id="{{ $decision }}-avsec-title">{{ $label }}</h2>
                    <p class="echo-review-modal-copy">{{ $decision === 'grant' ? \App\Domain\FlightPlans\Services\AvsecVerificationService::CONFIRMATION : 'Confirm that PNP-AVSEC clearance has been denied through the offline clearance process. This does not reject the flight plan or manifest.' }}</p>
                    <p class="echo-review-modal-copy">Review both the flight plan and manifest before confirming.</p>
                    @if($decision === 'deny')
                        <label for="avsec-denial-reason">Reason for denial</label>
                        <textarea id="avsec-denial-reason" name="reason" maxlength="255" required>{{ old('reason') }}</textarea>
                    @endif
                    <label for="{{ $decision }}-avsec-reference">Clearance reference (optional)</label>
                    <input id="{{ $decision }}-avsec-reference" type="text" name="reference" maxlength="255" value="{{ old('reference') }}">
                </div>
                <div class="echo-review-modal-footer">
                    <button class="echo-review-button echo-review-button-neutral" type="button" data-close-review-modal>Cancel</button>
                    <button class="echo-review-button {{ $decision === 'grant' ? 'echo-review-button-primary' : 'echo-review-button-danger' }}" type="submit">{{ $label }}</button>
                </div>
            </form>
        </div>
    @endforeach
@endif
