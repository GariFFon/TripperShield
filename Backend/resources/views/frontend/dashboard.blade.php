@extends('frontend.layouts.app')
@section('title', 'Policy Dashboard')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-white">Policy Dashboard</h1>
            <p class="text-slate-500 text-sm mt-1">Manage and track all insurance policy requests</p>
        </div>
        <div class="flex gap-3">
            <button onclick="window.location.reload()" class="btn-secondary text-sm px-4 py-2.5 rounded-xl">
                <i class="fas fa-sync-alt"></i> <span class="hidden sm:inline">Refresh</span>
            </button>
            <a href="{{ route('frontend.create') }}" class="btn-primary text-sm px-5 py-2.5 rounded-xl">
                <i class="fas fa-plus"></i> New Request
            </a>
        </div>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-8">
        @php
            $statCards = [
                ['label' => 'Total',     'value' => $stats['total'],               'icon' => 'file-alt',           'color' => '#94a3b8', 'status' => ''],
                ['label' => 'Validated', 'value' => $stats['validated'],            'icon' => 'check',              'color' => '#60a5fa', 'status' => 'VALIDATED'],
                ['label' => 'Submitted', 'value' => $stats['submitted'],            'icon' => 'paper-plane',        'color' => '#fbbf24', 'status' => 'SUBMITTED'],
                ['label' => 'Pending',   'value' => $stats['pending'],              'icon' => 'clock',              'color' => '#fb923c', 'status' => 'PENDING_CONFIRMATION'],
                ['label' => 'Issued',    'value' => $stats['issued'],               'icon' => 'check-circle',       'color' => '#34d399', 'status' => 'ISSUED'],
                ['label' => 'Failed',    'value' => $stats['failed'],               'icon' => 'times-circle',       'color' => '#f87171', 'status' => 'FAILED'],
                ['label' => 'Reconcile', 'value' => $stats['needs_reconciliation'], 'icon' => 'exclamation-triangle','color' => '#a78bfa','status' => 'NEEDS_RECONCILIATION'],
            ];
        @endphp
        @foreach($statCards as $card)
        <a href="{{ $card['status'] ? route('frontend.dashboard', ['status' => $card['status']]) : route('frontend.dashboard') }}"
           class="stat-card glass-card rounded-xl p-4 border border-slate-700/50 block">
            <div class="flex items-center justify-between mb-2">
                <div class="text-2xl font-black text-white">{{ $card['value'] }}</div>
                <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                     style="background: {{ $card['color'] }}20;">
                    <i class="fas fa-{{ $card['icon'] }} text-xs" style="color: {{ $card['color'] }};"></i>
                </div>
            </div>
            <div class="text-xs text-slate-500 font-medium">{{ $card['label'] }}</div>
        </a>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="glass-card rounded-2xl p-5 mb-6">
        <form method="GET" action="{{ route('frontend.dashboard') }}">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mb-3">
                <select name="status" class="form-input text-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    @foreach(['RECEIVED','VALIDATED','SUBMITTED','PENDING_CONFIRMATION','ISSUED','FAILED','NEEDS_RECONCILIATION'] as $st)
                    <option value="{{ $st }}" {{ request()->query('status') === $st ? 'selected' : '' }}>{{ $st }}</option>
                    @endforeach
                </select>

                <input type="text" name="agent_id" class="form-input text-sm" placeholder="Agent ID filter" value="{{ request()->query('agent_id') }}">

                <select name="destination" class="form-input text-sm" onchange="this.form.submit()">
                    <option value="">All Destinations</option>
                    <option value="DOMESTIC" {{ request()->query('destination') === 'DOMESTIC' ? 'selected' : '' }}>🇮🇳 Domestic</option>
                    <option value="INTERNATIONAL" {{ request()->query('destination') === 'INTERNATIONAL' ? 'selected' : '' }}>🌏 International</option>
                </select>

                <select name="plan_code" class="form-input text-sm" onchange="this.form.submit()">
                    <option value="">All Plans</option>
                    @foreach(['BASIC','PLUS','PREMIUM'] as $p)
                    <option value="{{ $p }}" {{ request()->query('plan_code') === $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>

                <input type="date" name="date_from" class="form-input text-sm" value="{{ request()->query('date_from') }}" placeholder="From date">
                <input type="date" name="date_to" class="form-input text-sm" value="{{ request()->query('date_to') }}" placeholder="To date">
            </div>

            <div class="flex items-center justify-between">
                <div class="flex flex-wrap gap-2">
                    @php $activeFilters = collect(request()->query())->filter(); @endphp
                    @if($activeFilters->count())
                        @foreach($activeFilters as $key => $val)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium badge-validated">
                            {{ ucfirst(str_replace('_',' ',$key)) }}: {{ $val }}
                            <a href="{{ route('frontend.dashboard', collect(request()->query())->forget($key)->all()) }}" class="hover:text-white">×</a>
                        </span>
                        @endforeach
                        <a href="{{ route('frontend.dashboard') }}" class="text-xs text-slate-500 hover:text-white px-3 py-1">Clear all</a>
                    @endif
                </div>
                <button type="submit" class="btn-secondary text-sm px-4 py-2 rounded-lg">
                    <i class="fas fa-search"></i> Filter
                </button>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="glass-card rounded-2xl overflow-hidden">
        @if($requests->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr style="background: rgba(15,15,26,0.7); border-bottom: 1px solid rgba(99,102,241,0.15);">
                        <th class="px-5 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">ID</th>
                        <th class="px-5 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Request ID</th>
                        <th class="px-5 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider hidden md:table-cell">Agent</th>
                        <th class="px-5 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider hidden lg:table-cell">Customer</th>
                        <th class="px-5 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider hidden md:table-cell">Plan</th>
                        <th class="px-5 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Premium</th>
                        <th class="px-5 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider hidden xl:table-cell">Created</th>
                        <th class="px-5 py-4 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="border-color: rgba(99,102,241,0.08);">
                    @foreach($requests as $policy)
                    <tr class="table-row">
                        <td class="px-5 py-4 text-sm font-mono text-slate-400">#{{ $policy->id }}</td>
                        <td class="px-5 py-4">
                            <span class="text-sm font-mono text-white">{{ $policy->client_request_id }}</span>
                        </td>
                        <td class="px-5 py-4 text-sm text-slate-400 hidden md:table-cell">{{ $policy->agent_id }}</td>
                        <td class="px-5 py-4 text-sm text-slate-300 hidden lg:table-cell">{{ $policy->customer_name }}</td>
                        <td class="px-5 py-4 hidden md:table-cell">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-white">{{ $policy->plan_code }}</span>
                                <span class="text-xs text-slate-500">{{ $policy->destination === 'INTERNATIONAL' ? '🌏' : '🇮🇳' }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-sm font-semibold text-white">₹{{ number_format($policy->calculated_premium, 0) }}</td>
                        <td class="px-5 py-4">
                            @php
                                $statusClass = [
                                    'RECEIVED' => 'badge-received',
                                    'VALIDATED' => 'badge-validated',
                                    'SUBMITTED' => 'badge-submitted',
                                    'PENDING_CONFIRMATION' => 'badge-pending_confirmation',
                                    'ISSUED' => 'badge-issued',
                                    'FAILED' => 'badge-failed',
                                    'NEEDS_RECONCILIATION' => 'badge-needs_reconciliation',
                                ][$policy->status] ?? 'badge-received';
                            @endphp
                            <span class="badge {{ $statusClass }}">{{ $policy->status }}</span>
                        </td>
                        <td class="px-5 py-4 text-xs text-slate-500 hidden xl:table-cell">
                            {{ $policy->created_at->format('d M Y, H:i') }}
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('frontend.show', $policy->id) }}"
                                   class="text-brand-400 hover:text-brand-300 text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-brand-500/10 transition-colors">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>

                                @if($policy->status === 'VALIDATED')
                                <button onclick="quickSubmit({{ $policy->id }}, 'success')"
                                        class="text-emerald-400 hover:text-emerald-300 text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-emerald-500/10 transition-colors">
                                    <i class="fas fa-paper-plane mr-1"></i> Submit
                                </button>
                                @endif

                                @if(in_array($policy->status, ['FAILED','NEEDS_RECONCILIATION']))
                                <button onclick="quickRetry({{ $policy->id }})"
                                        class="text-amber-400 hover:text-amber-300 text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-amber-500/10 transition-colors">
                                    <i class="fas fa-redo mr-1"></i> Retry
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($requests->hasPages())
        <div class="px-5 py-4 flex items-center justify-between border-t" style="border-color: rgba(99,102,241,0.1);">
            <span class="text-sm text-slate-500">
                Showing {{ $requests->firstItem() }}–{{ $requests->lastItem() }} of {{ $requests->total() }} records
            </span>
            <div class="flex gap-1">
                @if($requests->onFirstPage())
                <span class="px-3 py-1.5 text-sm text-slate-600 cursor-not-allowed rounded-lg">‹ Prev</span>
                @else
                <a href="{{ $requests->previousPageUrl() }}" class="px-3 py-1.5 text-sm btn-secondary rounded-lg">‹ Prev</a>
                @endif

                @if($requests->hasMorePages())
                <a href="{{ $requests->nextPageUrl() }}" class="px-3 py-1.5 text-sm btn-secondary rounded-lg">Next ›</a>
                @else
                <span class="px-3 py-1.5 text-sm text-slate-600 cursor-not-allowed rounded-lg">Next ›</span>
                @endif
            </div>
        </div>
        @endif

        @else
        {{-- Empty state --}}
        <div class="py-20 text-center">
            <div class="w-20 h-20 mx-auto mb-6 rounded-2xl bg-brand-500/10 border border-brand-500/20 flex items-center justify-center">
                <i class="fas fa-inbox text-3xl text-brand-400"></i>
            </div>
            <h3 class="text-white font-bold text-xl mb-2">No policy requests found</h3>
            <p class="text-slate-500 mb-6">
                @if(request()->hasAny(['status','agent_id','destination','plan_code']))
                    No requests match your current filters. <a href="{{ route('frontend.dashboard') }}" class="text-brand-400 hover:underline">Clear filters</a>
                @else
                    No policy requests yet. Create your first one!
                @endif
            </p>
            <a href="{{ route('frontend.create') }}" class="btn-primary">
                <i class="fas fa-plus"></i> Create First Policy
            </a>
        </div>
        @endif
    </div>
</div>

{{-- Quick Submit Modal --}}
<div id="submitModal" class="fixed inset-0 z-50 hidden" x-data>
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="document.getElementById('submitModal').classList.add('hidden')"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="glass-card rounded-2xl p-8 max-w-md w-full border border-brand-500/30" style="z-index:1;">
            <h3 class="text-lg font-bold text-white mb-2">Submit to Insurer</h3>
            <p class="text-slate-400 text-sm mb-6">Choose a mock scenario to test different insurer behaviors:</p>

            <div class="space-y-3 mb-6">
                @php $scenarios = [
                    ['key'=>'success','label'=>'✅ Success','desc'=>'Immediate policy issuance','color'=>'#10b981'],
                    ['key'=>'delayed','label'=>'⏳ Delayed','desc'=>'Simulates network latency','color'=>'#f59e0b'],
                    ['key'=>'failure','label'=>'❌ Failure','desc'=>'Insurer rejects the request','color'=>'#ef4444'],
                    ['key'=>'duplicate','label'=>'📋 Duplicate','desc'=>'Tests callback idempotency','color'=>'#3b82f6'],
                    ['key'=>'timeout','label'=>'⏰ Timeout','desc'=>'No callback — needs reconcile','color'=>'#8b5cf6'],
                ]; @endphp
                @foreach($scenarios as $s)
                <button onclick="confirmSubmit('{{ $s['key'] }}')"
                        class="w-full flex items-center gap-4 p-4 rounded-xl border border-slate-700/50 hover:border-brand-500/40 transition-all text-left group"
                        style="background: rgba(15,15,26,0.8);">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 text-base"
                         style="background: {{ $s['color'] }}15; border: 1px solid {{ $s['color'] }}30;">
                        {{ substr($s['label'], 0, 2) }}
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-white">{{ substr($s['label'], 3) }}</div>
                        <div class="text-xs text-slate-500">{{ $s['desc'] }}</div>
                    </div>
                    <i class="fas fa-arrow-right ml-auto text-slate-600 group-hover:text-brand-400 transition-colors"></i>
                </button>
                @endforeach
            </div>

            <button onclick="document.getElementById('submitModal').classList.add('hidden')"
                    class="btn-secondary w-full justify-center rounded-xl">
                Cancel
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let pendingPolicyId = null;

function quickSubmit(id, scenario) {
    pendingPolicyId = id;
    document.getElementById('submitModal').classList.remove('hidden');
}

function quickRetry(id) {
    pendingPolicyId = id;
    document.getElementById('submitModal').classList.remove('hidden');
}

async function confirmSubmit(scenario) {
    if (!pendingPolicyId) return;
    document.getElementById('submitModal').classList.add('hidden');
    showToast('Submitting to insurer (' + scenario + ')...', 'info');

    try {
        const r = await fetch('/policy/' + pendingPolicyId + '/submit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            body: JSON.stringify({ scenario })
        });
        const d = await r.json();
        if (d.success) {
            showToast('✅ Submitted! Status: ' + (d.data?.status || 'updated'), 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(d.message || 'Submit failed', 'error');
        }
    } catch(e) {
        showToast('Network error', 'error');
    }
}
</script>
@endpush