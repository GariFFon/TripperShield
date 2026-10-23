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
            '<div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 0.5rem; padding: 0.75rem 1rem; margin-top: 0.75rem; font-size: 0.8125rem; color: #fcd34d; text-align: left; line-height: 1.4;">
                <div style="font-weight: 700; color: #fbbf24; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.375rem;">
                    <span>⚡</span> <span>Free Demo Admin Credentials (Pre-Filled)</span>
                </div>
                <div style="font-family: monospace; font-size: 0.8rem; color: #ffffff;">
                    Email: <strong style="color: #6ee7b7;">admin@tripper.com</strong><br>
                    Password: <strong style="color: #6ee7b7;">password</strong>
                </div>
                <div style="margin-top: 0.35rem; font-size: 0.75rem; color: #94a3b8;">
                    Just click &ldquo;Sign in&rdquo; below to access the back-office panel.
                </div>
            </div>'
        );
    }
}
