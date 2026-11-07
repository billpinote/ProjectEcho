<details class="echo-preview-activity" id="flight-plan-activity">
    <summary>Flight Plan Activity · {{ $activityEvents->count() }} {{ $activityEvents->count() === 1 ? 'event' : 'events' }}</summary>
    @unless($creationRecorded)
        <p>Original flight plan submission was not captured.</p>
    @endunless
    @if($activityEvents->isNotEmpty())
        <ol class="echo-preview-activity-timeline">
            @foreach($activityEvents as $activityEvent)
                @if($activityEvent instanceof \App\Models\FilingAuthorization)
                    <li>
                        <time>{{ $activityEvent->requested_at?->copy()->utc()->format('Y-m-d H:i:s\Z') }}</time>
                        · Pilot approval requested
                        @include('flightplan.partials.activity-package', ['package' => $activityEvent])
                    </li>
                    @continue
                @endif
                @php
                    $activityLabel = match ($activityEvent->event_type) {
                        \App\Models\FlightPlanEvent::TYPE_CREATED => 'Flight plan created',
                        \App\Models\FlightPlanEvent::TYPE_UPDATED => 'Flight plan updated',
                        \App\Models\FlightPlanEvent::TYPE_MANIFEST_CREATED => 'Manifest created',
                        \App\Models\FlightPlanEvent::TYPE_MANIFEST_UPDATED => 'Manifest updated',
                        \App\Models\FlightPlanEvent::TYPE_MANIFEST_DELETED => 'Manifest deleted',
                        \App\Models\FlightPlanEvent::TYPE_SUBMITTED_FOR_PIC_AUTHORIZATION => 'Submitted for PIC authorization',
                        \App\Models\FlightPlanEvent::TYPE_PIC_AUTHORIZED => 'PIC authorized',
                        \App\Models\FlightPlanEvent::TYPE_PIC_DECLINED => 'PIC declined',
                        \App\Models\FlightPlanEvent::TYPE_SUBMITTED_TO_ATC => 'Submitted to ATMO',
                        \App\Models\FlightPlanEvent::TYPE_ATC_ACCEPTED => 'Accepted by ATMO',
                        \App\Models\FlightPlanEvent::TYPE_ATC_REJECTED => 'Rejected by ATMO',
                        \App\Models\FlightPlanEvent::TYPE_DELAYED => 'Flight delayed',
                        \App\Models\FlightPlanEvent::TYPE_CANCELLED => 'Flight plan cancelled',
                        \App\Models\FlightPlanEvent::TYPE_ARCHIVED => 'Flight plan archived',
                        \App\Models\FlightPlanEvent::TYPE_STARTUP_RECORDED => 'Start-up recorded',
                        \App\Models\FlightPlanEvent::TYPE_BLOCK_OFF_RECORDED => 'Block off recorded',
                        \App\Models\FlightPlanEvent::TYPE_AIRBORNE => 'Airborne recorded',
                        \App\Models\FlightPlanEvent::TYPE_TOUCHDOWN => 'Touchdown recorded',
                        \App\Models\FlightPlanEvent::TYPE_SHUTDOWN_RECORDED => 'Shutdown recorded',
                        \App\Models\FlightPlanEvent::TYPE_FLIGHT_COMPLETED => 'Flight completed',
                        'filing_authorization_invalidated' => match (true) {
                            ($activityEvent->new_values['flight_plan_changed'] ?? false) && ($activityEvent->new_values['manifest_changed'] ?? false) => 'Flight plan and manifest changed. Pilot approval needed again.',
                            ($activityEvent->new_values['flight_plan_changed'] ?? false) => 'Flight plan changed. Pilot approval needed again.',
                            ($activityEvent->new_values['manifest_changed'] ?? false) => 'Manifest changed. Pilot approval needed again.',
                            default => 'Filing changed. Pilot approval needed again.',
                        },
                        default => \Illuminate\Support\Str::headline((string) $activityEvent->event_type),
                    };
                    $oldEobt = $activityEvent->old_values['eobt'] ?? $activityEvent->old_values['proposed_time'] ?? null;
                    $newEobt = $activityEvent->new_values['eobt'] ?? $activityEvent->new_values['proposed_time'] ?? null;
                    $activityActor = $activityEvent->new_values['actor_name'] ?? 'Actor not captured';
                    $activityRole = $activityEvent->new_values['actor_role'] ?? null;
                @endphp
                <li>
                    <span class="echo-preview-activity-time">{{ $activityEvent->created_at?->copy()->utc()->format('Y-m-d H:i:s\Z') ?? '—' }}</span>
                    <span class="echo-preview-activity-event"> · {{ $activityLabel }}</span>
                    @if($activityEvent->event_type === \App\Models\FlightPlanEvent::TYPE_DELAYED)
                        <span> · {{ \App\Domain\FlightPlans\Rules\UtcFourDigitTime::formatForDisplay($oldEobt) ?? 'N/A' }} → {{ \App\Domain\FlightPlans\Rules\UtcFourDigitTime::formatForDisplay($newEobt) ?? 'N/A' }}</span>
                    @endif
                    @if($activityActor)
                        <span> · {{ $activityActor }}@if($activityRole) · {{ str($activityRole)->lower()->headline() }}@endif</span>
                    @endif
                    @if($activityEvent->reason)
                        <span> · {{ $activityEvent->reason }}</span>
                    @endif
                    @include('flightplan.partials.activity-values')
                    @if($package = $activityPackages->get($activityEvent->new_values['filing_authorization_id'] ?? 0))
                        @include('flightplan.partials.activity-package')
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</details>
