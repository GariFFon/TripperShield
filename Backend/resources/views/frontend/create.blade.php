@extends('frontend.layouts.app')
@section('title', 'New Policy Request')

@push('styles')
<style>
    .plan-card { cursor: pointer; transition: all 0.2s ease; }
    .plan-card.selected { border-color: rgba(99,102,241,0.7) !important; box-shadow: 0 0 0 3px rgba(99,102,241,0.2); }
    .plan-card:hover:not(.selected) { border-color: rgba(99,102,241,0.4); }

    .demo-preset { cursor: pointer; transition: all 0.2s ease; }
    .demo-preset:hover { transform: translateY(-2px); border-color: rgba(99,102,241,0.5); }

    .premium-display { animation: fadeIn 0.3s ease-out; }

    .formula-step {
        display: flex; align-items: center; gap: 8px;
        padding: 10px 14px; border-radius: 10px;
        background: rgba(15,15,26,0.8);
        border: 1px solid rgba(99,102,241,0.15);
        font-size: 13px; font-family: 'JetBrains Mono', monospace;
    }
    .formula-step .op { color: #6366f1; font-weight: bold; }
    .formula-step .val { color: #a5b4fc; font-weight: 600; }
    .formula-step .label { color: #64748b; font-size: 11px; }

    .step-indicator { transition: all 0.3s ease; }
</style>
@endpush

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10" x-data="policyFormApp()" x-init="init()">

    {{-- Header --}}
    <div class="mb-8 flex items-center gap-4">
        <a href="{{ route('frontend.dashboard') }}" class="btn-secondary text-xs px-3 py-2.5 rounded-xl">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-2xl font-black text-white">New Policy Request</h1>
            <p class="text-slate-500 text-sm mt-0.5">Fill the form or use one of the demo presets below</p>
        </div>
    </div>

    @if(request()->query('scenario'))
    <div class="mb-6 glass-card rounded-xl p-4 flex items-center gap-3 border border-brand-500/30">
        <i class="fas fa-info-circle text-brand-400"></i>
        <span class="text-sm text-slate-300"><strong class="text-brand-300">Scenario: {{ ucfirst(request()->query('scenario')) }}</strong> — This insurer scenario will be applied when you submit to insurer.</span>
        <input type="hidden" x-ref="scenarioInput" value="{{ request()->query('scenario') }}">
    </div>
    @endif

    {{-- ===== DEMO DATA PRESETS ===== --}}
    <div class="glass-card rounded-2xl p-6 mb-6 border border-brand-500/20">
        <div class="section-heading">
            <i class="fas fa-magic text-brand-400"></i> Quick Fill — Sample Data Presets
        </div>
        <p class="text-slate-400 text-sm mb-5">Click any preset to instantly fill the entire form with valid sample data. Perfect for demo.</p>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            @php
                $presets = [
                    ['label' => '🌏 International PLUS', 'tag' => 'Most Common', 'color' => '#6366f1', 'key' => 'intl_plus'],
                    ['label' => '🇮🇳 Domestic BASIC', 'tag' => 'Budget', 'color' => '#10b981', 'key' => 'dom_basic'],
                    ['label' => '✈️ Premium Senior', 'tag' => 'High Coverage', 'color' => '#f59e0b', 'key' => 'premium_senior'],
                    ['label' => '🎒 Short Trip', 'tag' => 'Weekend', 'color' => '#8b5cf6', 'key' => 'short_trip'],
                ];
            @endphp
            @foreach($presets as $preset)
            <button type="button"
                    @click="fillPreset('{{ $preset['key'] }}')"
                    class="demo-preset glass-card rounded-xl p-4 text-left border border-slate-700/50"
                    :disabled="loading">
                <div class="text-sm font-semibold text-white mb-1">{{ $preset['label'] }}</div>
                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                     style="background: {{ $preset['color'] }}20; color: {{ $preset['color'] }}; border: 1px solid {{ $preset['color'] }}40;">
                    {{ $preset['tag'] }}
                </div>
            </button>
            @endforeach
        </div>

        <button type="button"
                @click="fillRandom()"
                :disabled="loading"
                class="btn-secondary w-full py-3 rounded-xl text-sm justify-center">
            <i class="fas fa-random"></i>
            <span>Fill with Completely Random Valid Data</span>
        </button>
    </div>

    {{-- ===== MAIN FORM ===== --}}
    <form @submit.prevent="submitForm" autocomplete="off">

        {{-- Agent & Customer --}}
        <div class="glass-card rounded-2xl p-6 mb-5">
            <div class="section-heading">
                <i class="fas fa-user-tie text-brand-400"></i> Agent & Customer Information
            </div>
            <div class="grid md:grid-cols-2 gap-5">
                <div>
                    <label class="form-label">Agent ID <span class="text-red-400">*</span></label>
                    <input type="text" class="form-input" placeholder="e.g., AGENT-17"
                           x-model="form.agent_id" @blur="validateField('agent_id')">
                    <div class="form-error" x-show="errors.agent_id" x-cloak>
                        <i class="fas fa-exclamation-circle"></i> <span x-text="errors.agent_id"></span>
                    </div>
                </div>

                <div>
                    <label class="form-label">Client Request ID <span class="text-red-400">*</span></label>
                    <input type="text" class="form-input font-mono" placeholder="e.g., AGT-2026-0001"
                           x-model="form.client_request_id" @blur="validateField('client_request_id')">
                    <div class="form-error" x-show="errors.client_request_id" x-cloak>
                        <i class="fas fa-exclamation-circle"></i> <span x-text="errors.client_request_id"></span>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">Customer Name <span class="text-red-400">*</span></label>
                    <input type="text" class="form-input" placeholder="Full name of the traveller"
                           x-model="form.customer_name" @blur="validateField('customer_name')">
                    <div class="form-error" x-show="errors.customer_name" x-cloak>
                        <i class="fas fa-exclamation-circle"></i> <span x-text="errors.customer_name"></span>
                    </div>
                </div>

                <div>
                    <label class="form-label">Date of Birth <span class="text-red-400">*</span></label>
                    <input type="date" class="form-input"
                           x-model="form.date_of_birth"
                           :max="maxDob"
                           @change="calculatePremium(); validateField('date_of_birth')">
                    <div class="form-error" x-show="errors.date_of_birth" x-cloak>
                        <i class="fas fa-exclamation-circle"></i> <span x-text="errors.date_of_birth"></span>
                    </div>
                </div>

                <div class="flex items-end">
                    <div class="glass rounded-xl p-4 border border-slate-700/50 w-full text-center" x-show="form.date_of_birth && form.trip_start">
                        <div class="text-xs text-slate-500 uppercase tracking-wide mb-1">Age at Travel</div>
                        <div class="text-2xl font-black text-white" x-text="ageAtTravel || '—'"></div>
                        <div class="text-xs text-slate-500">years old</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Trip Details --}}
        <div class="glass-card rounded-2xl p-6 mb-5">
            <div class="section-heading">
                <i class="fas fa-plane text-brand-400"></i> Trip Details
            </div>
            <div class="grid md:grid-cols-3 gap-5">
                <div>
                    <label class="form-label">Destination <span class="text-red-400">*</span></label>
                    <select class="form-input" x-model="form.destination" @change="calculatePremium()">
                        <option value="">Select destination</option>
                        <option value="DOMESTIC">🇮🇳 Domestic (India)</option>
                        <option value="INTERNATIONAL">🌏 International</option>
                    </select>
                    <div class="form-error" x-show="errors.destination" x-cloak>
                        <i class="fas fa-exclamation-circle"></i> <span x-text="errors.destination"></span>
                    </div>
                </div>

                <div>
                    <label class="form-label">Trip Start Date <span class="text-red-400">*</span></label>
                    <input type="date" class="form-input"
                           x-model="form.trip_start"
                           :min="minTripDate"
                           @change="calculatePremium(); validateField('trip_start')">
                    <div class="form-error" x-show="errors.trip_start" x-cloak>
                        <i class="fas fa-exclamation-circle"></i> <span x-text="errors.trip_start"></span>
                    </div>
                </div>

                <div>
                    <label class="form-label">Trip End Date <span class="text-red-400">*</span></label>
                    <input type="date" class="form-input"
                           x-model="form.trip_end"
                           :min="minTripEndDate"
                           @change="calculatePremium(); validateField('trip_end')">
                    <div class="form-error" x-show="errors.trip_end" x-cloak>
                        <i class="fas fa-exclamation-circle"></i> <span x-text="errors.trip_end"></span>
                    </div>
                    <p class="text-xs text-slate-600 mt-1.5" x-show="tripDays > 0">
                        <i class="fas fa-calendar-alt mr-1 text-brand-400"></i>
                        <span x-text="tripDays + ' day' + (tripDays === 1 ? '' : 's') + ' of travel'"></span>
                    </p>
                </div>
            </div>
        </div>

        {{-- Plan Cards --}}
        <div class="glass-card rounded-2xl p-6 mb-5">
            <div class="section-heading">
                <i class="fas fa-file-contract text-brand-400"></i> Plan & Coverage
            </div>

            {{-- Plan selector cards --}}
            <div class="grid md:grid-cols-3 gap-4 mb-6">
                @php
                    $plans = [
                        ['code' => 'BASIC', 'rate' => 15, 'multi' => '1.00', 'color' => '#10b981', 'icon' => 'shield', 'features' => ['Standard medical coverage', 'Trip cancellation', 'Baggage loss']],
                        ['code' => 'PLUS', 'rate' => 20, 'multi' => '1.25', 'color' => '#6366f1', 'icon' => 'shield-alt', 'features' => ['Enhanced medical', 'Emergency evacuation', 'Sports cover', 'Personal liability']],
                        ['code' => 'PREMIUM', 'rate' => 30, 'multi' => '1.50', 'color' => '#f59e0b', 'icon' => 'crown', 'features' => ['Comprehensive medical', 'Zero excess', 'Cancel anytime', 'Concierge service']],
                    ];
                @endphp
                @foreach($plans as $plan)
                <div class="plan-card glass-card rounded-xl p-5 border border-slate-700/50"
                     :class="form.plan_code === '{{ $plan['code'] }}' ? 'selected' : ''"
                     @click="form.plan_code = '{{ $plan['code'] }}'; calculatePremium()">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                                 style="background: {{ $plan['color'] }}20; border: 1px solid {{ $plan['color'] }}40;">
                                <i class="fas fa-{{ $plan['icon'] }} text-sm" style="color: {{ $plan['color'] }};"></i>
                            </div>
                            <span class="font-bold text-white">{{ $plan['code'] }}</span>
                        </div>
                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                             :style="form.plan_code === '{{ $plan['code'] }}' ? 'border-color: #6366f1; background: #6366f1;' : 'border-color: #374151;'">
                            <i class="fas fa-check text-white text-xs" x-show="form.plan_code === '{{ $plan['code'] }}'"></i>
                        </div>
                    </div>
                    <div class="mb-3">
                        <span class="text-2xl font-black text-white">₹{{ $plan['rate'] }}</span>
                        <span class="text-slate-500 text-sm">/day</span>
                        <span class="ml-2 text-xs px-2 py-0.5 rounded-full font-medium"
                              style="background: {{ $plan['color'] }}20; color: {{ $plan['color'] }};">{{ $plan['multi'] }}x</span>
                    </div>
                    <ul class="space-y-1.5">
                        @foreach($plan['features'] as $f)
                        <li class="flex items-center gap-2 text-xs text-slate-400">
                            <i class="fas fa-check-circle text-xs" style="color: {{ $plan['color'] }};"></i> {{ $f }}
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </div>

            <div class="form-error mb-4" x-show="errors.plan_code" x-cloak>
                <i class="fas fa-exclamation-circle"></i> <span x-text="errors.plan_code"></span>
            </div>

            <div>
                <label class="form-label">Sum Insured (₹) <span class="text-red-400">*</span></label>
                <input type="number" class="form-input" placeholder="e.g., 500000"
                       step="1000" min="1000" max="10000000"
                       x-model.number="form.sum_insured"
                       @blur="validateField('sum_insured')">
                <p class="text-xs text-slate-600 mt-1.5">Min: ₹1,000 — Max: ₹1,00,00,000</p>
                <div class="form-error" x-show="errors.sum_insured" x-cloak>
                    <i class="fas fa-exclamation-circle"></i> <span x-text="errors.sum_insured"></span>
                </div>
            </div>
        </div>

        {{-- Premium Preview --}}
        <div class="glass-card rounded-2xl p-6 mb-5 premium-display border border-brand-500/20"
             x-show="premiumData" x-cloak>
            <div class="section-heading">
                <i class="fas fa-calculator text-brand-400"></i> Live Premium Preview
            </div>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
                <div class="glass rounded-xl p-4 border border-slate-700/50 text-center">
                    <div class="text-xs text-slate-500 uppercase tracking-wide mb-1">Base Rate</div>
                    <div class="font-bold text-white font-mono">₹<span x-text="premiumData?.base_daily_rate ?? '—'"></span>/day</div>
                </div>
                <div class="glass rounded-xl p-4 border border-slate-700/50 text-center">
                    <div class="text-xs text-slate-500 uppercase tracking-wide mb-1">Days</div>
                    <div class="font-bold text-white font-mono"><span x-text="premiumData?.travel_days ?? '—'"></span></div>
                </div>
                <div class="glass rounded-xl p-4 border border-slate-700/50 text-center">
                    <div class="text-xs text-slate-500 uppercase tracking-wide mb-1">Age Factor</div>
                    <div class="font-bold text-brand-300 font-mono"><span x-text="premiumData?.age_multiplier ?? '—'"></span>×</div>
                </div>
                <div class="glass rounded-xl p-4 border border-slate-700/50 text-center">
                    <div class="text-xs text-slate-500 uppercase tracking-wide mb-1">Destination</div>
                    <div class="font-bold text-brand-300 font-mono"><span x-text="premiumData?.destination_multiplier ?? '—'"></span>×</div>
                </div>
                <div class="glass rounded-xl p-4 border border-slate-700/50 text-center">
                    <div class="text-xs text-slate-500 uppercase tracking-wide mb-1">Coverage</div>
                    <div class="font-bold text-brand-300 font-mono"><span x-text="premiumData?.coverage_multiplier ?? '—'"></span>×</div>
                </div>
            </div>

            <div class="flex items-center justify-between p-5 rounded-xl border border-brand-500/30"
                 style="background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(139,92,246,0.1));">
                <div>
                    <div class="text-sm text-slate-400 mb-1">Formula: Base × Days × Age × Destination × Coverage</div>
                    <div class="font-mono text-xs text-slate-500"
                         x-text="`₹${premiumData?.base_daily_rate} × ${premiumData?.travel_days} × ${premiumData?.age_multiplier} × ${premiumData?.destination_multiplier} × ${premiumData?.coverage_multiplier}`">
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-xs text-slate-500 uppercase tracking-wide mb-1">Total Premium</div>
                    <div class="text-4xl font-black gradient-text">₹<span x-text="premiumData?.calculated_premium ?? '—'"></span></div>
                </div>
            </div>

            <div x-show="premiumLoading" class="mt-3 flex items-center gap-2 text-xs text-slate-500">
                <div class="spinner" style="width:14px; height:14px; border-width:1.5px;"></div> Recalculating...
            </div>
        </div>

        {{-- Submit Section --}}
        <div class="glass-card rounded-2xl p-6 border border-brand-500/20">
            <div class="section-heading">
                <i class="fas fa-paper-plane text-brand-400"></i> Submit Policy Request
            </div>

            <div class="flex flex-col sm:flex-row gap-4">
                <button type="submit"
                        class="btn-primary flex-1 py-4 text-base rounded-xl justify-center"
                        :disabled="loading || !formValid">
                    <template x-if="!loading">
                        <span class="flex items-center gap-2 justify-center">
                            <i class="fas fa-paper-plane"></i> Submit Policy Request
                        </span>
                    </template>
                    <template x-if="loading">
                        <span class="flex items-center gap-2 justify-center">
                            <div class="spinner"></div> Processing...
                        </span>
                    </template>
                </button>

                <a href="{{ route('frontend.dashboard') }}" class="btn-secondary py-4 text-base rounded-xl justify-center sm:w-36">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>

            <div class="mt-3 text-xs text-slate-600 flex items-start gap-1.5" x-show="!formValid && Object.keys(errors).length === 0">
                <i class="fas fa-info-circle text-slate-500 mt-0.5"></i>
                Complete all required fields to enable submission
            </div>
        </div>

    </form>

    {{-- Loading Overlay --}}
    <div x-show="loading && submitting" x-cloak
         class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 backdrop-blur-sm">
        <div class="glass-card rounded-2xl p-10 text-center max-w-sm mx-4 border border-brand-500/30">
            <div class="w-16 h-16 border-4 border-brand-500 border-t-transparent rounded-full animate-spin mx-auto mb-6"
                 style="border-color: #6366f1 #6366f1 transparent;"></div>
            <h3 class="text-white font-bold text-lg mb-2">Processing</h3>
            <p class="text-slate-400 text-sm" x-text="loadingMessage">Submitting policy request...</p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function policyFormApp() {
    return {
        form: {
            agent_id: '',
            client_request_id: '',
            customer_name: '',
            date_of_birth: '',
            destination: '',
            trip_start: '',
            trip_end: '',
            plan_code: '',
            sum_insured: 500000,
        },
        errors: {},
        premiumData: null,
        premiumLoading: false,
        loading: false,
        submitting: false,
        loadingMessage: 'Submitting policy request...',

        // ---- Presets ----
        presets: {
            intl_plus: () => ({
                agent_id: 'AGENT-' + (Math.floor(Math.random()*90)+10),
                client_request_id: 'AGT-2026-' + String(Math.floor(Math.random()*9000)+1000).padStart(4,'0'),
                customer_name: 'Rahul Sharma',
                date_of_birth: '1992-07-14',
                destination: 'INTERNATIONAL',
                trip_start: this.futureDate(10),
                trip_end: this.futureDate(24),
                plan_code: 'PLUS',
                sum_insured: 500000,
            }),
            dom_basic: () => ({
                agent_id: 'AGENT-' + (Math.floor(Math.random()*90)+10),
                client_request_id: 'AGT-2026-' + String(Math.floor(Math.random()*9000)+1000).padStart(4,'0'),
                customer_name: 'Priya Verma',
                date_of_birth: '1998-03-22',
                destination: 'DOMESTIC',
                trip_start: this.futureDate(5),
                trip_end: this.futureDate(12),
                plan_code: 'BASIC',
                sum_insured: 150000,
            }),
            premium_senior: () => ({
                agent_id: 'AGENT-' + (Math.floor(Math.random()*90)+10),
                client_request_id: 'AGT-2026-' + String(Math.floor(Math.random()*9000)+1000).padStart(4,'0'),
                customer_name: 'Suresh Patel',
                date_of_birth: '1958-11-05',
                destination: 'INTERNATIONAL',
                trip_start: this.futureDate(15),
                trip_end: this.futureDate(30),
                plan_code: 'PREMIUM',
                sum_insured: 1000000,
            }),
            short_trip: () => ({
                agent_id: 'AGENT-' + (Math.floor(Math.random()*90)+10),
                client_request_id: 'AGT-2026-' + String(Math.floor(Math.random()*9000)+1000).padStart(4,'0'),
                customer_name: 'Anjali Singh',
                date_of_birth: '1995-09-18',
                destination: 'INTERNATIONAL',
                trip_start: this.futureDate(7),
                trip_end: this.futureDate(9),
                plan_code: 'PLUS',
                sum_insured: 200000,
            }),
        },

        // ---- Computed ----
        get formValid() {
            return this.form.agent_id && this.form.client_request_id && this.form.customer_name &&
                   this.form.date_of_birth && this.form.destination && this.form.trip_start &&
                   this.form.trip_end && this.form.plan_code && this.form.sum_insured >= 1000;
        },
        get minTripDate() {
            const d = new Date(); d.setDate(d.getDate()+1); return d.toISOString().split('T')[0];
        },
        get minTripEndDate() { return this.form.trip_start || this.minTripDate; },
        get maxDob() {
            const d = new Date(); d.setFullYear(d.getFullYear()-1); return d.toISOString().split('T')[0];
        },
        get tripDays() {
            if (!this.form.trip_start || !this.form.trip_end) return 0;
            const diff = (new Date(this.form.trip_end) - new Date(this.form.trip_start)) / 86400000;
            return Math.max(0, Math.ceil(diff) + 1);
        },
        get ageAtTravel() {
            if (!this.form.date_of_birth || !this.form.trip_start) return null;
            const dob = new Date(this.form.date_of_birth);
            const travel = new Date(this.form.trip_start);
            let age = travel.getFullYear() - dob.getFullYear();
            if (travel.getMonth() < dob.getMonth() || (travel.getMonth() === dob.getMonth() && travel.getDate() < dob.getDate())) age--;
            return age;
        },

        // ---- Methods ----
        futureDate(days) {
            const d = new Date(); d.setDate(d.getDate()+days); return d.toISOString().split('T')[0];
        },

        init() {
            const params = new URLSearchParams(window.location.search);
            if (params.get('scenario')) this.form.scenario = params.get('scenario');
            if (!this.form.trip_start) this.form.trip_start = this.futureDate(10);
            if (!this.form.trip_end) this.form.trip_end = this.futureDate(20);
            this.$nextTick(() => this.calculatePremium());
        },

        fillPreset(key) {
            const preset = {
                intl_plus: {
                    agent_id: 'AGENT-' + (Math.floor(Math.random()*90)+10),
                    client_request_id: 'AGT-2026-' + String(Math.floor(Math.random()*9000)+1000).padStart(4,'0'),
                    customer_name: 'Rahul Sharma',
                    date_of_birth: '1992-07-14',
                    destination: 'INTERNATIONAL',
                    trip_start: this.futureDate(10),
                    trip_end: this.futureDate(24),
                    plan_code: 'PLUS',
                    sum_insured: 500000,
                },
                dom_basic: {
                    agent_id: 'AGENT-' + (Math.floor(Math.random()*90)+10),
                    client_request_id: 'AGT-2026-' + String(Math.floor(Math.random()*9000)+1000).padStart(4,'0'),
                    customer_name: 'Priya Verma',
                    date_of_birth: '1998-03-22',
                    destination: 'DOMESTIC',
                    trip_start: this.futureDate(5),
                    trip_end: this.futureDate(12),
                    plan_code: 'BASIC',
                    sum_insured: 150000,
                },
                premium_senior: {
                    agent_id: 'AGENT-' + (Math.floor(Math.random()*90)+10),
                    client_request_id: 'AGT-2026-' + String(Math.floor(Math.random()*9000)+1000).padStart(4,'0'),
                    customer_name: 'Suresh Patel',
                    date_of_birth: '1958-11-05',
                    destination: 'INTERNATIONAL',
                    trip_start: this.futureDate(15),
                    trip_end: this.futureDate(30),
                    plan_code: 'PREMIUM',
                    sum_insured: 1000000,
                },
                short_trip: {
                    agent_id: 'AGENT-' + (Math.floor(Math.random()*90)+10),
                    client_request_id: 'AGT-2026-' + String(Math.floor(Math.random()*9000)+1000).padStart(4,'0'),
                    customer_name: 'Anjali Singh',
                    date_of_birth: '1995-09-18',
                    destination: 'INTERNATIONAL',
                    trip_start: this.futureDate(7),
                    trip_end: this.futureDate(9),
                    plan_code: 'PLUS',
                    sum_insured: 200000,
                },
            };
            if (preset[key]) {
                Object.assign(this.form, preset[key]);
                this.errors = {};
                this.calculatePremium();
                showToast('✨ Demo data filled!', 'success');
            }
        },

        fillRandom() {
            const names = ['Amit Kumar','Sneha Rao','Vikram Joshi','Deepika Nair','Karthik Menon','Pooja Gupta'];
            const plans = ['BASIC','PLUS','PREMIUM'];
            const dests = ['DOMESTIC','INTERNATIONAL'];
            const baseYear = 2026;
            const birthYear = 1960 + Math.floor(Math.random()*35);
            const birthMonth = String(Math.floor(Math.random()*12)+1).padStart(2,'0');
            const birthDay = String(Math.floor(Math.random()*28)+1).padStart(2,'0');
            const tripLen = Math.floor(Math.random()*25)+3;
            const tripOffset = Math.floor(Math.random()*30)+5;
            const ins = [100000,200000,300000,500000,750000,1000000][Math.floor(Math.random()*6)];

            Object.assign(this.form, {
                agent_id: 'AGENT-' + (Math.floor(Math.random()*90)+10),
                client_request_id: 'AGT-' + baseYear + '-' + String(Math.floor(Math.random()*9000)+1000).padStart(4,'0'),
                customer_name: names[Math.floor(Math.random()*names.length)],
                date_of_birth: `${birthYear}-${birthMonth}-${birthDay}`,
                destination: dests[Math.floor(Math.random()*2)],
                trip_start: this.futureDate(tripOffset),
                trip_end: this.futureDate(tripOffset + tripLen),
                plan_code: plans[Math.floor(Math.random()*3)],
                sum_insured: ins,
            });
            this.errors = {};
            this.calculatePremium();
            showToast('🎲 Random data generated!', 'info');
        },

        async calculatePremium() {
            if (!this.form.destination || !this.form.trip_start || !this.form.trip_end || !this.form.plan_code || !this.form.date_of_birth) return;
            this.premiumLoading = true;
            try {
                const r = await fetch('{{ route('frontend.calculate-premium') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({
                        destination: this.form.destination, trip_start: this.form.trip_start,
                        trip_end: this.form.trip_end, plan_code: this.form.plan_code,
                        date_of_birth: this.form.date_of_birth,
                    })
                });
                const d = await r.json();
                if (d.success) this.premiumData = d.data;
            } catch(e) {
                console.warn('Premium calc failed', e);
            } finally {
                this.premiumLoading = false;
            }
        },

        validateField(field) {
            const v = this.form[field];
            let err = '';
            if (field === 'agent_id' && !v) err = 'Agent ID is required';
            if (field === 'client_request_id' && !v) err = 'Client Request ID is required';
            if (field === 'customer_name' && !v) err = 'Customer name is required';
            if (field === 'date_of_birth') {
                if (!v) err = 'Date of birth is required';
                else if (new Date(v) >= new Date()) err = 'Must be in the past';
            }
            if (field === 'destination' && !v) err = 'Destination is required';
            if (field === 'trip_start') {
                if (!v) err = 'Trip start is required';
                else if (new Date(v) <= new Date()) err = 'Must be a future date';
            }
            if (field === 'trip_end') {
                if (!v) err = 'Trip end is required';
                else if (this.form.trip_start && new Date(v) < new Date(this.form.trip_start)) err = 'Must be on or after trip start';
            }
            if (field === 'plan_code' && !v) err = 'Plan selection is required';
            if (field === 'sum_insured') {
                if (!v || v < 1000) err = 'Minimum ₹1,000';
                else if (v > 10000000) err = 'Maximum ₹1,00,00,000';
            }
            if (err) this.errors[field] = err; else delete this.errors[field];
        },

        async submitForm() {
            ['agent_id','client_request_id','customer_name','date_of_birth','destination','trip_start','trip_end','plan_code','sum_insured']
                .forEach(f => this.validateField(f));
            if (Object.keys(this.errors).length > 0) { showToast('Please fix the errors above', 'error'); return; }

            this.loading = true;
            this.submitting = true;
            this.loadingMessage = 'Creating your policy request...';

            try {
                const payload = {...this.form};
                const scenario = new URLSearchParams(window.location.search).get('scenario');
                if (scenario) payload.scenario = scenario;

                const r = await fetch('{{ route('frontend.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload)
                });
                const d = await r.json();
                if (d.success) {
                    showToast(d.duplicate ? '⚠️ Duplicate prevented! Showing existing.' : '✅ Policy request created!', 'success');
                    setTimeout(() => window.location.href = d.redirect, 1200);
                } else {
                    showToast(d.message || 'Failed to create policy request', 'error');
                    if (d.errors) this.errors = d.errors;
                    this.submitting = false;
                }
            } catch(e) {
                showToast('Network error. Please try again.', 'error');
                this.submitting = false;
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
@endpush