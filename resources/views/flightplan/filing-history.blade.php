<x-manifest-layout title="Filing authorization history">
    <h1 class="text-2xl font-semibold">Filing authorization history</h1>
    <p>Flight {{ $flight->aircraft_identification }}. Previous decisions remain preserved when a filing changes.</p>
    @forelse($packages as $package)
        <section class="rounded-lg border p-4">
            <h2 class="font-semibold">Authorization record #{{ $package->id }} — {{ str($package->status)->headline() }}</h2>
            <p>{{ in_array($package->id, $currentDocumentPackages, true) ? 'Contains current document signature evidence' : 'Historical authorization record — this is not current filing authorization' }}</p>
            <p>Flight revision {{ $package->flight_revision }} · {{ $package->decided_at ?? $package->requested_at }}</p>
            <p>Authentication: {{ $package->authentication['method'] ?? 'Not captured' }}. Signature: {{ $package->signature_version === null ? 'Not applied' : 'Version '.$package->signature_version }}.</p>
            @foreach(($package->authorized_documents ?? $package->documents) as $type => $document)
                @continue($package->signed_document_types !== null && !in_array($type, $package->signed_document_types, true))
                <p><a href="{{ route('filing.document', ['package' => $package, 'type' => $type]) }}" target="_blank" rel="noopener noreferrer">{{ str($type)->headline() }} — preserved document</a></p>
            @endforeach
        </section>
    @empty
        <p>No package evidence was captured for this legacy filing. Existing authorization history remains in the flight activity timeline.</p>
    @endforelse
</x-manifest-layout>
