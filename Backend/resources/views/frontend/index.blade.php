@extends('frontend.layouts.app')
@section('title', 'Home')

@section('content')

{{-- Hero Section --}}
<section class="relative overflow-hidden py-24 lg:py-36">
    {{-- Background blobs --}}
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute top-[-100px] left-[-100px] w-[600px] h-[600px] rounded-full" style="background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 70%);"></div>
        <div class="absolute bottom-[-150px] right-[-150px] w-[700px] h-[700px] rounded-full" style="background: radial-gradient(circle, rgba(139,92,246,0.1) 0%, transparent 70%);"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="text-center max-w-4xl mx-auto animate-slide-up">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full glass text-brand-400 text-xs font-semibold mb-8 tracking-wider uppercase border border-brand-500/30">
                <span class="w-1.5 h-1.5 rounded-full bg-brand-400 animate-pulse"></span>
                Fault-Tolerant Insurance Platform
            </div>
            <h1 class="text-5xl lg:text-7xl font-black text-white mb-6 leading-[1.05] tracking-tight">
                Travel Insurance<br><span class="gradient-text">Made Reliable</span>
            </h1>
            <p class="text-lg lg:text-xl text-slate-400 mb-10 max-w-2xl mx-auto leading-relaxed">
                Submit policy requests, calculate premiums instantly, and track issuance with enterprise-grade fault tolerance. Handles duplicates, failures & timeouts automatically.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('frontend.create') }}" class="btn-primary text-base px-8 py-4 rounded-xl animate-glow-pulse">
                    <i class="fas fa-rocket"></i> Create Policy Request
                </a>
                <a href="{{ route('frontend.dashboard') }}" class="btn-secondary text-base px-8 py-4 rounded-xl">
                    <i class="fas fa-th-large"></i> View Dashboard
                </a>
            </div>
        </div>

        {{-- Live Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-20 animate-fade-in">
            @php
                $heroStats = [
                    ['value' => \App\Models\PolicyRequest::count(), 'label' => 'Total Requests', 'icon' => 'file-alt', 'color' => 'brand'],
                    ['value' => \App\Models\PolicyRequest::where('status','ISSUED')->count(), 'label' => 'Policies Issued', 'icon' => 'check-circle', 'color' => 'green'],
                    ['value' => (\App\Models\PolicyRequest::count() > 0 ? round(\App\Models\PolicyRequest::where('status','ISSUED')->count() / \App\Models\PolicyRequest::count() * 100) : 0) . '%', 'label' => 'Success Rate', 'icon' => 'chart-line', 'color' => 'purple'],
                    ['value' => '₹' . number_format(\App\Models\PolicyRequest::avg('calculated_premium') ?? 0, 0), 'label' => 'Avg Premium', 'icon' => 'rupee-sign', 'color' => 'yellow'],
                ];
            @endphp
            @foreach($heroStats as $s)
            <div class="glass-card rounded-2xl p-6 text-center hover:border-brand-500/40 transition-all duration-300">
                <div class="text-3xl font-black text-white mb-1">{{ $s['value'] }}</div>
                <div class="text-xs text-slate-500 uppercase tracking-wide flex items-center justify-center gap-1">
                    <i class="fas fa-{{ $s['icon'] }} text-brand-400 text-xs"></i> {{ $s['label'] }}
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Workflow Steps --}}
<section class="py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-3xl lg:text-4xl font-bold text-white mb-4">How It Works</h2>
            <p class="text-slate-400 max-w-xl mx-auto">7-stage state machine with complete audit trail</p>
        </div>

        <div class="relative flex flex-col md:flex-row items-center justify-between gap-4 overflow-x-auto pb-4">
            {{-- Connector line --}}
            <div class="hidden md:block absolute top-8 left-[5%] right-[5%] h-0.5" style="background: linear-gradient(to right, #4338ca, #7c3aed, #4338ca);"></div>

            @php
                $steps = [
                    ['state' => 'RECEIVED', 'icon' => 'inbox', 'desc' => 'Request intake', 'color' => '#6b7280'],
                    ['state' => 'VALIDATED', 'icon' => 'check-double', 'desc' => 'Premium calculated', 'color' => '#3b82f6'],
                    ['state' => 'SUBMITTED', 'icon' => 'paper-plane', 'desc' => 'Sent to insurer', 'color' => '#f59e0b'],
                    ['state' => 'PENDING', 'icon' => 'clock', 'desc' => 'Awaiting callback', 'color' => '#f97316'],
                    ['state' => 'ISSUED', 'icon' => 'certificate', 'desc' => 'Policy active', 'color' => '#10b981'],
                    ['state' => 'FAILED', 'icon' => 'times-circle', 'desc' => 'Rejected / retry', 'color' => '#ef4444'],
                    ['state' => 'RECONCILE', 'icon' => 'sync-alt', 'desc' => 'Auto-reconcile', 'color' => '#8b5cf6'],
                ];
            @endphp

            @foreach($steps as $step)
            <div class="flex flex-col items-center relative z-10 flex-shrink-0">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-3 border-2 transition-transform hover:scale-110 duration-200"
                     style="background: {{ $step['color'] }}20; border-color: {{ $step['color'] }}50;">
                    <i class="fas fa-{{ $step['icon'] }} text-xl" style="color: {{ $step['color'] }};"></i>
                </div>
                <span class="text-xs font-bold text-white text-center">{{ $step['state'] }}</span>
                <span class="text-xs text-slate-500 text-center mt-0.5">{{ $step['desc'] }}</span>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Features --}}
<section class="py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-3xl lg:text-4xl font-bold text-white mb-4">Enterprise-Grade Reliability</h2>
            <p class="text-slate-400 max-w-xl mx-auto">Built to handle real-world insurer failures without data loss</p>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            @php
                $features = [
                    ['icon' => 'calculator', 'title' => 'Instant Premium', 'desc' => 'Real-time pricing based on plan, destination, age, and trip duration with live preview as you type.', 'color' => '#6366f1', 'items' => ['3 Plans: BASIC, PLUS, PREMIUM', '2x Destinations: Domestic/Intl', 'Age bands with dynamic multipliers', 'Live preview before submit']],
                    ['icon' => 'shield-alt', 'title' => 'Fault-Tolerant', 'desc' => 'Handles unreliable insurer behavior — no duplicate issuance, no incorrect states, ever.', 'color' => '#10b981', 'items' => ['Duplicate intake prevention', 'Callback idempotency (event dedup)', 'State machine with explicit transitions', 'Auto-reconciliation for timeouts']],
                    ['icon' => 'eye', 'title' => 'Full Observability', 'desc' => 'Event-sourced state history and Filament admin panel for complete operations visibility.', 'color' => '#f59e0b', 'items' => ['Complete audit trail', 'Issuance attempt tracking', 'Provider callback logging', 'Admin panel with actions']],
                ];
            @endphp
            @foreach($features as $f)
            <div class="glass-card rounded-2xl p-8 hover:border-brand-500/40 transition-all duration-300 group">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-6 transition-transform group-hover:scale-110 duration-300"
                     style="background: {{ $f['color'] }}20; border: 1px solid {{ $f['color'] }}40;">
                    <i class="fas fa-{{ $f['icon'] }} text-xl" style="color: {{ $f['color'] }};"></i>
                </div>
                <h3 class="text-lg font-bold text-white mb-3">{{ $f['title'] }}</h3>
                <p class="text-slate-400 text-sm mb-5 leading-relaxed">{{ $f['desc'] }}</p>
                <ul class="space-y-2">
                    @foreach($f['items'] as $item)
                    <li class="flex items-center gap-2 text-sm text-slate-300">
                        <i class="fas fa-check text-xs" style="color: {{ $f['color'] }};"></i>{{ $item }}
                    </li>
                    @endforeach
                </ul>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Mock Scenarios --}}
<section class="py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-white mb-4">Test All Scenarios</h2>
            <p class="text-slate-400">Pick a scenario to see how PolicyFlow handles different insurer behaviors</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4">
            @php
                $scenarios = [
                    ['name' => 'Success', 'icon' => 'check-circle', 'color' => '#10b981', 'bg' => '#10b98120', 'desc' => 'Immediate issuance', 'scenario' => 'success'],
                    ['name' => 'Delayed', 'icon' => 'clock', 'color' => '#f59e0b', 'bg' => '#f59e0b20', 'desc' => 'Simulates latency', 'scenario' => 'delayed'],
                    ['name' => 'Failure', 'icon' => 'times-circle', 'color' => '#ef4444', 'bg' => '#ef444420', 'desc' => 'Insurer rejects', 'scenario' => 'failure'],
                    ['name' => 'Duplicate', 'icon' => 'copy', 'color' => '#3b82f6', 'bg' => '#3b82f620', 'desc' => 'Tests idempotency', 'scenario' => 'duplicate'],
                    ['name' => 'Timeout', 'icon' => 'exclamation-triangle', 'color' => '#8b5cf6', 'bg' => '#8b5cf620', 'desc' => 'Needs reconcile', 'scenario' => 'timeout'],
                ];
            @endphp
            @foreach($scenarios as $s)
            <a href="{{ route('frontend.create') }}?scenario={{ $s['scenario'] }}"
               class="glass-card rounded-2xl p-6 text-center group cursor-pointer transition-all duration-300 hover:scale-105"
               style="hover:border-color: {{ $s['color'] }}60">
                <div class="w-14 h-14 mx-auto mb-4 rounded-full flex items-center justify-center transition-transform group-hover:scale-110 duration-300"
                     style="background: {{ $s['bg'] }}; border: 1px solid {{ $s['color'] }}40;">
                    <i class="fas fa-{{ $s['icon'] }} text-2xl" style="color: {{ $s['color'] }};"></i>
                </div>
                <h3 class="text-sm font-bold text-white mb-1">{{ $s['name'] }}</h3>
                <p class="text-xs text-slate-500">{{ $s['desc'] }}</p>
            </a>
            @endforeach
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="py-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="glass-card rounded-3xl p-12" style="border: 1px solid rgba(99,102,241,0.3);">
            <div class="w-16 h-16 mx-auto mb-6 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center shadow-lg" style="box-shadow: 0 0 40px rgba(99,102,241,0.4);">
                <i class="fas fa-rocket text-white text-2xl"></i>
            </div>
            <h2 class="text-3xl lg:text-4xl font-black text-white mb-4">Ready to Test It?</h2>
            <p class="text-slate-400 mb-8 max-w-lg mx-auto">Create a policy request in seconds. Hit "Fill Sample Data" to auto-populate the form — no typing needed.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('frontend.create') }}" class="btn-primary text-base px-8 py-4 rounded-xl">
                    <i class="fas fa-arrow-right"></i> Create Policy Request
                </a>
                <a href="{{ route('frontend.dashboard') }}" class="btn-secondary text-base px-8 py-4 rounded-xl">
                    <i class="fas fa-th-large"></i> View Dashboard
                </a>
            </div>
        </div>
    </div>
</section>

@endsection