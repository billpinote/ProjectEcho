@php
    $documents = $package->status === 'authorized' ? $package->authorized_documents : $package->documents;
@endphp
<details>
    <summary>{{ match ($package->status) {
        'pending' => 'Pilot approval pending',
        'authorized' => 'Pilot approved',
        'declined' => 'Pilot declined',
        default => str($package->status)->headline(),
    } }} · Flight plan version {{ $package->flight_revision }}</summary>
    <p>Authorization record #{{ $package->id }}</p>
    <dl>
        <dt>Requested (UTC)</dt>
        <dd>{{ $package->requested_at?->copy()->utc()->format('Y-m-d H:i:s') ?? 'Not captured' }}</dd>
        <dt>Decided (UTC)</dt>
        <dd>{{ $package->decided_at?->copy()->utc()->format('Y-m-d H:i:s') ?? 'No decision recorded' }}</dd>
        <dt>Authentication method</dt>
        <dd>{{ $package->authentication['method'] ?? 'Not captured' }}</dd>
        <dt>Signature</dt>
        <dd>{{ $package->signature_version === null ? 'Not applied' : 'Version '.$package->signature_version }}</dd>
    </dl>
    <ul>
        @foreach($documents ?? [] as $type => $document)
            @continue($package->signed_document_types !== null && !in_array($type, $package->signed_document_types, true))
            <li>
                {{ str($type)->headline() }} · Document revision {{ $document['document_revision'] ?? 'Not captured' }}
                @if($package->status !== 'pending')
                    · {{ ($currentDocumentPackages[$type] ?? null) === $package->id ? 'Current document authorization' : 'Historical authorization evidence' }}
                    · <a href="{{ route('filing.document', ['package' => $package, 'type' => $type]) }}" target="_blank" rel="noopener noreferrer">View preserved document</a>
                @endif
            </li>
        @endforeach
    </ul>
</details>
