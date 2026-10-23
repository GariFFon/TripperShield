<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'PolicyFlow') — Travel Insurance Platform</title>
    <meta name="description" content="PolicyFlow — Fault-Tolerant Travel Insurance Issuance & Reconciliation Engine built with Laravel 11.">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        brand: {
                            50:  '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            300: '#a5b4fc',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                            950: '#1e1b4b',
                        },
                        surface: {
                            DEFAULT: '#0f0f1a',
                            card: '#161627',
                            raised: '#1e1e35',
                            border: '#2a2a45',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.4s ease-out',
                        'slide-up': 'slideUp 0.4s ease-out',
                        'glow-pulse': 'glowPulse 2s ease-in-out infinite',
                        'shimmer': 'shimmer 1.5s infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        glowPulse: {
                            '0%, 100%': { boxShadow: '0 0 20px rgba(99,102,241,0.3)' },
                            '50%': { boxShadow: '0 0 40px rgba(99,102,241,0.6)' },
                        },
                        shimmer: {
                            '0%': { backgroundPosition: '-200% 0' },
                            '100%': { backgroundPosition: '200% 0' },
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0a14;
            color: #e2e8f0;
            min-height: 100vh;
        }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0f0f1a; }
        ::-webkit-scrollbar-thumb { background: #4338ca; border-radius: 3px; }

        .glass {
            background: rgba(22, 22, 39, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(99,102,241,0.15);
        }
        .glass-card {
            background: linear-gradient(135deg, rgba(30,30,53,0.9) 0%, rgba(22,22,39,0.9) 100%);
            border: 1px solid rgba(99,102,241,0.2);
            backdrop-filter: blur(20px);
        }
        .glow-brand {
            box-shadow: 0 0 30px rgba(99,102,241,0.3), inset 0 1px 0 rgba(255,255,255,0.1);
        }
        .glow-green { box-shadow: 0 0 20px rgba(16,185,129,0.3); }
        .glow-red { box-shadow: 0 0 20px rgba(239,68,68,0.3); }

        .gradient-text {
            background: linear-gradient(135deg, #818cf8, #6366f1, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .gradient-border {
            position: relative;
        }
        .gradient-border::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            padding: 1px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6, #6366f1);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
        }

        /* Status badges */
        .badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; }
        .badge-received    { background: rgba(107,114,128,0.2); color: #9ca3af; border: 1px solid rgba(107,114,128,0.3); }
        .badge-validated   { background: rgba(59,130,246,0.2); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3); }
        .badge-submitted   { background: rgba(245,158,11,0.2); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); }
        .badge-pending_confirmation { background: rgba(249,115,22,0.2); color: #fb923c; border: 1px solid rgba(249,115,22,0.3); }
        .badge-issued      { background: rgba(16,185,129,0.2); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
        .badge-failed      { background: rgba(239,68,68,0.2); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
        .badge-needs_reconciliation { background: rgba(139,92,246,0.2); color: #a78bfa; border: 1px solid rgba(139,92,246,0.3); }

        /* Buttons */
        .btn-primary {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 12px 24px; font-weight: 600; font-size: 14px;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: white; border-radius: 10px; border: none;
            box-shadow: 0 4px 15px rgba(99,102,241,0.4);
            transition: all 0.2s ease; cursor: pointer; gap: 8px;
            text-decoration: none;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 25px rgba(99,102,241,0.5);
        }
        .btn-primary:active { transform: translateY(0); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .btn-secondary {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 12px 24px; font-weight: 600; font-size: 14px;
            background: rgba(99,102,241,0.1); color: #818cf8;
            border: 1px solid rgba(99,102,241,0.3); border-radius: 10px;
            transition: all 0.2s ease; cursor: pointer; gap: 8px;
            text-decoration: none;
        }
        .btn-secondary:hover { background: rgba(99,102,241,0.2); border-color: rgba(99,102,241,0.5); color: #a5b4fc; }

        .btn-success {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 20px; font-weight: 600; font-size: 13px;
            background: linear-gradient(135deg, #059669, #10b981);
            color: white; border-radius: 8px; border: none;
            box-shadow: 0 4px 12px rgba(16,185,129,0.3);
            transition: all 0.2s ease; cursor: pointer; gap: 6px;
            text-decoration: none;
        }
        .btn-success:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(16,185,129,0.4); }

        .btn-danger {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 20px; font-weight: 600; font-size: 13px;
            background: linear-gradient(135deg, #dc2626, #ef4444);
            color: white; border-radius: 8px; border: none;
            box-shadow: 0 4px 12px rgba(239,68,68,0.3);
            transition: all 0.2s ease; cursor: pointer; gap: 6px;
            text-decoration: none;
        }
        .btn-danger:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(239,68,68,0.4); }

        /* Form inputs */
        .form-input {
            width: 100%; padding: 12px 16px;
            background: rgba(15,15,26,0.8);
            border: 1px solid rgba(99,102,241,0.25);
            border-radius: 10px; color: #e2e8f0;
            font-size: 14px; font-family: 'Inter', sans-serif;
            transition: all 0.2s ease;
            outline: none;
        }
        .form-input:focus {
            border-color: rgba(99,102,241,0.7);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
            background: rgba(15,15,26,1);
        }
        .form-input::placeholder { color: #4b5563; }
        .form-input option { background: #161627; color: #e2e8f0; }
        .form-label { display: block; font-size: 13px; font-weight: 500; color: #94a3b8; margin-bottom: 6px; }
        .form-error { font-size: 12px; color: #f87171; margin-top: 4px; display: flex; align-items: center; gap: 4px; }

        /* Nav */
        .nav-link {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 14px; border-radius: 8px;
            font-size: 14px; font-weight: 500; color: #94a3b8;
            text-decoration: none; transition: all 0.2s ease;
        }
        .nav-link:hover { background: rgba(99,102,241,0.1); color: #a5b4fc; }
        .nav-link.active { background: rgba(99,102,241,0.15); color: #818cf8; }

        /* Toast */
        .toast {
            position: fixed; top: 80px; right: 20px; z-index: 9999;
            padding: 14px 18px; border-radius: 12px;
            display: flex; align-items: center; gap: 12px;
            font-size: 14px; font-weight: 500;
            animation: slideInRight 0.3s ease-out;
            max-width: 380px; pointer-events: all;
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100%); }
            to { opacity: 1; transform: translateX(0); }
        }
        .toast-success { background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.4); color: #34d399; }
        .toast-error { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.4); color: #f87171; }
        .toast-info { background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.4); color: #818cf8; }

        /* Table */
        .table-row { transition: background 0.15s ease; }
        .table-row:hover { background: rgba(99,102,241,0.05); }

        /* Loading spinner */
        .spinner { width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.2); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Stat card hover */
        .stat-card { transition: all 0.25s ease; cursor: pointer; text-decoration: none; }
        .stat-card:hover { transform: translateY(-3px); border-color: rgba(99,102,241,0.5) !important; }

        /* Section heading */
        .section-heading { font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: #6366f1; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .section-heading::after { content: ''; flex: 1; height: 1px; background: linear-gradient(to right, rgba(99,102,241,0.3), transparent); }
    </style>

    @stack('styles')
</head>
<body x-data="{ toasts: [] }" class="antialiased">

    <!-- Navigation -->
    <nav class="sticky top-0 z-50 glass border-b border-surface-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <a href="{{ route('frontend.index') }}" class="flex items-center gap-3 group">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center shadow-lg group-hover:shadow-brand-500/40 transition-shadow">
                        <i class="fas fa-shield-alt text-white text-sm"></i>
                    </div>
                    <span class="text-lg font-bold text-white">Policy<span class="gradient-text">Flow</span></span>
                </a>

                <!-- Nav Links -->
                <div class="hidden md:flex items-center gap-1">
                    <a href="{{ route('frontend.index') }}" class="nav-link {{ request()->routeIs('frontend.index') ? 'active' : '' }}">
                        <i class="fas fa-home text-xs"></i> Home
                    </a>
                    <a href="{{ route('frontend.dashboard') }}" class="nav-link {{ request()->routeIs('frontend.dashboard') ? 'active' : '' }}">
                        <i class="fas fa-th-large text-xs"></i> Dashboard
                    </a>
                    <a href="{{ route('frontend.create') }}" class="nav-link {{ request()->routeIs('frontend.create') ? 'active' : '' }}">
                        <i class="fas fa-plus text-xs"></i> New Request
                    </a>
                </div>

                <!-- Right -->
                <div class="flex items-center gap-3">
                    <span class="hidden sm:flex items-center gap-1.5 text-xs text-slate-500">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        System Online
                    </span>
                    <a href="/admin" target="_blank" class="btn-secondary text-xs px-3 py-2">
                        <i class="fas fa-cog"></i>
                        <span class="hidden sm:block">Admin Panel</span>
                    </a>
                    <a href="{{ route('frontend.create') }}" class="btn-primary text-xs px-4 py-2.5">
                        <i class="fas fa-plus"></i> New Policy
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Toast Container -->
    <div class="fixed top-20 right-4 z-[9999] flex flex-col gap-3 pointer-events-none" style="width:380px;">
        <template x-for="(toast, idx) in toasts" :key="toast.id">
            <div class="toast pointer-events-all"
                 :class="'toast-' + toast.type"
                 x-show="toast.visible"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-full"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
                <i class="fas" :class="toast.type === 'success' ? 'fa-check-circle' : (toast.type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle')"></i>
                <span x-text="toast.message" class="flex-1 text-sm"></span>
                <button @click="toasts.splice(idx, 1)" class="opacity-60 hover:opacity-100 ml-2">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
        </template>
    </div>

    <!-- Main Content -->
    <main class="flex-1">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="border-t border-surface-border py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-slate-500 text-sm">PolicyFlow — Fault-Tolerant Insurance Issuance & Reconciliation Engine</p>
            <p class="text-slate-600 text-xs mt-1">Laravel 11 · Filament v3 · SQLite · Campus Placement Demo</p>
        </div>
    </footer>

    <!-- Global toast function -->
    <script>
        function showToast(message, type = 'info', duration = 5000) {
            const el = document.querySelector('[x-data]')?.__x;
            if (!el) return;
            const toast = { id: Date.now(), message, type, visible: true };
            el.$data.toasts.push(toast);
            setTimeout(() => {
                const idx = el.$data.toasts.findIndex(t => t.id === toast.id);
                if (idx > -1) el.$data.toasts.splice(idx, 1);
            }, duration);
        }
    </script>

    @stack('scripts')
</body>
</html>