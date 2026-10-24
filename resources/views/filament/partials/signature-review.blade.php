@if ($record?->signature)
    <div class="space-y-4">
        <p class="text-sm">Compare this proposed signature with the pilot's identity / KYC evidence before approving this request. Approval activates exactly the version shown.</p>
        <p class="text-sm font-medium">Version {{ $record->signature->version }} · {{ str($record->signature->status)->headline() }}</p>
        <div class="echo-signature-pdf-preview">
            <img src="{{ route('pilot-signatures.show', $record->signature) }}" alt="Signature version submitted for KYC review" />
        </div>
        <a href="{{ route('pilot-signatures.show', $record->signature) }}" target="_blank" rel="noopener noreferrer" class="text-primary-600">Inspect full-resolution signature</a>
        <p class="break-all text-sm">SHA-256: {{ $record->signature->sha256 }}</p>
        <p class="text-sm">Submitted {{ $record->signature->submitted_at?->format('d M Y H:i') }}</p>
        @if ($record->signature->reviewed_at)
            <p class="text-sm">Reviewed by {{ $record->signature->reviewer?->name }} · {{ $record->signature->reviewed_at->format('d M Y H:i') }}</p>
        @endif
        @if ($record->user->approvedSignature && $record->user->approvedSignature->isNot($record->signature))
            <p class="text-sm font-medium">Current approved signature (remains active until approval)</p>
            <div class="echo-signature-pdf-preview">
                <img src="{{ route('pilot-signatures.show', $record->user->approvedSignature) }}" alt="Current approved signature for comparison" />
            </div>
        @endif
    </div>
@else
    <p>No signature submitted with this request.</p>
@endif
