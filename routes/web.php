<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use App\Models\Company;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::get('/admin/login', function () {
    return view('admin.login', [
        'company' => Company::query()->first(['name', 'logo']),
    ]);
})->name('admin.login');

Route::get('/admin/dashboard', function () {
    return view('admin.dashboard', [
        'company' => Company::query()->first(['name', 'logo']),
    ]);
})->name('admin.dashboard');

Route::get('/maintenance/clear', function (Request $request) {
    // $expectedKey = (string) env('MAINTENANCE_KEY', '');
    // $providedKey = (string) $request->query('key', '');

    // if ($expectedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
    //     return response()->json(['message' => 'Unauthorized'], 403);
    // }

    Artisan::call('optimize:clear');
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');

    return response()->json([
        'message' => 'Maintenance clear commands executed successfully',
    ]);
});

// Local-only page to confirm Reverb events are received in the browser.
Route::get('/debug/reverb', function () {
    $reverbKey = (string) config('broadcasting.connections.reverb.key', env('REVERB_APP_KEY', 'local'));
    // For browser clients, never point at 127.0.0.1 on a remote server.
    // Default to the current host and the Nginx WS proxy path (/ws).
    $wsHost = request()->getHost();
    $wsPort = (int) request()->getPort();
    $wsScheme = request()->isSecure() ? 'https' : 'http';
    $wsPath = '/ws';

    $reverbKeyJson = json_encode($reverbKey, JSON_UNESCAPED_SLASHES);
    $wsHostJson = json_encode($wsHost, JSON_UNESCAPED_SLASHES);
    $wsSchemeJson = json_encode($wsScheme, JSON_UNESCAPED_SLASHES);
    $wsPathJson = json_encode($wsPath, JSON_UNESCAPED_SLASHES);

    $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Reverb Debug</title>
  <style>
    body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 24px; }
    .row { display: flex; gap: 12px; flex-wrap: wrap; align-items: end; }
    label { display: block; font-size: 12px; color: #444; margin-bottom: 6px; }
    input { padding: 10px 12px; border: 1px solid #ccc; border-radius: 8px; min-width: 260px; }
    button { padding: 10px 14px; border: 1px solid #111; border-radius: 8px; background: #111; color: #fff; cursor: pointer; }
    button.secondary { background: #fff; color: #111; }
    pre { margin-top: 16px; padding: 12px; background: #0b1020; color: #e6edf3; border-radius: 10px; overflow: auto; max-height: 65vh; }
    .hint { margin-top: 10px; color: #666; font-size: 13px; }
    code { background: #f3f4f6; padding: 2px 6px; border-radius: 6px; }
  </style>
</head>
<body>
  <h2>Reverb Debug (private-booking.{id})</h2>
  <div class="row">
    <div>
      <label>Booking ID</label>
      <input id="bookingId" placeholder="e.g. 56" />
    </div>
    <div>
      <label>Admin/Dispatcher Bearer Token</label>
      <input id="token" placeholder="paste token here" />
    </div>
    <div>
      <button id="connectBtn">Connect</button>
      <button class="secondary" id="clearBtn" type="button">Clear Log</button>
    </div>
  </div>

  <div class="hint">
    1) Start Reverb: <code>php artisan reverb:start --host=127.0.0.1 --port={$wsPort}</code><br/>
    2) Set booking status to <code>on_route</code> and send driver location updates.<br/>
    3) You should see <code>booking.location.updated</code> events here.
  </div>

  <pre id="log"></pre>

  <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
  <script>
    const REVERB_KEY = {$reverbKeyJson};
    const WS_HOST = {$wsHostJson};
    const WS_PORT = {$wsPort};
    const WS_SCHEME = {$wsSchemeJson};
    const WS_PATH = {$wsPathJson};
    const AUTH_ENDPOINT = '/api/broadcasting/auth';

    const logEl = document.getElementById('log');
    function log(line, obj) {
      const ts = new Date().toISOString();
      logEl.textContent += '[' + ts + '] ' + line + (obj ? ('\\n' + JSON.stringify(obj, null, 2)) : '') + '\\n\\n';
      logEl.scrollTop = logEl.scrollHeight;
    }

    let echo = null;
    let channel = null;

    // Make the underlying websocket state visible.
    try {
      if (window.Pusher) window.Pusher.logToConsole = true;
      if (typeof Pusher !== 'undefined') Pusher.logToConsole = true;
    } catch (e) {}

    // Verify libraries are actually loaded.
    try {
      const hasPusher = !!(window.Pusher || (typeof Pusher !== 'undefined'));
      const hasEcho = !!(window.Echo || (typeof Echo !== 'undefined'));
      log('Debug libs', { hasPusher, hasEcho });
    } catch (e) {}

    document.getElementById('clearBtn').addEventListener('click', () => {
      logEl.textContent = '';
    });

    document.getElementById('connectBtn').addEventListener('click', async () => {
      const bookingId = document.getElementById('bookingId').value.trim();
      const token = document.getElementById('token').value.trim();

      if (!bookingId) return log('Missing booking id');
      if (!token) return log('Missing bearer token');

      if (echo) {
        try { echo.disconnect(); } catch (e) {}
        echo = null;
        channel = null;
      }

      log('Connecting...', { bookingId, wsHost: WS_HOST, wsPort: WS_PORT, scheme: WS_SCHEME });

      try {
        const EchoCtor = window.Echo || (typeof Echo !== 'undefined' ? Echo : null);
        if (!EchoCtor) {
          log('Echo is not available. The laravel-echo script did not load.');
          return;
        }

        echo = new EchoCtor({
          broadcaster: 'pusher',
          key: REVERB_KEY,
          cluster: 'mt1',
          wsHost: WS_HOST,
          wsPort: WS_PORT,
          wssPort: WS_PORT,
          wsPath: WS_PATH,
          forceTLS: WS_SCHEME === 'https',
          enabledTransports: ['ws', 'wss'],
          disableStats: true,
          authEndpoint: AUTH_ENDPOINT,
          auth: {
            headers: {
              Authorization: 'Bearer ' + token,
              Accept: 'application/json'
            }
          }
        });
      } catch (err) {
        log('Failed to init Echo', { message: String(err && err.message ? err.message : err), err });
        return;
      }

      // Pusher-js connection state hooks
      try {
        echo.connector.pusher.connection.bind('state_change', (states) => log('WS state_change', states));
        echo.connector.pusher.connection.bind('connecting', () => log('WS connecting'));
        echo.connector.pusher.connection.bind('connected', () => log('WS connected'));
        echo.connector.pusher.connection.bind('error', (err) => log('WS error', err));
        echo.connector.pusher.connection.bind('disconnected', () => log('WS disconnected'));
      } catch (e) {}

      channel = echo.private('booking.' + bookingId);

      channel.subscribed(() => log('Subscribed to private-booking.' + bookingId));
      channel.error((err) => log('Channel error', err));

      channel.listen('.booking.location.updated', (e) => {
        log('Event: booking.location.updated', e);
      });

      setTimeout(() => {
        try {
          const state = echo && echo.connector && echo.connector.pusher
            ? echo.connector.pusher.connection.state
            : 'unknown';
          log('WS check after 5s', { state });
        } catch (e) {}
      }, 5000);
    });
  </script>
</body>
</html>
HTML;

    return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
});
