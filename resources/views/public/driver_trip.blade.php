<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Trip Controls · {{ $company?->name }}</title><style>
*{box-sizing:border-box}body{margin:0;background:#f3f6fb;color:#172033;font-family:Arial,sans-serif}.shell{max-width:620px;margin:auto;padding:18px}.brand{display:flex;align-items:center;gap:12px;margin:8px 0 20px}.brand img{max-width:54px;max-height:54px}.brand strong{font-size:22px}.card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:20px;margin-bottom:14px;box-shadow:0 8px 30px rgba(15,23,42,.05)}.label{color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.8px}.status{display:inline-block;margin-top:8px;padding:6px 10px;border-radius:20px;background:#e2e8f0;font-weight:700;text-transform:capitalize}.route{margin:12px 0;padding:10px 0;border-bottom:1px solid #eef2f7}.route:last-child{border:0}.route small{display:block;color:#64748b;margin-bottom:4px}button{width:100%;border:0;border-radius:10px;padding:15px;background:#0f172a;color:#fff;font-size:16px;font-weight:700;cursor:pointer}button:disabled{opacity:.45}.notice{padding:12px;border-radius:9px;background:#eef6ff;color:#1e40af;margin-bottom:12px;font-size:14px}.error{background:#fff1f2;color:#be123c}.vehicle{color:#475569;line-height:1.6}
</style></head><body><main class="shell"><div class="brand">
@if($company?->logo)
<img src="{{ $company->logo }}" alt="Logo">
@endif
<strong>{{ $company?->name ?? config('app.name') }}</strong></div><div id="notice" class="notice">Loading trip…</div><section class="card"><div class="label">Booking</div><h1 style="margin:5px 0 8px;font-size:25px;">#{{ $booking->id }}</h1><div id="status" class="status">{{ str_replace('_',' ',$booking->status) }}</div></section><section class="card"><div class="label">Route</div><div class="route"><small>Pickup</small><strong>{{ $booking->pickup_address }}</strong></div>
@foreach($booking->stops as $stop)
<div class="route"><small>Stop {{ $stop->position }}</small><strong>{{ $stop->address }}</strong></div>
@endforeach
@if($booking->dropoff_address)
<div class="route"><small>Drop-off</small><strong>{{ $booking->dropoff_address }}</strong></div>
@endif
</section>
@if($booking->vehicle)
<section class="card vehicle"><div class="label">Vehicle</div><strong>{{ $booking->vehicle->name }}</strong><br>{{ collect([$booking->vehicle->color,$booking->vehicle->model,$booking->vehicle->plate_number])->filter()->join(' · ') }}</section>
@endif
<section class="card"><button id="statusBtn" disabled>Loading…</button></section></main><script>
const token=@json($token),api='/api/public/driver-trips/'+encodeURIComponent(token),notice=document.getElementById('notice'),button=document.getElementById('statusBtn'),statusEl=document.getElementById('status');let trip=null,watchId=null;const labels={picking_up:'Start picking up',on_route:'Start trip',done:'Complete trip'};
async function call(url,options={}){const response=await fetch(url,{headers:{'Accept':'application/json','Content-Type':'application/json'},...options});const payload=await response.json();if(!response.ok)throw new Error(payload.message||'Request failed');return payload}
function render(data){trip=data;statusEl.textContent=String(data.status).replaceAll('_',' ');button.textContent=data.next_status?labels[data.next_status]:'Trip controls completed';button.disabled=!data.next_status;if(['picking_up','on_route'].includes(data.status))startLocation();if(data.status==='done'){notice.textContent='Trip completed. Location sharing has stopped.';stopLocation()}}
async function load(){try{render((await call(api)).data);notice.textContent='Allow location access to share your position during the trip.'}catch(error){notice.classList.add('error');notice.textContent=error.message}}
button.addEventListener('click',async()=>{if(!trip?.next_status)return;button.disabled=true;try{render((await call(api+'/status',{method:'POST',body:JSON.stringify({status:trip.next_status})})).data)}catch(error){notice.classList.add('error');notice.textContent=error.message;button.disabled=false}});
function startLocation(){if(watchId!==null||!navigator.geolocation)return;watchId=navigator.geolocation.watchPosition(async position=>{notice.classList.remove('error');notice.textContent='Location sharing active · '+new Date().toLocaleTimeString();try{await call(api+'/location',{method:'POST',body:JSON.stringify({latitude:position.coords.latitude,longitude:position.coords.longitude,accuracy:position.coords.accuracy,heading:position.coords.heading,speed:position.coords.speed,recorded_at:new Date(position.timestamp).toISOString()})})}catch(error){notice.classList.add('error');notice.textContent=error.message}},error=>{notice.classList.add('error');notice.textContent='Location permission is required: '+error.message},{enableHighAccuracy:true,maximumAge:5000,timeout:15000})}
function stopLocation(){if(watchId!==null)navigator.geolocation.clearWatch(watchId);watchId=null}load();
</script></body></html>
