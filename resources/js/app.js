import './bootstrap';

const authTokenKey = 'moover_admin_token';
const state = { token: '', profile: null, view: 'overview', resources: {}, lookups: {}, editing: null, bookingQuote: null };

const request = async (url, token, options = {}) => {
    const response = await fetch(url, {
        ...options,
        headers: { Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}), ...(options.headers || {}) },
    });
    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('json') ? await response.json().catch(() => ({})) : await response.text();
    if (!response.ok) throw Object.assign(new Error(payload?.message || 'Unable to complete this request.'), { status: response.status, payload });
    return payload;
};

const jsonOptions = (body, method = 'POST') => ({ method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
const apiData = (payload) => payload?.data?.data || payload?.data || [];
const esc = (value = '') => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[character]);
const initials = (name = '') => name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase() || '--';
const money = (value, currency = 'USD') => `${String(currency || 'USD').toUpperCase()} ${Number(value || 0).toFixed(2)}`;
const human = (value = '') => String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const dateTime = (value) => value ? new Date(value).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : '—';
const inputValue = (definition, value) => definition.type === 'datetime-local' && value ? String(value).replace(' ', 'T').slice(0, 16) : value;
const relativeTime = (value) => {
    if (!value) return 'Recently';
    const seconds = Math.max(0, Math.round((Date.now() - new Date(value).getTime()) / 1000));
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
};
const setText = (id, value) => { const element = document.getElementById(id); if (element) element.textContent = value; };
const notify = (message, error = false) => {
    const success = document.getElementById('dashboard-success');
    const failure = document.getElementById('dashboard-error');
    [success, failure].forEach((element) => { if (element) element.hidden = true; });
    const target = error ? failure : success;
    if (target) { target.textContent = message; target.hidden = false; setTimeout(() => { target.hidden = true; }, 5000); }
};

const field = (name, label, type = 'text', options = {}) => ({ name, label, type, ...options });
const formDefinitions = {
    customer: [field('name', 'Full name', 'text', { required: true }), field('email', 'Email', 'email', { required: true }), field('phone', 'Phone', 'text', { required: true }), field('customer_company', 'Company'), field('customer_type', 'Customer type', 'select', { choices: ['individual', 'corporate'] }), field('preferred_service_level', 'Service level'), field('dispatch_note', 'Dispatch note', 'textarea')],
    driver: [field('name', 'Full name', 'text', { required: true }), field('email', 'Email', 'email', { required: true }), field('phone', 'Phone', 'text', { required: true }), field('vehicle_id', 'Fleet vehicle', 'select', { lookup: 'vehicles' }), field('license_number', 'License number', 'text', { required: true }), field('license_expiry', 'License expiry', 'date'), field('status', 'Status', 'select', { choices: ['online', 'offline', 'pending', 'inactive'] }), field('available', 'Available', 'checkbox')],
    'vehicle-class': [field('name', 'Class name', 'text', { required: true }), field('description', 'Description', 'textarea'), field('capacity', 'Passenger capacity', 'number', { required: true, min: 1 }), field('luggage', 'Luggage capacity', 'number', { required: true, min: 0 }), field('hourly_rate', 'Hourly rate', 'number', { step: '0.01' }), field('peak_hourly_rate', 'Peak hourly rate', 'number', { step: '0.01' }), field('point_to_point_rate', 'Short point-to-point rate', 'number', { step: '0.01' }), field('per_km_rate', 'Long-distance rate per km', 'number', { step: '0.01' }), field('extra_stop_eligible', 'Apply extra-stop fee', 'checkbox')],
    vehicle: [field('vehicle_class_id', 'Vehicle class', 'select', { lookup: 'vehicle-classes', required: true }), field('name', 'Vehicle name', 'text', { required: true }), field('category', 'Category'), field('plate_number', 'Plate number'), field('color', 'Color'), field('model', 'Model'), field('status', 'Status', 'select', { choices: ['active', 'inactive', 'maintenance'] })],
    affiliate: [field('name', 'Affiliate name', 'text', { required: true }), field('email', 'Email', 'email', { required: true }), field('phone', 'Phone'), field('address', 'Address', 'textarea'), field('status', 'Status', 'select', { choices: ['active', 'inactive'] }), field('payout_mode', 'Payout mode', 'select', { choices: ['percentage'] }), field('affiliate_payout_percent', 'Affiliate payout %', 'number', { step: '0.01' }), field('platform_commission_percent', 'Platform commission %', 'number', { step: '0.01' }), field('stripe_connect_account_id', 'Stripe Connect account'), field('payout_currency', 'Currency', 'text', { value: 'usd' })],
    booking: [field('name', 'Passenger name', 'text', { required: true }), field('email', 'Email', 'email', { required: true }), field('phone', 'Phone'), field('service_type', 'Service type', 'select', { choices: ['point_to_point', 'hourly', 'airport', 'custom'], required: true }), field('airport_id', 'Airport (airport bookings)', 'select', { lookup: 'airports' }), field('pickup_address', 'Pickup address', 'textarea', { required: true }), field('stops_text', 'Middle stops (one address per line)', 'textarea'), field('dropoff_address', 'Drop-off address', 'textarea'), field('pickup_time', 'Pickup time', 'datetime-local', { required: true }), field('dropoff_time', 'Drop-off time', 'datetime-local'), field('passengers', 'Passengers', 'number', { required: true, min: 1, value: 1 }), field('child_seats', 'Children / child seats', 'number', { min: 0, value: 0 }), field('bags', 'Bags', 'number', { min: 0, value: 0 }), field('distance_km', 'Distance (km)', 'number', { step: '0.01' }), field('hours', 'Hours', 'number', { step: '0.25' }), field('flight_number', 'Flight number'), field('airlines', 'Airline'), field('notes', 'Notes', 'textarea')],
    finalization: [field('extras_price', 'Extras', 'number', { min: 0, step: '0.01' }), field('extra_stops', 'Extra stops', 'number', { min: 0 }), field('waiting_minutes', 'Waiting minutes', 'number', { min: 0, step: '0.01' }), field('tolls', 'Tolls', 'number', { min: 0, step: '0.01' }), field('parking', 'Parking', 'number', { min: 0, step: '0.01' }), field('others', 'Other charges', 'number', { min: 0, step: '0.01' }), field('airport_fees', 'Airport fees', 'number', { min: 0, step: '0.01' }), field('congestion_charge', 'Congestion charge', 'number', { min: 0, step: '0.01' })],
    airport: [field('code', 'Airport code', 'text', { required: true }), field('name', 'Airport name', 'text', { required: true }), field('active', 'Active', 'checkbox')],
};

const bookingEditFields = [
    ...formDefinitions.booking,
    field('vehicle_class_id', 'Vehicle class', 'select', { lookup: 'vehicle-classes', required: true }),
    field('driver_id', 'Driver', 'select', { lookup: 'drivers' }),
    field('vehicle_id', 'Dispatch vehicle (optional)', 'select', { lookup: 'vehicles' }),
    field('status', 'Status', 'select', { choices: ['pending', 'confirmed', 'assigned', 'picking_up', 'on_route', 'completed', 'cancelled', 'done'] }),
];

const resourceConfig = {
    bookings: { endpoint: '/bookings', columns: ['ID', 'Passenger', 'Service', 'Vehicle class', 'Pickup', 'Total', 'Status', 'Actions'] },
    customers: { endpoint: '/customers?per_page=100', columns: ['ID', 'Customer', 'Phone', 'Type', 'Company', 'Actions'] },
    drivers: { endpoint: '/drivers?per_page=100', columns: ['ID', 'Driver', 'Phone', 'Vehicle', 'Availability', 'Status', 'Actions'] },
    'vehicle-classes': { endpoint: '/vehicle-classes', columns: ['ID', 'Class', 'Capacity', 'Hourly', 'Peak', 'Short trip', 'Per km', 'Airport rates', 'Actions'] },
    vehicles: { endpoint: '/vehicles', columns: ['ID', 'Vehicle', 'Class', 'Plate', 'Status', 'Actions'] },
    affiliates: { endpoint: '/affiliates?per_page=100', columns: ['ID', 'Affiliate', 'Phone', 'Payout', 'Stripe', 'Status'] },
    airports: { endpoint: '/airports', columns: ['ID', 'Code', 'Airport', 'Status', 'Actions'] },
};

const resourceType = (view) => view === 'vehicle-classes' ? 'vehicle-class' : view.replace(/s$/, '');
const endpointFor = (type, id = null) => {
    const map = { customer: '/customers', driver: '/drivers', 'vehicle-class': '/vehicle-classes', vehicle: '/vehicles', affiliate: '/affiliates', airport: '/airports' };
    if (!id) return map[type];
    if (type === 'vehicle-class') return `/vehicle-classes/update/${id}`;
    return `/${type}s/update/${id}`;
};

const renderFields = (container, definitions, values = {}) => {
    container.innerHTML = definitions.map((definition) => {
        const value = inputValue(definition, values[definition.name] ?? definition.value ?? '');
        const required = definition.required ? 'required' : '';
        const wide = definition.type === 'textarea' ? ' form-field--wide' : '';
        let input;
        if (definition.type === 'select') {
            const source = definition.lookup ? (state.lookups[definition.lookup] || []) : (definition.choices || []).map((choice) => ({ id: choice, name: human(choice) }));
            input = `<select name="${definition.name}" ${required}><option value="">Select ${esc(definition.label.toLowerCase())}</option>${source.map((item) => `<option value="${esc(item.id)}" ${String(value) === String(item.id) ? 'selected' : ''}>${esc(item.name)}</option>`).join('')}</select>`;
        } else if (definition.type === 'textarea') {
            input = `<textarea name="${definition.name}" rows="3" ${required}>${esc(value)}</textarea>`;
        } else if (definition.type === 'checkbox') {
            input = `<label class="check-control"><input name="${definition.name}" type="checkbox" value="1" ${value === true || value === 1 || value === '1' ? 'checked' : ''}><span>Yes</span></label>`;
        } else {
            input = `<input name="${definition.name}" type="${definition.type}" value="${esc(value)}" ${required} ${definition.min !== undefined ? `min="${definition.min}"` : ''} ${definition.step ? `step="${definition.step}"` : ''}>`;
        }
        return `<label class="form-field${wide}"><span>${esc(definition.label)}${definition.required ? ' *' : ''}</span>${input}</label>`;
    }).join('');
};

const formPayload = (form, definitions) => {
    const data = new FormData(form);
    return Object.fromEntries(definitions.map((definition) => {
        let value = definition.type === 'checkbox' ? data.has(definition.name) : data.get(definition.name);
        if (value === '') value = null;
        if (definition.type === 'number' && value !== null) value = Number(value);
        return [definition.name, value];
    }));
};

const vehicleClassFields = () => [
    ...formDefinitions['vehicle-class'],
    ...(state.lookups.airports || []).map((airport) => field(`airport_rate_${airport.id}`, `${airport.code} flat rate`, 'number', { min: 0, step: '0.01' })),
];

const vehicleClassValues = (record = {}) => ({
    ...record,
    ...(record.airport_rates || []).reduce((values, rate) => ({ ...values, [`airport_rate_${rate.airport_id}`]: rate.rate }), {}),
});

const renderRows = (view, rows) => {
    const body = document.querySelector(`[data-body="${view}"]`);
    const head = document.querySelector(`[data-head="${view}"]`);
    head.innerHTML = `<tr>${resourceConfig[view].columns.map((column) => `<th>${column}</th>`).join('')}</tr>`;
    const renderers = {
        bookings: (row) => `<td>#${row.id}</td><td><strong>${esc(row.name || row.customer?.name || 'Guest')}</strong><small>${esc(row.email || row.customer?.email || '')}</small></td><td>${esc(human(row.service_type))}</td><td>${esc(row.vehicle_class?.name || '—')}</td><td>${esc(dateTime(row.pickup_time))}</td><td>${esc(money(row.final_price || row.total_price))}</td><td><span class="status-pill status-pill--${esc(row.status)}">${esc(human(row.status))}</span></td><td><div class="row-actions"><button data-edit="booking" data-id="${row.id}">Edit</button><button data-booking-status="${row.id}">Status</button>${row.status === 'done' ? `<button data-finalize="${row.id}">Finalize</button>` : ''}${row.status === 'completed' && ['authorized', 'requires_capture'].includes(row.payment_status) ? `<button data-capture="${row.id}">Capture</button>` : ''}</div></td>`,
        customers: (row) => `<td>#${row.id}</td><td><strong>${esc(row.name)}</strong><small>${esc(row.email)}</small></td><td>${esc(row.phone || '—')}</td><td>${esc(human(row.customer_type || 'individual'))}</td><td>${esc(row.customer_company || '—')}</td><td><button data-edit="customer" data-id="${row.id}">Edit</button></td>`,
        drivers: (row) => `<td>#${row.id}</td><td><strong>${esc(row.name)}</strong><small>${esc(row.email)}</small></td><td>${esc(row.phone || '—')}</td><td>${esc(row.vehicle?.name || 'Unassigned')}</td><td>${row.available ? '<span class="status-pill">Available</span>' : '<span class="status-pill status-pill--cancelled">Busy</span>'}</td><td>${esc(human(row.status || 'active'))}</td><td><button data-edit="driver" data-id="${row.id}">Edit</button></td>`,
        'vehicle-classes': (row) => `<td>#${row.id}</td><td><strong>${esc(row.name)}</strong><small>${esc(row.description || '')}</small></td><td>${esc(row.capacity)} people · ${esc(row.luggage)} bags</td><td>${esc(money(row.hourly_rate))}</td><td>${row.peak_hourly_rate === null ? '—' : esc(money(row.peak_hourly_rate))}</td><td>${row.point_to_point_rate === null ? '—' : esc(money(row.point_to_point_rate))}</td><td>${esc(money(row.per_km_rate))}</td><td>${esc((row.airport_rates || []).length)}</td><td><button data-edit="vehicle-class" data-id="${row.id}">Edit</button></td>`,
        vehicles: (row) => `<td>#${row.id}</td><td><strong>${esc(row.name)}</strong><small>${esc([row.color, row.model].filter(Boolean).join(' · '))}</small></td><td>${esc(row.vehicle_class?.name || '—')}</td><td>${esc(row.plate_number || '—')}</td><td>${esc(human(row.status || 'active'))}</td><td><button data-edit="vehicle" data-id="${row.id}">Edit</button></td>`,
        affiliates: (row) => `<td>#${row.id}</td><td><strong>${esc(row.name)}</strong><small>${esc(row.email)}</small></td><td>${esc(row.phone)}</td><td>${esc(row.affiliate_payout_percent || 0)}%</td><td>${esc(row.stripe_connect_account_id || 'Not connected')}</td><td>${esc(human(row.status || 'active'))}</td>`,
        airports: (row) => `<td>#${row.id}</td><td><strong>${esc(row.code)}</strong></td><td>${esc(row.name)}</td><td><span class="status-pill">${row.active ? 'Active' : 'Inactive'}</span></td><td><button data-edit="airport" data-id="${row.id}">Edit</button></td>`,
    };
    body.innerHTML = rows.length ? rows.map((row) => `<tr>${renderers[view](row)}</tr>`).join('') : `<tr><td colspan="${resourceConfig[view].columns.length}" class="empty-state">No records found.</td></tr>`;
};

const loadResource = async (view) => {
    const payload = await request(`${document.body.dataset.apiBase}${resourceConfig[view].endpoint}`, state.token);
    state.resources[view] = apiData(payload);
    renderRows(view, state.resources[view]);
};

const filteredRows = (view) => {
    const query = (document.querySelector(`[data-search="${view}"]`)?.value || '').toLowerCase();
    const status = document.querySelector(`[data-filter="${view}"]`)?.value || '';
    return (state.resources[view] || []).filter((row) => JSON.stringify(row).toLowerCase().includes(query) && (!status || row.status === status));
};

const renderFeed = (bookings) => {
    const body = document.getElementById('live-feed-body');
    body.innerHTML = bookings.length ? bookings.map((booking) => {
        const status = String(booking.status || 'pending');
        return `<tr><td><strong>#${booking.id}</strong><small>${esc(human(booking.service_type))}</small></td><td><strong>${esc(booking.customer?.name || booking.name || 'Guest')}</strong><small>${esc(booking.customer?.phone || booking.phone || '')}</small></td><td><strong>${esc(booking.pickup_address)}</strong><span class="status-pill status-pill--${esc(status)}">${esc(human(status))}</span></td><td><strong>${esc(booking.driver?.name || 'Not assigned')}</strong><small>${esc(booking.vehicle_class?.name || 'Class pending')}</small></td><td>${esc(dateTime(booking.pickup_time))}</td></tr>`;
    }).join('') : '<tr><td colspan="5" class="empty-state">No bookings currently need attention.</td></tr>';
};

const loadOverview = async () => {
    const urls = ['/bookings/dashboard-summary', '/drivers/dashboard-summary', '/customers/dashboard-summary', '/bookings/live-operations-feed', '/vehicle-classes', '/bookings/recent-activity'];
    const results = await Promise.allSettled(urls.map((url) => request(`${document.body.dataset.apiBase}${url}`, state.token)));
    const values = results.map((result) => result.status === 'fulfilled' ? result.value.data : null);
    const [bookingSummary, driverSummary, customerSummary, feed, vehicleClasses, activity] = values;
    const counts = bookingSummary?.today_counts || {};
    setText('metric-bookings', Number(counts.pending || 0) + Number(counts.confirmed || 0) + Number(counts.in_progress || 0));
    setText('metric-bookings-note', `${counts.confirmed || 0} confirmed · ${counts.in_progress || 0} active · ${counts.pending || 0} pending`);
    setText('metric-completed', money(bookingSummary?.earnings_today));
    setText('metric-drivers', bookingSummary?.drivers_available_for_dispatch ?? 0);
    setText('metric-drivers-note', `${driverSummary?.online_drivers ?? 0} currently online`);
    setText('metric-customers', customerSummary?.total_customers ?? 0);
    setText('overview-note', `${bookingSummary?.total_trips_lifetime ?? 0} lifetime trips`);
    renderFeed(Array.isArray(feed) ? feed : []);
    document.getElementById('availability-list').innerHTML = Array.isArray(vehicleClasses) && vehicleClasses.length ? vehicleClasses.slice(0, 5).map((item) => `<div class="availability-row"><div><strong>${esc(item.name)}</strong><small>${esc(item.capacity)} passengers · ${esc(item.luggage)} bags</small></div><span class="availability-count">${esc(item.capacity)}</span></div>`).join('') : '<p class="empty-state">No vehicle classes configured.</p>';
    document.getElementById('activity-list').innerHTML = activity?.data?.length ? activity.data.slice(0, 5).map((item) => `<li><strong>${esc(item.description || item.action)}</strong><small>${esc(relativeTime(item.created_at))}</small></li>`).join('') : '<li class="empty-state">No recent activity.</li>';
    if (results.some((result) => result.status === 'rejected')) notify('Some dashboard information could not be loaded.', true);
};

const loadLookups = async () => {
    const entries = [['vehicle-classes', '/vehicle-classes'], ['vehicles', '/vehicles'], ['drivers', '/drivers?per_page=100'], ['affiliates', '/affiliates?per_page=100'], ['airports', '/airports']];
    await Promise.all(entries.map(async ([key, endpoint]) => {
        try { state.lookups[key] = apiData(await request(`${document.body.dataset.apiBase}${endpoint}`, state.token)); } catch (_) { state.lookups[key] = []; }
    }));
};

const showView = async (view) => {
    state.view = view;
    document.querySelectorAll('.admin-view').forEach((element) => element.classList.toggle('is-active', element.dataset.adminView === view));
    document.querySelectorAll('.nav-item').forEach((element) => element.classList.toggle('nav-item--active', element.dataset.view === view));
    const title = view === 'overview' ? `Welcome back, ${state.profile?.name?.split(' ')[0] || 'there'}.` : human(view);
    document.getElementById('page-title').textContent = title;
    location.hash = view;
    try {
        if (view === 'overview') await loadOverview();
        else if (resourceConfig[view]) await loadResource(view);
        else if (view === 'finance') await loadFinance();
        else if (view === 'settings') await loadSettings();
    } catch (error) { notify(error.message, true); }
};

const openDialog = async (type, record = null) => {
    await loadLookups();
    state.editing = record ? { type, id: record.id } : { type, id: null };
    state.bookingQuote = null;
    setText('dialog-eyebrow', record ? 'Update record' : 'Create record');
    setText('dialog-title', `${record ? 'Edit' : 'New'} ${human(type)}`);
    setText('dialog-submit', type === 'booking' ? 'Get vehicle-class quote' : (record ? 'Save changes' : 'Create'));
    const fields = document.getElementById('resource-form-fields');
    const definitions = type === 'vehicle-class' ? vehicleClassFields() : (type === 'booking' && record ? bookingEditFields : formDefinitions[type]);
    const values = type === 'vehicle-class'
        ? vehicleClassValues(record || {})
        : (type === 'booking' ? { ...(record || {}), stops_text: (record?.stops || []).map((stop) => stop.address).join('\n') } : (record || {}));
    renderFields(fields, definitions, values);
    document.getElementById('booking-quote-options').hidden = true;
    document.getElementById('dialog-error').hidden = true;
    document.getElementById('resource-dialog').showModal();
};

const showBookingQuote = (options) => {
    const container = document.getElementById('booking-quote-options');
    container.hidden = false;
    container.innerHTML = `<p class="eyebrow">Select a vehicle class</p>${options.map((option, index) => `<label class="quote-card"><input type="radio" name="quoted_vehicle_class_id" value="${option.vehicle_class_id}" ${index === 0 ? 'checked' : ''}><span><strong>${esc(option.name)}</strong><small>${esc(option.capacity)} passengers · ${esc(option.luggage)} bags</small><small>${esc(human(option.pricing_method))} · fare ${esc(money(option.total_price))}</small></span><b><small>Temporary hold</small>${esc(money(option.calculation?.authorization_amount))}</b></label>`).join('')}`;
    setText('dialog-submit', 'Create booking');
};

const submitResourceForm = async (event) => {
    event.preventDefault();
    const { type, id } = state.editing;
    const definitions = type === 'vehicle-class' ? vehicleClassFields() : (type === 'booking' && id ? bookingEditFields : formDefinitions[type]);
    const payload = formPayload(event.currentTarget, definitions);
    Object.keys(payload).forEach((key) => payload[key] === null && delete payload[key]);
    if (type === 'vehicle-class') {
        payload.airport_rates = (state.lookups.airports || []).filter((airport) => payload[`airport_rate_${airport.id}`] !== undefined).map((airport) => ({ airport_id: airport.id, service_zone: 'Manhattan', rate: payload[`airport_rate_${airport.id}`] }));
        (state.lookups.airports || []).forEach((airport) => delete payload[`airport_rate_${airport.id}`]);
    }
    if (type === 'booking') {
        payload.stops = String(payload.stops_text || '').split('\n').map((address) => address.trim()).filter(Boolean).map((address) => ({ address }));
        delete payload.stops_text;
    }
    const errorBox = document.getElementById('dialog-error');
    errorBox.hidden = true;
    try {
        if (type === 'finalization') {
            const result = await request(`${document.body.dataset.apiBase}/bookings/${id}/finalize`, state.token, jsonOptions(payload));
            document.getElementById('resource-dialog').close();
            notify(`Booking finalized at ${money(result.data?.final_price)}. It is ready to capture.`);
            await showView('bookings');
            return;
        }
        if (type === 'booking') {
            if (id) {
                await request(`${document.body.dataset.apiBase}/bookings/update/${id}`, state.token, jsonOptions(payload));
                document.getElementById('resource-dialog').close();
                notify('Booking updated successfully.');
                await showView('bookings');
                return;
            }
            const selected = document.querySelector('[name="quoted_vehicle_class_id"]:checked');
            if (!selected) {
                const quote = await request(`${document.body.dataset.apiBase}/bookings`, state.token, jsonOptions(payload));
                state.bookingQuote = payload;
                showBookingQuote(quote.data?.vehicle_class_options || []);
                return;
            }
            await request(`${document.body.dataset.apiBase}/bookings`, state.token, jsonOptions({ ...state.bookingQuote, vehicle_class_id: Number(selected.value) }));
        } else {
            await request(`${document.body.dataset.apiBase}${endpointFor(type, id)}`, state.token, jsonOptions(payload));
        }
        document.getElementById('resource-dialog').close();
        notify(`${human(type)} ${id ? 'updated' : 'created'} successfully.`);
        await loadLookups();
        await showView(type === 'vehicle-class' ? 'vehicle-classes' : `${type}s`);
    } catch (error) {
        const validation = error.payload?.errors ? Object.values(error.payload.errors).flat().join(' ') : error.message;
        errorBox.textContent = validation;
        errorBox.hidden = false;
    }
};

const loadFinance = async () => {
    const [settlements, disbursements] = await Promise.all([
        request(`${document.body.dataset.apiBase}/affiliate-settlements?per_page=100`, state.token),
        request(`${document.body.dataset.apiBase}/affiliate-disbursements?per_page=100`, state.token),
    ]);
    const settlementRows = apiData(settlements);
    const disbursementRows = apiData(disbursements);
    document.getElementById('settlements-body').innerHTML = settlementRows.length ? settlementRows.map((row) => `<tr><td>#${row.id}</td><td>#${row.booking_id}</td><td>${esc(row.affiliate?.name || row.affiliate_id)}</td><td>${esc(money(row.gross_amount, row.currency))}</td><td>${esc(money(row.affiliate_amount, row.currency))}</td><td><span class="status-pill">${esc(human(row.status))}</span></td><td>${['ready', 'failed', 'on_hold'].includes(row.status) ? `<button data-disburse="${row.id}">Disburse</button>` : ''}</td></tr>`).join('') : '<tr><td colspan="7" class="empty-state">No settlements found.</td></tr>';
    document.getElementById('disbursements-body').innerHTML = disbursementRows.length ? disbursementRows.map((row) => `<tr><td>#${row.id}</td><td>${esc(row.affiliate?.name || row.affiliate_id)}</td><td>${esc(money(row.amount, row.currency))}</td><td>${esc(String(row.currency || 'usd').toUpperCase())}</td><td>${esc(human(row.status))}</td><td>${esc(dateTime(row.created_at))}</td></tr>`).join('') : '<tr><td colspan="6" class="empty-state">No disbursements found.</td></tr>';
};

const companyFields = [field('name', 'Company name'), field('email', 'Email', 'email'), field('phone', 'Phone'), field('address', 'Address', 'textarea'), field('timezone', 'Timezone'), field('url', 'Website')];
const systemFields = [field('platform_name', 'Platform name'), field('currency', 'Currency'), field('base_price_flat', 'Base price', 'number', { step: '0.01' }), field('tax_rate', 'Tax rate %', 'number', { step: '0.01' }), field('gratuity_percentage', 'Gratuity %', 'number', { step: '0.01' }), field('rate_buffer', 'Authorization buffer %', 'number', { step: '0.01' }), field('surge_rate', 'Surge rate %', 'number', { step: '0.01' }), field('cancellation_fee', 'Cancellation fee', 'number', { step: '0.01' }), field('wait_time_rate', 'Wait-time hourly rate', 'number', { step: '0.01' }), field('waiting_grace_minutes', 'Waiting grace minutes', 'number', { min: 0 }), field('extra_stop_fee', 'Extra-stop fee', 'number', { step: '0.01' }), field('extra_stop_minutes', 'Minutes included per stop', 'number', { min: 0 }), field('short_distance_limit_km', 'Short-trip limit (km)', 'number', { step: '0.01' }), field('distance_rate_start_km', 'Distance-rate threshold (km)', 'number', { step: '0.01' }), field('point_to_point_minimum_hours', 'Point-to-point minimum hours', 'number', { step: '0.25' }), field('peak_days', 'Peak days (comma-separated)'), field('primary_brand_color', 'Primary color'), field('secondary_brand_color', 'Secondary color')];

const loadSettings = async () => {
    const [companyPayload, systemPayload] = await Promise.all([request(`${document.body.dataset.apiBase}/company`, state.token), request(`${document.body.dataset.apiBase}/system-config`, state.token)]);
    const company = companyPayload.data || companyPayload;
    const system = systemPayload.data?.config || systemPayload.data || systemPayload;
    if (Array.isArray(system.peak_days)) system.peak_days = system.peak_days.join(', ');
    renderFields(document.getElementById('company-settings-fields'), companyFields, company || {});
    renderFields(document.getElementById('system-settings-fields'), systemFields, system || {});
};

const submitSettings = async (form, endpoint, definitions, label) => {
    const payload = formPayload(form, definitions);
    Object.keys(payload).forEach((key) => payload[key] === null && delete payload[key]);
    if (endpoint === '/system-config/update' && typeof payload.peak_days === 'string') payload.peak_days = payload.peak_days.split(',').map((day) => day.trim().toLowerCase()).filter(Boolean);
    await request(`${document.body.dataset.apiBase}${endpoint}`, state.token, jsonOptions(payload));
    notify(`${label} saved successfully.`);
};

const bootLogin = () => {
    const page = document.body;
    const form = document.getElementById('admin-login-form');
    if (localStorage.getItem(authTokenKey)) window.location.replace(page.dataset.dashboardUrl);
    document.querySelector('.password-toggle')?.addEventListener('click', (event) => { const input = document.getElementById('password'); input.type = input.type === 'password' ? 'text' : 'password'; event.currentTarget.setAttribute('aria-pressed', String(input.type === 'text')); });
    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = document.getElementById('login-submit');
        button.disabled = true;
        try {
            const payload = await request(`${page.dataset.apiBase}/user/login`, null, jsonOptions({ email: form.email.value.trim(), password: form.password.value }));
            localStorage.setItem(authTokenKey, payload.token);
            window.location.assign(page.dataset.dashboardUrl);
        } catch (error) { const box = document.getElementById('login-error'); box.textContent = error.payload?.errors?.email?.[0] || error.message; box.hidden = false; button.disabled = false; }
    });
};

const bootDashboard = async () => {
    const page = document.body;
    state.token = localStorage.getItem(authTokenKey) || '';
    const logout = () => { localStorage.removeItem(authTokenKey); window.location.replace(page.dataset.loginUrl); };
    if (!state.token) return logout();
    try {
        state.profile = await request(`${page.dataset.apiBase}/user`, state.token);
        if (!['admin', 'dispatcher'].includes(state.profile.user_type)) return logout();
    } catch (_) { return logout(); }

    const displayName = state.profile.name || 'Administrator';
    setText('dashboard-user-name', displayName.split(' ')[0]);
    setText('sidebar-user-name', displayName);
    setText('sidebar-initials', initials(displayName));
    setText('header-initials', initials(displayName));
    setText('dashboard-date', new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric' }).format(new Date()));
    await loadLookups();

    document.getElementById('admin-navigation').addEventListener('click', (event) => { const link = event.target.closest('[data-view]'); if (link) { event.preventDefault(); showView(link.dataset.view); } });
    document.addEventListener('click', async (event) => {
        const go = event.target.closest('[data-go]'); if (go) return showView(go.dataset.go);
        const create = event.target.closest('[data-create]'); if (create) return openDialog(create.dataset.create);
        const edit = event.target.closest('[data-edit]'); if (edit) { const view = edit.dataset.edit === 'vehicle-class' ? 'vehicle-classes' : `${edit.dataset.edit}s`; const record = (state.resources[view] || []).find((item) => String(item.id) === edit.dataset.id); return openDialog(edit.dataset.edit, record); }
        const finalize = event.target.closest('[data-finalize]'); if (finalize) { const record = (state.resources.bookings || []).find((item) => String(item.id) === finalize.dataset.finalize); return openDialog('finalization', record); }
        const capture = event.target.closest('[data-capture]'); if (capture && confirm('Capture the finalized amount from this authorization?')) { try { await request(`${page.dataset.apiBase}/bookings/${capture.dataset.capture}/payment/capture`, state.token, { method: 'POST' }); notify('Payment captured successfully.'); await loadResource('bookings'); } catch (error) { notify(error.message, true); } return; }
        const status = event.target.closest('[data-booking-status]'); if (status) { const value = prompt('New status: pending, confirmed, assigned, picking_up, on_route, completed, cancelled or done'); if (value) { try { await request(`${page.dataset.apiBase}/bookings/${status.dataset.bookingStatus}/update-status`, state.token, jsonOptions({ status: value })); notify('Booking status updated.'); await loadResource('bookings'); } catch (error) { notify(error.message, true); } } }
        const disburse = event.target.closest('[data-disburse]'); if (disburse && confirm('Send this affiliate disbursement through Stripe?')) { try { await request(`${page.dataset.apiBase}/affiliate-settlements/${disburse.dataset.disburse}/disburse`, state.token, jsonOptions({})); notify('Disbursement processed.'); await loadFinance(); } catch (error) { notify(error.message, true); } }
    });
    document.querySelectorAll('[data-search]').forEach((input) => input.addEventListener('input', () => renderRows(input.dataset.search, filteredRows(input.dataset.search))));
    document.querySelectorAll('[data-filter]').forEach((input) => input.addEventListener('change', () => renderRows(input.dataset.filter, filteredRows(input.dataset.filter))));
    document.querySelectorAll('.dialog-close').forEach((button) => button.addEventListener('click', () => document.getElementById('resource-dialog').close()));
    document.getElementById('resource-form').addEventListener('submit', submitResourceForm);
    document.getElementById('global-refresh').addEventListener('click', () => showView(state.view));
    document.getElementById('logout-button').addEventListener('click', async () => { try { await request(`${page.dataset.apiBase}/user/logout`, state.token, { method: 'POST' }); } finally { logout(); } });
    document.getElementById('company-settings-form').addEventListener('submit', async (event) => { event.preventDefault(); try { await submitSettings(event.currentTarget, '/company/update', companyFields, 'Company profile'); } catch (error) { notify(error.message, true); } });
    document.getElementById('system-settings-form').addEventListener('submit', async (event) => { event.preventDefault(); try { await submitSettings(event.currentTarget, '/system-config/update', systemFields, 'System configuration'); } catch (error) { notify(error.message, true); } });
    await showView(location.hash.slice(1) && document.querySelector(`[data-admin-view="${location.hash.slice(1)}"]`) ? location.hash.slice(1) : 'overview');
};

document.addEventListener('DOMContentLoaded', () => {
    if (document.body.dataset.page === 'admin-login') bootLogin();
    if (document.body.dataset.page === 'admin-dashboard') bootDashboard();
});
