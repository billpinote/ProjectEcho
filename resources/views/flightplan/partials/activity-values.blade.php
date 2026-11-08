@php
    use App\Models\FlightPlanEvent;

    $type = $activityEvent->event_type;
    $recorded = $activityEvent->new_values ?? [];
    $old = $activityEvent->old_values ?? [];
    $actorAccountId = $recorded['actor_account_id'] ?? $activityEvent->actor_user_id;
    $fields = [];
    $heading = 'Recorded changes';
    $unavailable = 'Not captured';
    $showPrevious = false;
    $flightFields = array_diff(app(\App\Domain\FlightPlans\Support\FilingDocumentRegistry::class)->materialFlightFields(), ['filed_by_signature']);

    if ($type === FlightPlanEvent::TYPE_CREATED) {
        $fields = [...$flightFields, 'status', 'revision_number', 'revision_of_id'];
        $heading = 'Original flight plan submission';
    } elseif ($type === FlightPlanEvent::TYPE_UPDATED) {
        $fields = array_intersect(array_keys($recorded), [...$flightFields, 'revision_number', 'revision_of_id']);
        $showPrevious = true;
    } elseif (in_array($type, [FlightPlanEvent::TYPE_MANIFEST_CREATED, FlightPlanEvent::TYPE_MANIFEST_UPDATED, FlightPlanEvent::TYPE_MANIFEST_DELETED], true)) {
        $fields = [...array_keys(app(\App\Domain\FlightPlans\Support\FlightManifestData::class)->fromFlight(new \App\Models\Flight)),
            ...(new \App\Models\FlightManifest)->getFillable(), 'persons'];
        $heading = $type === FlightPlanEvent::TYPE_MANIFEST_DELETED ? 'Manifest before deletion' : 'Manifest submission';
        if ($type === FlightPlanEvent::TYPE_MANIFEST_DELETED) {
            $recorded = $old;
        } else {
            $unavailable = isset($recorded['manifest_snapshot']) ? 'Not captured' : 'Not captured in this event';
            $recorded = $recorded['manifest_snapshot'] ?? $recorded;
            $showPrevious = $type === FlightPlanEvent::TYPE_MANIFEST_UPDATED;
        }
    } elseif ($type === FlightPlanEvent::TYPE_AVSEC_VERIFIED) {
        $fields = ['verification_id', 'flight_revision', 'content_sha256', 'confirmation', 'pnp_reference_number', 'station'];
        $heading = 'AVSEC confirmation evidence';
    } elseif (in_array($type, [FlightPlanEvent::TYPE_PIC_AUTHORIZED, FlightPlanEvent::TYPE_PIC_DECLINED, 'filing_authorization_invalidated'], true)) {
        $fields = array_intersect(['revision_number', 'authorization_method', 'flight_plan_changed', 'manifest_changed'], array_keys($recorded));
        $heading = 'Authorization details';
    }

    $format = fn ($value) => match (true) {
        $value === null || $value === '' => 'Not set',
        is_bool($value) => $value ? 'Yes' : 'No',
        is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        default => $value,
    };
@endphp
@if($fields !== [])
    <details>
        <summary>{{ $heading }}</summary>
        @if($actorAccountId !== null)
            <p>Actor account ID {{ $actorAccountId }}</p>
        @endif
        <dl>
            @foreach($fields as $field)
                <dt>{{ str($field)->headline() }}</dt>
                <dd>
                    @if($showPrevious && array_key_exists($field, $old))
                        {{ $format($old[$field]) }} →
                    @endif
                    @if($field === 'persons' && is_array($recorded[$field] ?? null))
                        <ol>
                            @forelse($recorded[$field] as $person)
                                <li>{{ $format($person['full_name'] ?? null) }} · {{ str($person['classification'] ?? 'Not set')->headline() }} · Nationality {{ $format($person['nationality'] ?? null) }}</li>
                            @empty
                                <li>No persons listed</li>
                            @endforelse
                        </ol>
                    @else
                        {{ array_key_exists($field, $recorded) ? $format($recorded[$field]) : $unavailable }}
                    @endif
                </dd>
            @endforeach
        </dl>
    </details>
@endif
