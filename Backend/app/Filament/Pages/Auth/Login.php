<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        $this->form->fill([
            'email' => 'admin@tripper.com',
            'password' => 'password',
            'remember' => true,
        ]);
    }

    public function getSubheading(): string | Htmlable | null
    {
        return new HtmlString(
            '<div style="background: #0f172a; border: 1px solid #334155; border-radius: 0.75rem; padding: 0.875rem 1rem; margin-top: 0.75rem; text-align: left; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <div style="font-weight: 700; font-size: 0.8125rem; color: #f59e0b; margin-bottom: 0.375rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span>⚡</span> <span>Demo Admin Credentials (Pre-Filled)</span>
                </div>
                <div style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.8125rem; color: #e2e8f0; line-height: 1.6; background: #1e293b; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid #334155;">
                    <div>Email: <strong style="color: #38bdf8; font-weight: 700;">admin@tripper.com</strong></div>
                    <div>Password: <strong style="color: #34d399; font-weight: 700;">password</strong></div>
                </div>
                <div style="margin-top: 0.5rem; font-size: 0.75rem; color: #cbd5e1;">
                    👉 Just click <strong style="color: #ffffff;">&ldquo;Sign in&rdquo;</strong> below to enter the dashboard.
                </div>
            </div>'
        );
    }
}
