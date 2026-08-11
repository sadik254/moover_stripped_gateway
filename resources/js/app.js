import './bootstrap';

const authTokenKey = 'moover_admin_token';

const request = async (url, token, options = {}) => {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
            ...(options.headers || {}),
        },
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw Object.assign(new Error(payload.message || 'Unable to complete this request.'), {
            status: response.status,
            payload,
        });
    }

    return payload;
};

const initials = (name = '') => name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase() || '--';
const setText = (id, value) => { const element = document.getElementById(id); if (element) element.textContent = value; };
const escapeHtml = (value = '') => String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[character]);
const relativeTime = (value) => {
    if (!value) return 'Recently';
    const seconds = Math.max(0, Math.round((Date.now() - new Date(value).getTime()) / 1000));
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
};
const showAlert = (id, message) => { const alert = document.getElementById(id); if (alert) { alert.textContent = message; alert.hidden = false; } };

const bootLogin = () => {
    const page = document.body;
    const form = document.getElementById('admin-login-form');
    if (localStorage.getItem(authTokenKey)) window.location.replace(page.dataset.dashboardUrl);

    document.querySelector('.password-toggle')?.addEventListener('click', (event) => {
        const input = document.getElementById('password');
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        event.currentTarget.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
        event.currentTarget.setAttribute('aria-pressed', String(reveal));
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = document.getElementById('login-submit');
        const error = document.getElementById('login-error');
        error.hidden = true;
        button.disabled = true;
        button.querySelector('span').textContent = 'Signing in...';

        try {
            const payload = await request(`${page.dataset.apiBase}/user/login`, null, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: form.email.value.trim(), password: form.password.value }),
            });
            localStorage.setItem(authTokenKey, payload.token);
            window.location.assign(page.dataset.dashboardUrl);
        } catch (errorResponse) {
            showAlert('login-error', errorResponse.payload?.errors?.email?.[0] || errorResponse.message);
            button.disabled = false;
            button.querySelector('span').textContent = 'Sign in';
        }
    });
};

const renderFeed = (bookings) => {
    const body = document.getElementById('live-feed-body');
    if (!body) return;
    if (!bookings.length) { body.innerHTML = '<tr><td colspan="5" class="empty-state">No bookings currently need dispatch attention.</td></tr>'; return; }
    body.innerHTML = bookings.map((booking) => {
        const status = String(booking.status || 'pending');
        const statusClass = ['pending', 'cancelled'].includes(status) ? ` status-pill--${status}` : '';
        return `<tr><td><strong>#${escapeHtml(booking.id)}</strong><small>${escapeHtml(booking.service_type?.replaceAll('_', ' ') || 'Booking')}</small></td><td><strong>${escapeHtml(booking.customer?.name || booking.name || 'Unassigned')}</strong><small>${escapeHtml(booking.customer?.phone || booking.phone || '')}</small></td><td><strong>${escapeHtml(booking.pickup_address || 'Pickup to be confirmed')}</strong><span class="status-pill${statusClass}">${escapeHtml(status.replaceAll('_', ' '))}</span></td><td><strong>${escapeHtml(booking.driver?.name || 'Not assigned')}</strong><small>${escapeHtml(booking.vehicle?.name || 'Vehicle pending')}</small></td><td><strong>${escapeHtml(booking.pickup_time ? new Date(booking.pickup_time).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : 'TBD')}</strong><small>${escapeHtml(booking.pickup_time ? new Date(booking.pickup_time).toLocaleDateString([], { month: 'short', day: 'numeric' }) : '')}</small></td></tr>`;
    }).join('');
};

const renderAvailability = (availability) => {
    const element = document.getElementById('availability-list');
    if (!element) return;
    const groups = (availability?.vehicles || []).reduce((carry, vehicle) => {
        const name = vehicle.vehicle_class?.name || vehicle.category || 'Unclassified';
        carry[name] = (carry[name] || 0) + 1;
        return carry;
    }, {});
    const entries = Object.entries(groups);
    element.innerHTML = entries.length ? entries.slice(0, 4).map(([name, count]) => `<div class="availability-row"><div><strong>${escapeHtml(name)}</strong><small>Available in the current dispatch window</small></div><span class="availability-count">${count}</span></div>`).join('') : '<p class="empty-state">No available vehicles in the current window.</p>';
};

const renderActivity = (activities) => {
    const element = document.getElementById('activity-list');
    if (!element) return;
    element.innerHTML = activities.length ? activities.slice(0, 4).map((activity) => `<li><strong>${escapeHtml(activity.description || activity.action || 'Booking updated')}</strong><small>${escapeHtml(relativeTime(activity.created_at))}</small></li>`).join('') : '<li class="empty-state">No recent activity to show.</li>';
};

const bootDashboard = async () => {
    const page = document.body;
    const token = localStorage.getItem(authTokenKey);
    const redirectToLogin = () => { localStorage.removeItem(authTokenKey); window.location.replace(page.dataset.loginUrl); };
    if (!token) { redirectToLogin(); return; }

    setText('dashboard-date', new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric' }).format(new Date()));
    document.getElementById('logout-button')?.addEventListener('click', async () => {
        try { await request(`${page.dataset.apiBase}/user/logout`, token, { method: 'POST' }); } catch (_) { /* Remove local credentials even if the token already expired. */ }
        redirectToLogin();
    });

    try {
        const profile = await request(`${page.dataset.apiBase}/user`, token);
        if (!['admin', 'dispatcher'].includes(profile.user_type)) throw Object.assign(new Error('This portal is for admin and dispatcher accounts.'), { status: 403 });

        const displayName = profile.name || 'there';
        const userInitials = initials(displayName);
        setText('dashboard-user-name', displayName.split(' ')[0]);
        setText('sidebar-user-name', displayName);
        setText('sidebar-initials', userInitials);
        setText('header-initials', userInitials);

        const results = await Promise.allSettled([
            request(`${page.dataset.apiBase}/bookings/dashboard-summary`, token),
            request(`${page.dataset.apiBase}/drivers/dashboard-summary`, token),
            request(`${page.dataset.apiBase}/customers/dashboard-summary`, token),
            request(`${page.dataset.apiBase}/bookings/live-operations-feed`, token),
            request(`${page.dataset.apiBase}/bookings/vehicle-availability`, token),
            request(`${page.dataset.apiBase}/bookings/recent-activity`, token),
        ]);
        const data = results.map((result) => result.status === 'fulfilled' ? result.value.data : null);
        const [bookingSummary, driverSummary, customerSummary, feed, availability, activity] = data;
        const counts = bookingSummary?.today_counts || {};
        const bookingCount = Number(counts.pending || 0) + Number(counts.confirmed || 0) + Number(counts.in_progress || 0);
        setText('metric-bookings', bookingCount);
        setText('metric-bookings-note', `${counts.confirmed || 0} confirmed · ${counts.in_progress || 0} active · ${counts.pending || 0} pending`);
        setText('metric-completed', bookingSummary?.completed_today ?? 0);
        setText('metric-drivers', bookingSummary?.drivers_available_for_dispatch ?? 0);
        setText('metric-drivers-note', `${driverSummary?.online_drivers ?? 0} currently online`);
        setText('metric-customers', customerSummary?.total_customers ?? 0);
        setText('overview-note', `${bookingSummary?.total_trips_lifetime ?? 0} confirmed trips in your operation`);
        renderFeed(Array.isArray(feed) ? feed : []);
        renderAvailability(availability);
        renderActivity(activity?.data || []);
        if (results.some((result) => result.status === 'rejected')) showAlert('dashboard-error', 'Some dashboard information could not be loaded. You can still continue working.');
    } catch (errorResponse) {
        if ([401, 403].includes(errorResponse.status)) { redirectToLogin(); return; }
        showAlert('dashboard-error', errorResponse.message || 'Unable to load the dashboard.');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    if (document.body.dataset.page === 'admin-login') bootLogin();
    if (document.body.dataset.page === 'admin-dashboard') bootDashboard();
});
