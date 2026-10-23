@extends('frontend.layouts.app')
@section('title', 'Policy #' . $policyRequest->id)

@push('styles')
<style>
    .timeline-node {
        width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border: 2px solid; position: relative; z-index: 2;
    }
    .timeline-line { position: absolute; left: 19px; top: 40px; bottom: -8px; width: 2px; background: rgba(99,102,241,0.2); }
</style>
@endpush

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10"
     x-data="{
        loading: false,
        loadingMsg: '',
        scenario: 'success',
        async doAction(action, id) {
            const msgs = { submit: 'Submitting to insurer...', retry: 'Retrying submission...', reconcile: 'Reconciling with provider...' };
            this.loading = true;
            this.loadingMsg = msgs[action] || 'Processing...';
            try {
                const r = await fetch('/policy/' + id + '/' + action, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ scenario: this.scenario })
                });
                const d = await r.json();
                if (d.success) {
                    showToast('✅ ' + d.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(d.message || 'Action failed', 'error');
                }
            } catch(e) {
                showToast('Network error. Try again.', 'error');
            } finally {
                this.loading = false;
            }
        }
     }">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-8 gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('frontend.dashboard') }}" class="btn-secondary text-xs px-3 py-2.5 rounded-xl flex-shrink-0">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-black text-white">Policy Request #{{ $policyRequest->id }}</h1>
                    @php
                        $statusClass = [
                            'RECEIVED' => 'badge-received', 'VALIDATED' => 'badge-validated',
                            'SUBMITTED' => 'badge-submitted', 'PENDING_CONFIRMATION' => 'badge-pending_confirmation',
                            'ISSUED' => 'badge-issued', 'FAILED' => 'badge-failed',
                            'NEEDS_RECONCILIATION' => 'badge-needs_reconciliation',
                        ][$policyRequest->status] ?? 'badge-received';
                    @endphp
                    <span class="badge {{ $statusClass }} text-sm px-3 py-1">{{ $policyRequest->status }}</span>
                </div>
                <p class="text-slate-500 text-sm mt-1 font-mono">{{ $policyRequest->client_request_id }} — {{ $policyRequest->agent_id }}</p>
            </div>
        </div>

        {{-- Special badges --}}
        <div class="flex flex-wrap gap-2">
            @if($policyRequest->policy_number)
            <span class="badge badge-issued px-3 py-1.5">
                <i class="fas fa-certificate mr-1.5"></i> {{ $policyRequest->policy_number }}
            </span>
            @endif
            @if($policyRequest->provider_reference)
            <span class="badge badge-received font-mono px-3 py-1.5">
                <i class="fas fa-exchange-alt mr-1.5"></i> {{ $policyRequest->provider_reference }}
            </span>
            @endif
        </div>
    </div>

    {{-- Loading overlay --}}
    <div x-show="loading" x-cloak class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center">
        <div class="glass-card rounded-2xl p-10 text-center border border-brand-500/30 max-w-sm">
            <div class="w-14 h-14 border-4 border-brand-500 border-t-transparent rounded-full animate-spin mx-auto mb-5"
                 style="border-color: #6366f1 #6366f1 transparent;"></div>
            <p class="text-white font-semibold text-lg mb-1">Processing</p>
            <p class="text-slate-400 text-sm" x-text="loadingMsg"></p>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Customer & Trip Details --}}
            <div class="glass-card rounded-2xl p-6">
                <div class="section-heading">
                    <i class="fas fa-user-circle text-brand-400"></i> Customer & Trip Details
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    @php
                        $fields = [
                            ['label' => 'Customer Name', 'value' => $policyRequest->customer_name, 'icon' => 'user'],
                            ['label' => 'Agent ID', 'value' => $policyRequest->agent_id, 'icon' => 'id-badge', 'mono' => true],
                            ['label' => 'Client Request ID', 'value' => $policyRequest->client_request_id, 'icon' => 'hashtag', 'mono' => true],
                            ['label' => 'Date of Birth', 'value' => $policyRequest->date_of_birth, 'icon' => 'birthday-cake'],
                            ['label' => 'Age at Travel', 'value' => $policyRequest->age_at_travel . ' years', 'icon' => 'calendar-check'],
                            ['label' => 'Destination', 'value' => ($policyRequest->destination === 'INTERNATIONAL' ? '🌏 ' : '🇮🇳 ') . $policyRequest->destination, 'icon' => 'map-marker-alt'],
                            ['label' => 'Trip Dates', 'value' => $policyRequest->trip_start . ' → ' . $policyRequest->trip_end, 'icon' => 'plane'],
                            ['label' => 'Travel Days', 'value' => $policyRequest->travel_days . ' days', 'icon' => 'clock'],
                            ['label' => 'Plan', 'value' => $policyRequest->plan_code, 'icon' => 'file-contract'],
                            ['label' => 'Sum Insured', 'value' => '₹' . number_format($policyRequest->sum_insured, 0), 'icon' => 'shield-alt'],
                        ];
                    @endphp
                    @foreach($fields as $field)
                    <div class="glass rounded-xl p-4 border border-slate-700/40">
                        <div class="text-xs text-slate-500 uppercase tracking-wide mb-1 flex items-center gap-1.5">
                            <i class="fas fa-{{ $field['icon'] }} text-brand-400"></i> {{ $field['label'] }}
                        </div>
                        <div class="text-white font-semibold {{ isset($field['mono']) ? 'font-mono text-sm' : '' }}">{{ $field['value'] }}</div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Premium Breakdown --}}
            <div class="glass-card rounded-2xl p-6">
                <div class="section-heading">
                    <i class="fas fa-calculator text-brand-400"></i> Premium Breakdown
                </div>

                <div class="grid sm:grid-cols-2 gap-4 mb-5">
                    <div class="rounded-xl p-5 border border-brand-500/30"
                         style="background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(139,92,246,0.08));">
                        <div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Calculated Premium</div>
                        <div class="text-4xl font-black gradient-text">₹{{ number_format($policyRequest->calculated_premium, 2) }}</div>
                    </div>
                    <div class="space-y-2">
                        @php
                            $mults = [
                                ['label' => 'Base Daily Rate', 'val' => '₹' . number_format($policyRequest->base_daily_rate, 2)],
                                ['label' => 'Travel Days', 'val' => $policyRequest->travel_days . ' days'],
                                ['label' => 'Age Multiplier', 'val' => $policyRequest->age_multiplier . '×'],
                                ['label' => 'Destination Mult.', 'val' => $policyRequest->destination_multiplier . '×'],
                                ['label' => 'Coverage Mult.', 'val' => $policyRequest->coverage_multiplier . '×'],
                            ];
                        @endphp
                        @foreach($mults as $m)
                        <div class="flex justify-between items-center py-1.5 border-b border-slate-700/30 last:border-0">
                            <span class="text-sm text-slate-400">{{ $m['label'] }}</span>
                            <span class="text-sm font-semibold text-white font-mono">{{ $m['val'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="glass rounded-xl p-4 border border-slate-700/40">
                    <div class="text-xs text-slate-500 mb-1">Formula</div>
                    <code class="text-brand-300 text-sm font-mono">
                        ₹{{ number_format($policyRequest->base_daily_rate, 2) }}
                        × {{ $policyRequest->travel_days }}
                        × {{ $policyRequest->age_multiplier }}
                        × {{ $policyRequest->destination_multiplier }}
                        × {{ $policyRequest->coverage_multiplier }}
                        = <strong class="text-white">₹{{ number_format($policyRequest->calculated_premium, 2) }}</strong>
                    </code>
                </div>
            </div>

            {{-- Audit Trail --}}
            <div class="glass-card rounded-2xl p-6">
                <div class="section-heading">
                    <i class="fas fa-history text-brand-400"></i> State Machine Audit Trail
                </div>
                @if($policyRequest->stateHistory->count() > 0)
                <div class="space-y-0">
                    @php
                        $stateColors = [
                            'RECEIVED' => ['#6b7280','#6b728030'],
                            'VALIDATED' => ['#60a5fa','#60a5fa30'],
                            'SUBMITTED' => ['#fbbf24','#fbbf2430'],
                            'PENDING_CONFIRMATION' => ['#fb923c','#fb923c30'],
                            'ISSUED' => ['#34d399','#34d39930'],
                            'FAILED' => ['#f87171','#f8717130'],
                            'NEEDS_RECONCILIATION' => ['#a78bfa','#a78bfa30'],
                        ];
                        $stateIcons = [
                            'RECEIVED' => 'inbox', 'VALIDATED' => 'check-double',
                            'SUBMITTED' => 'paper-plane', 'PENDING_CONFIRMATION' => 'clock',
                            'ISSUED' => 'certificate', 'FAILED' => 'times-circle',
                            'NEEDS_RECONCILIATION' => 'sync-alt',
                        ];
                    @endphp
                    @foreach($policyRequest->stateHistory as $idx => $history)
                    @php
                        $col = $stateColors[$history->to_state] ?? ['#6b7280','#6b728030'];
                        $icon = $stateIcons[$history->to_state] ?? 'dot-circle';
                        $isLast = $idx === $policyRequest->stateHistory->count() - 1;
                    @endphp
                    <div class="relative flex gap-4 pb-6 {{ $isLast ? 'pb-0' : '' }}">
                        {{-- Timeline --}}
                        <div class="flex flex-col items-center">
                            <div class="timeline-node" style="border-color: {{ $col[0] }}; background: {{ $col[1] }};">
                                <i class="fas fa-{{ $icon }} text-sm" style="color: {{ $col[0] }};"></i>
                            </div>
                            @if(!$isLast)
                            <div class="flex-1 w-0.5 mt-2" style="background: linear-gradient({{ $col[0] }}, rgba(99,102,241,0.2));"></div>
                            @endif
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 pb-1">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-2">
                                <div class="flex items-center gap-2">
                                    @if($history->from_state)
                                    <span class="text-xs text-slate-500">{{ $history->from_state }}</span>
                                    <i class="fas fa-arrow-right text-brand-500 text-xs"></i>
                                    @else
                                    <span class="text-xs text-slate-600">START</span>
                                    <i class="fas fa-arrow-right text-brand-500 text-xs"></i>
                                    @endif
                                    <span class="text-sm font-bold text-white">{{ $history->to_state }}</span>
                                </div>
                                <span class="text-xs text-slate-500 font-mono">{{ $history->created_at->format('d M Y, H:i:s') }}</span>
                            </div>
                            @if($history->metadata && count($history->metadata) > 0)
                            <div class="glass rounded-lg p-3 border border-slate-700/30">
                                @foreach($history->metadata as $k => $v)
                                <div class="flex gap-2 text-xs">
                                    <span class="text-brand-400 font-mono">{{ $k }}:</span>
                                    <span class="text-slate-300">{{ is_array($v) || is_object($v) ? json_encode($v) : $v }}</span>
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-slate-500 text-center py-8">No state history available</p>
                @endif
            </div>

            {{-- Issuance Attempts --}}
            @if($policyRequest->issuanceAttempts->count() > 0)
            <div class="glass-card rounded-2xl p-6">
                <div class="section-heading">
                    <i class="fas fa-paper-plane text-brand-400"></i> Issuance Attempts ({{ $policyRequest->issuanceAttempts->count() }})
                </div>
                <div class="space-y-4">
                    @foreach($policyRequest->issuanceAttempts as $attempt)
                    @php
                        $aColor = $attempt->response_status === 'SUBMITTED' ? '#fbbf24' : ($attempt->response_status === 'ISSUED' ? '#34d399' : '#f87171');
                    @endphp
                    <div class="glass rounded-xl p-5 border border-slate-700/40">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-bold px-2.5 py-1 rounded-full"
                                      style="background: {{ $aColor }}20; color: {{ $aColor }}; border: 1px solid {{ $aColor }}40;">
                                    Attempt #{{ $attempt->attempt_number }}
                                </span>
                                <span class="text-xs text-slate-500 font-mono">{{ $attempt->started_at->format('d M Y, H:i:s') }}</span>
                            </div>
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full"
                                  style="background: {{ $aColor }}20; color: {{ $aColor }}; border: 1px solid {{ $aColor }}40;">
                                {{ $attempt->response_status }}
                            </span>
                        </div>
                        @if($attempt->response_payload)
                        <pre class="text-xs font-mono rounded-xl p-4 overflow-x-auto border border-slate-700/30"
                             style="background: rgba(10,10,20,0.8); color: #a5b4fc; max-height: 160px;">{{ json_encode($attempt->response_payload, JSON_PRETTY_PRINT) }}</pre>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Provider Events --}}
            @if($policyRequest->providerEvents->count() > 0)
            <div class="glass-card rounded-2xl p-6">
                <div class="section-heading">
                    <i class="fas fa-bell text-brand-400"></i> Provider Callbacks ({{ $policyRequest->providerEvents->count() }})
                </div>
                <div class="space-y-3">
                    @foreach($policyRequest->providerEvents as $event)
                    @php
                        $evtColor = $event->event_type === 'ISSUED' ? '#34d399' : ($event->event_type === 'REJECTED' ? '#f87171' : '#a78bfa');
                    @endphp
                    <div class="glass rounded-xl p-4 border border-slate-700/40">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-bold px-2.5 py-1 rounded-full"
                                      style="background: {{ $evtColor }}20; color: {{ $evtColor }}; border: 1px solid {{ $evtColor }}40;">
                                    {{ $event->event_type }}
                                </span>
                                <span class="text-xs text-slate-500 font-mono truncate max-w-xs">{{ $event->provider_event_id }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $event->processed_at ? 'badge-issued' : 'badge-submitted' }}">
                                    {{ $event->processed_at ? 'Processed' : 'Pending' }}
                                </span>
                                <span class="text-xs text-slate-500 font-mono">{{ $event->created_at->format('H:i:s') }}</span>
                            </div>
                        </div>
                        @if($event->payload)
                        <pre class="text-xs font-mono rounded-xl p-3 mt-3 overflow-x-auto border border-slate-700/30"
                             style="background: rgba(10,10,20,0.8); color: #94a3b8; max-height: 120px;">{{ json_encode($event->payload, JSON_PRETTY_PRINT) }}</pre>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-5">

            {{-- Actions Panel --}}
            <div class="glass-card rounded-2xl p-6 sticky top-20">
                <div class="section-heading">
                    <i class="fas fa-bolt text-brand-400"></i> Actions
                </div>

                {{-- Scenario Selector --}}
                <div class="mb-5">
                    <label class="form-label">Mock Insurer Scenario</label>
                    <select x-model="scenario" class="form-input text-sm">
                        <option value="success">✅ Success — Immediate issuance</option>
                        <option value="failure">❌ Failure — Insurer rejects</option>
                        <option value="duplicate">📋 Duplicate — Tests idempotency</option>
                        <option value="timeout">⏰ Timeout — Needs reconcile</option>
                    </select>
                </div>

                <div class="space-y-3">
                    @if($policyRequest->status === 'VALIDATED')
                    <button @click="doAction('submit', {{ $policyRequest->id }})"
                            class="btn-primary w-full justify-center rounded-xl py-3">
                        <i class="fas fa-paper-plane"></i> Submit to Insurer
                    </button>
                    @endif

                    @if(in_array($policyRequest->status, ['FAILED','NEEDS_RECONCILIATION']))
                    <button @click="doAction('retry', {{ $policyRequest->id }})"
                            class="btn-success w-full justify-center rounded-xl py-3">
                        <i class="fas fa-redo"></i> Retry Submission
                    </button>
                    @endif

                    @if(in_array($policyRequest->status, ['PENDING_CONFIRMATION','NEEDS_RECONCILIATION']))
                    <button @click="doAction('reconcile', {{ $policyRequest->id }})"
                            class="w-full justify-center rounded-xl py-3 inline-flex items-center gap-2 font-semibold text-sm text-purple-300 hover:text-purple-200 transition-colors"
                            style="background: rgba(139,92,246,0.15); border: 1px solid rgba(139,92,246,0.3);">
                        <i class="fas fa-sync-alt"></i> Trigger Reconciliation
                    </button>
                    @endif

                    <a href="{{ route('frontend.dashboard') }}" class="btn-secondary w-full justify-center rounded-xl py-3">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            {{-- Quick Stats --}}
            <div class="glass-card rounded-2xl p-6">
                <div class="section-heading">
                    <i class="fas fa-chart-bar text-brand-400"></i> Quick Stats
                </div>
                <div class="space-y-3">
                    @php
                        $quickStats = [
                            ['label' => 'Attempt Count', 'val' => $policyRequest->attempt_count],
                            ['label' => 'Created At', 'val' => $policyRequest->created_at->format('d M Y, H:i')],
                            ['label' => 'Updated At', 'val' => $policyRequest->updated_at->format('d M Y, H:i')],
                            ['label' => 'State Events', 'val' => $policyRequest->stateHistory->count()],
                            ['label' => 'Callbacks', 'val' => $policyRequest->providerEvents->count()],
                        ];
                    @endphp
                    @if($policyRequest->last_response)
                        @php $quickStats[] = ['label' => 'Last Scenario', 'val' => ucfirst($policyRequest->last_response['scenario'] ?? 'N/A')]; @endphp
                    @endif
                    @foreach($quickStats as $s)
                    <div class="flex justify-between items-center py-1.5 border-b border-slate-700/30 last:border-0">
                        <span class="text-sm text-slate-400">{{ $s['label'] }}</span>
                        <span class="text-sm font-semibold text-white">{{ $s['val'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
@endpush