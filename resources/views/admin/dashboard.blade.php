<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $company?->name ?? config('app.name', 'Moover') }} Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-shell admin-dashboard-page" data-page="admin-dashboard" data-api-base="{{ url('/api') }}" data-login-url="{{ route('admin.login') }}">
    <div class="dashboard-layout">
        <aside class="admin-sidebar" aria-label="Primary navigation">
            <a class="sidebar-brand" href="{{ route('admin.dashboard') }}">
                @if ($company?->logo)
                    <img src="{{ $company->logo }}" alt="{{ $company->name }}" class="sidebar-logo">
                @else
                    <span class="sidebar-logo sidebar-logo--fallback">{{ strtoupper(substr($company?->name ?? 'M', 0, 1)) }}</span>
                @endif
                <span>{{ $company?->name ?? config('app.name', 'Moover') }}</span>
            </a>

            <nav class="sidebar-nav">
                <a class="nav-item nav-item--active" href="#overview"><span class="nav-glyph">+</span>Overview</a>
                <a class="nav-item" href="#live-operations"><span class="nav-glyph">◉</span>Live operations</a>
                <a class="nav-item" href="#fleet"><span class="nav-glyph">▣</span>People &amp; fleet</a>
                <a class="nav-item" href="#activity"><span class="nav-glyph">↗</span>Activity</a>
            </nav>

            <div class="sidebar-footer">
                <div class="profile-chip"><span class="profile-avatar" id="sidebar-initials">--</span><span id="sidebar-user-name">Loading profile</span></div>
                <button id="logout-button" class="logout-button" type="button"><span>↗</span> Sign out</button>
                <span class="version-label">Operations console</span>
            </div>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-header">
                <div>
                    <p class="eyebrow">{{ $company?->name ?? config('app.name', 'Moover') }} / operations</p>
                    <h1>Welcome back, <span id="dashboard-user-name">there</span>.</h1>
                </div>
                <div class="header-profile"><span class="header-date" id="dashboard-date"></span><span class="profile-avatar" id="header-initials">--</span></div>
            </header>

            <div class="dashboard-content">
                <div id="dashboard-error" class="form-alert dashboard-alert" role="alert" hidden></div>

                <section id="overview" class="overview-section">
                    <div class="section-heading">
                        <div><p class="eyebrow">Today's dispatch board</p><h2>Operational overview</h2></div>
                        <p id="overview-note">Loading live operational data...</p>
                    </div>
                    <div class="metric-grid">
                        <article class="metric-card"><span class="metric-label">Today's bookings</span><strong id="metric-bookings">--</strong><p id="metric-bookings-note">Loading status breakdown</p></article>
                        <article class="metric-card"><span class="metric-label">Completed today</span><strong id="metric-completed">--</strong><p>Trips closed by your team</p></article>
                        <article class="metric-card"><span class="metric-label">Drivers available</span><strong id="metric-drivers">--</strong><p id="metric-drivers-note">Available for dispatch</p></article>
                        <article class="metric-card metric-card--accent"><span class="metric-label">Customers</span><strong id="metric-customers">--</strong><p>People in your customer list</p></article>
                    </div>
                </section>

                <section id="live-operations" class="panel live-panel">
                    <div class="panel-heading"><div><p class="eyebrow">Live operations</p><h2>Dispatch feed</h2><p>Current work requiring your team's attention.</p></div><a href="#overview" class="text-link">Refresh board <span>→</span></a></div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Booking</th><th>Customer</th><th>Route &amp; status</th><th>Driver</th><th>Pickup</th></tr></thead>
                            <tbody id="live-feed-body"><tr><td colspan="5" class="empty-state">Loading operational feed...</td></tr></tbody>
                        </table>
                    </div>
                </section>

                <div class="dashboard-bottom-grid">
                    <section id="fleet" class="panel compact-panel"><div class="panel-heading"><div><p class="eyebrow">Fleet readiness</p><h2>Vehicle availability</h2></div><a href="#fleet" class="text-link">Fleet <span>→</span></a></div><div id="availability-list" class="availability-list"><p class="empty-state">Loading availability...</p></div></section>
                    <section id="activity" class="panel compact-panel"><div class="panel-heading"><div><p class="eyebrow">Team timeline</p><h2>Recent activity</h2></div></div><ol id="activity-list" class="activity-list"><li class="empty-state">Loading recent activity...</li></ol></section>
                    <section class="panel quick-actions"><div class="panel-heading"><div><p class="eyebrow">Shortcuts</p><h2>Quick actions</h2></div></div><div class="quick-action-grid"><a href="#live-operations"><span>+</span><b>Review bookings</b><small>Dispatch board</small></a><a href="#fleet"><span>↗</span><b>Manage fleet</b><small>Vehicles &amp; drivers</small></a><a href="#activity"><span>◉</span><b>View activity</b><small>Latest changes</small></a></div></section>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
