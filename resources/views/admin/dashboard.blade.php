<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $company?->name ?? config('app.name', 'Moover') }} Operations</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-shell admin-dashboard-page" data-page="admin-dashboard" data-api-base="{{ url('/api') }}" data-login-url="{{ route('admin.login') }}">
<div class="dashboard-layout">
    <aside class="admin-sidebar" aria-label="Primary navigation">
        <a class="sidebar-brand" href="#overview">
            @if ($company?->logo)
                <img src="{{ $company->logo }}" alt="{{ $company->name }}" class="sidebar-logo">
            @else
                <span class="sidebar-logo sidebar-logo--fallback">{{ strtoupper(substr($company?->name ?? 'M', 0, 1)) }}</span>
            @endif
            <span>{{ $company?->name ?? config('app.name', 'Moover') }}</span>
        </a>

        <nav class="sidebar-nav" id="admin-navigation">
            <a class="nav-item nav-item--active" href="#overview" data-view="overview"><span class="nav-glyph">⌂</span>Overview</a>
            <a class="nav-item" href="#bookings" data-view="bookings"><span class="nav-glyph">◆</span>Bookings</a>
            <a class="nav-item" href="#customers" data-view="customers"><span class="nav-glyph">◎</span>Customers</a>
            <a class="nav-item" href="#drivers" data-view="drivers"><span class="nav-glyph">↗</span>Drivers</a>
            <a class="nav-item" href="#vehicle-classes" data-view="vehicle-classes"><span class="nav-glyph">▦</span>Vehicle classes</a>
            <a class="nav-item" href="#airports" data-view="airports"><span class="nav-glyph">✈</span>Airports</a>
            <a class="nav-item" href="#vehicles" data-view="vehicles"><span class="nav-glyph">▣</span>Fleet</a>
            <a class="nav-item" href="#affiliates" data-view="affiliates"><span class="nav-glyph">◇</span>Affiliates</a>
            <a class="nav-item" href="#finance" data-view="finance"><span class="nav-glyph">$</span>Finance</a>
            <a class="nav-item" href="#settings" data-view="settings"><span class="nav-glyph">⚙</span>Settings</a>
        </nav>

        <div class="sidebar-footer">
            <div class="profile-chip"><span class="profile-avatar" id="sidebar-initials">--</span><span id="sidebar-user-name">Loading profile</span></div>
            <button id="logout-button" class="logout-button" type="button">Sign out</button>
            <span class="version-label">Operations console</span>
        </div>
    </aside>

    <main class="dashboard-main">
        <header class="dashboard-header">
            <div>
                <p class="eyebrow" id="page-eyebrow">{{ $company?->name ?? config('app.name', 'Moover') }} / operations</p>
                <h1 id="page-title">Welcome back, <span id="dashboard-user-name">there</span>.</h1>
            </div>
            <div class="header-actions">
                <button class="icon-button" id="global-refresh" type="button" title="Refresh current view">↻</button>
                <span class="header-date" id="dashboard-date"></span>
                <span class="profile-avatar" id="header-initials">--</span>
            </div>
        </header>

        <div class="dashboard-content">
            <div id="dashboard-error" class="form-alert dashboard-alert" role="alert" hidden></div>
            <div id="dashboard-success" class="form-alert form-alert--success dashboard-alert" role="status" hidden></div>

            <section class="admin-view is-active" data-admin-view="overview">
                <div class="section-heading">
                    <div><p class="eyebrow">Today's dispatch board</p><h2>Operational overview</h2></div>
                    <p id="overview-note">Loading live operational data...</p>
                </div>
                <div class="metric-grid">
                    <article class="metric-card"><span class="metric-label">Today's bookings</span><strong id="metric-bookings">--</strong><p id="metric-bookings-note">Loading status breakdown</p></article>
                    <article class="metric-card"><span class="metric-label">Revenue today</span><strong id="metric-completed">--</strong><p>Paid bookings completed today</p></article>
                    <article class="metric-card"><span class="metric-label">Drivers available</span><strong id="metric-drivers">--</strong><p id="metric-drivers-note">Available for dispatch</p></article>
                    <article class="metric-card metric-card--accent"><span class="metric-label">Customers</span><strong id="metric-customers">--</strong><p>People in your customer list</p></article>
                </div>
                <section class="panel live-panel">
                    <div class="panel-heading"><div><p class="eyebrow">Live operations</p><h2>Dispatch feed</h2><p>Bookings requiring attention today.</p></div><button class="text-button" data-go="bookings">View all →</button></div>
                    <div class="table-wrap"><table><thead><tr><th>Booking</th><th>Customer</th><th>Route & status</th><th>Driver / class</th><th>Pickup</th></tr></thead><tbody id="live-feed-body"><tr><td colspan="5" class="empty-state">Loading operational feed...</td></tr></tbody></table></div>
                </section>
                <div class="dashboard-bottom-grid">
                    <section class="panel compact-panel"><div class="panel-heading"><div><p class="eyebrow">Booking options</p><h2>Vehicle classes</h2></div><button class="text-button" data-go="vehicle-classes">Manage →</button></div><div id="availability-list" class="availability-list"><p class="empty-state">Loading vehicle classes...</p></div></section>
                    <section class="panel compact-panel"><div class="panel-heading"><div><p class="eyebrow">Team timeline</p><h2>Recent activity</h2></div></div><ol id="activity-list" class="activity-list"><li class="empty-state">Loading recent activity...</li></ol></section>
                    <section class="panel quick-actions"><div class="panel-heading"><div><p class="eyebrow">Shortcuts</p><h2>Quick actions</h2></div></div><div class="quick-action-grid"><button data-create="booking"><span>+</span><b>New booking</b><small>Quote and reserve</small></button><button data-create="customer"><span>+</span><b>Add customer</b><small>Customer record</small></button><button data-create="driver"><span>+</span><b>Add driver</b><small>Dispatch team</small></button></div></section>
                </div>
            </section>

            @foreach ([
                'bookings' => ['Bookings', 'Manage reservations, assignments, statuses and payments.', 'New booking'],
                'customers' => ['Customers', 'Customer profiles and service preferences.', 'Add customer'],
                'drivers' => ['Drivers', 'Driver accounts, availability and fleet assignments.', 'Add driver'],
                'vehicle-classes' => ['Vehicle classes', 'Passenger capacity, luggage limits and pricing.', 'Add class'],
                'airports' => ['Airports', 'Airports available for fixed-rate Manhattan transfers.', 'Add airport'],
                'vehicles' => ['Fleet vehicles', 'Physical fleet records used by driver operations.', 'Add vehicle'],
                'affiliates' => ['Affiliates', 'Partner operators and payout configuration.', 'Add affiliate'],
            ] as $key => [$title, $description, $action])
                <section class="admin-view" data-admin-view="{{ $key }}">
                    <div class="resource-header"><div><p class="eyebrow">Management</p><h2>{{ $title }}</h2><p>{{ $description }}</p></div><button class="primary-button" data-create="{{ rtrim($key, 's') === 'vehicle-classe' ? 'vehicle-class' : rtrim($key, 's') }}">+ {{ $action }}</button></div>
                    <div class="resource-toolbar"><label class="search-box">⌕ <input type="search" data-search="{{ $key }}" placeholder="Search {{ strtolower($title) }}"></label><select data-filter="{{ $key }}"><option value="">All statuses</option><option value="pending">Pending</option><option value="active">Active</option><option value="confirmed">Confirmed</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div>
                    <section class="panel resource-panel"><div class="table-wrap"><table><thead data-head="{{ $key }}"></thead><tbody data-body="{{ $key }}"><tr><td class="empty-state">Loading {{ strtolower($title) }}...</td></tr></tbody></table></div><div class="pagination-bar" data-pagination="{{ $key }}"></div></section>
                </section>
            @endforeach

            <section class="admin-view" data-admin-view="finance">
                <div class="resource-header"><div><p class="eyebrow">Payments</p><h2>Finance</h2><p>Booking collections, affiliate settlements and disbursements.</p></div></div>
                <div class="finance-grid">
                    <section class="panel"><div class="panel-heading"><div><p class="eyebrow">Affiliate ledger</p><h2>Settlements</h2></div></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Booking</th><th>Affiliate</th><th>Gross</th><th>Payout</th><th>Status</th><th></th></tr></thead><tbody id="settlements-body"><tr><td colspan="7" class="empty-state">Loading settlements...</td></tr></tbody></table></div></section>
                    <section class="panel"><div class="panel-heading"><div><p class="eyebrow">Transfer history</p><h2>Disbursements</h2></div></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Affiliate</th><th>Amount</th><th>Currency</th><th>Status</th><th>Processed</th></tr></thead><tbody id="disbursements-body"><tr><td colspan="6" class="empty-state">Loading disbursements...</td></tr></tbody></table></div></section>
                </div>
            </section>

            <section class="admin-view" data-admin-view="settings">
                <div class="resource-header"><div><p class="eyebrow">Configuration</p><h2>Settings</h2><p>Company identity, booking pricing and service configuration.</p></div></div>
                <div class="settings-grid">
                    <form class="panel settings-form" id="company-settings-form"><div class="panel-heading"><div><p class="eyebrow">Organization</p><h2>Company profile</h2></div></div><div class="form-grid" id="company-settings-fields"></div><div class="form-actions"><button class="primary-button" type="submit">Save company</button></div></form>
                    <form class="panel settings-form" id="system-settings-form"><div class="panel-heading"><div><p class="eyebrow">Pricing</p><h2>System configuration</h2></div></div><div class="form-grid" id="system-settings-fields"></div><div class="form-actions"><button class="primary-button" type="submit">Save configuration</button></div></form>
                </div>
            </section>
        </div>
    </main>
</div>

<dialog id="resource-dialog" class="resource-dialog">
    <form id="resource-form" method="dialog">
        <div class="dialog-header"><div><p class="eyebrow" id="dialog-eyebrow">Create record</p><h2 id="dialog-title">New record</h2></div><button class="dialog-close" value="cancel" type="button" aria-label="Close">×</button></div>
        <div id="dialog-error" class="form-alert" hidden></div>
        <div class="form-grid" id="resource-form-fields"></div>
        <div id="booking-quote-options" class="quote-options" hidden></div>
        <div class="form-actions"><button class="secondary-button dialog-close" type="button">Cancel</button><button class="primary-button" id="dialog-submit" type="submit">Save</button></div>
    </form>
</dialog>
</body>
</html>
