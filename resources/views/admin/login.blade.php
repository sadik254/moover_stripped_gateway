<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $company?->name ?? config('app.name', 'Moover') }} Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-shell admin-login-page" data-page="admin-login" data-api-base="{{ url('/api') }}" data-dashboard-url="{{ route('admin.dashboard') }}">
    <main class="login-stage">
        <section class="login-panel" aria-labelledby="login-title">
            <div class="login-brand">
                @if ($company?->logo)
                    <img src="{{ $company->logo }}" alt="{{ $company->name }}" class="company-mark">
                @else
                    <span class="company-mark company-mark--fallback" aria-hidden="true">{{ strtoupper(substr($company?->name ?? 'M', 0, 1)) }}</span>
                @endif
                <span>Operations portal</span>
            </div>

            <div class="login-copy">
                <p class="eyebrow">Secure access</p>
                <h1 id="login-title">{{ $company?->name ?? config('app.name', 'Moover') }} <span>Admin</span></h1>
                <p>Sign in to coordinate your fleet, people, and live bookings.</p>
            </div>

            <form id="admin-login-form" class="login-form" novalidate>
                <div class="form-alert" id="login-error" role="alert" hidden></div>

                <label for="email">Email <b aria-hidden="true">*</b></label>
                <div class="input-shell">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 6.5h17v11h-17zM4 7l8 6 8-6"/></svg>
                    <input id="email" name="email" type="email" autocomplete="email" placeholder="Enter your email" required>
                </div>

                <label for="password">Password <b aria-hidden="true">*</b></label>
                <div class="input-shell">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
                    <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    </button>
                </div>

                <button class="primary-action" id="login-submit" type="submit">
                    <span>Sign in</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h13M14 7l5 5-5 5"/></svg>
                </button>
            </form>

            <p class="login-footnote">For authorized {{ $company?->name ?? config('app.name', 'Moover') }} staff only.</p>
        </section>
    </main>
</body>
</html>
