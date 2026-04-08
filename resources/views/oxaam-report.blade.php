<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Oxaam CG-AI Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#09111f;--panel:#101b31;--line:#233758;--text:#eef4ff;--muted:#9cb6d8;--accent:#4ecdc4;--accent2:#ff8a5b;--good:#3ddc97;--bad:#ff6b6b;--mono:"IBM Plex Mono",monospace;--sans:"Space Grotesk",system-ui,sans-serif}
        *{box-sizing:border-box}body{margin:0;font-family:var(--sans);color:var(--text);background:radial-gradient(circle at top left,rgba(78,205,196,.18),transparent 35%),radial-gradient(circle at top right,rgba(255,138,91,.16),transparent 32%),linear-gradient(180deg,#0c1528 0%,#07101d 100%)}
        .shell{width:min(1180px,calc(100% - 28px));margin:0 auto;padding:26px 0 40px}.hero,.grid,.stats{display:grid;gap:16px}.hero{grid-template-columns:minmax(0,1.4fr) minmax(280px,.8fr)}.stats{grid-template-columns:repeat(4,minmax(0,1fr))}.grid{grid-template-columns:minmax(0,1.15fr) minmax(300px,.85fr)}
        .card{background:rgba(16,27,49,.86);border:1px solid var(--line);border-radius:24px;padding:22px;box-shadow:0 24px 70px rgba(0,0,0,.28);backdrop-filter:blur(16px)}h1,h2,p{margin:0}.eyebrow{display:inline-block;padding:8px 12px;border-radius:999px;background:rgba(255,255,255,.06);color:var(--muted);font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}.hero p.lead,.sub{color:var(--muted);line-height:1.65}
        .hero h1{margin:14px 0 12px;font-size:clamp(2rem,4vw,3.2rem);line-height:.98;letter-spacing:-.04em}.notes{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}.note,.pill{padding:9px 12px;border-radius:14px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08)}
        .btn,.copy,.mini,a.link{font:inherit;border-radius:14px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.05);color:var(--text);cursor:pointer;text-decoration:none;transition:.16s ease} .btn:hover,.copy:hover,.mini:hover,a.link:hover{transform:translateY(-1px);border-color:rgba(78,205,196,.45);background:rgba(78,205,196,.08)}
        .btn{border:none;background:linear-gradient(135deg,var(--accent),#9af5cf);color:#07101d;font-weight:700;padding:14px 18px}.cli{font-family:var(--mono);padding:12px 14px;border-radius:16px;background:rgba(0,0,0,.22);color:#c5d9f7;font-size:.85rem;word-break:break-word}.flash{margin-bottom:14px;padding:13px 16px;border-radius:16px}.flash.ok{background:rgba(61,220,151,.12);border:1px solid rgba(61,220,151,.26);color:#cbfbe3}.flash.err{background:rgba(255,107,107,.12);border:1px solid rgba(255,107,107,.26);color:#ffd7d7}
        .stat .num{font-size:clamp(1.6rem,3vw,2.3rem);margin:8px 0}.muted{color:var(--muted)}.row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px}.status{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;font-size:.78rem;text-transform:uppercase;letter-spacing:.05em}.status:before{content:"";width:8px;height:8px;border-radius:999px}.status.success{background:rgba(61,220,151,.12);color:#cbfbe3}.status.success:before{background:var(--good)}.status.failed{background:rgba(255,107,107,.12);color:#ffd7d7}.status.failed:before{background:var(--bad)}
        .latest{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:14px}.block{padding:15px;border-radius:16px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08)}.label{margin-bottom:8px;color:var(--muted);font-size:.8rem;text-transform:uppercase;letter-spacing:.05em}.copy{width:100%;padding:12px 14px;text-align:left;font-family:var(--mono);word-break:break-all}.mini{padding:12px 14px}.linkrow{display:flex;gap:10px}.link{display:inline-flex;align-items:center;justify-content:center;padding:12px 14px;flex:1;font-weight:600}
        .meta{display:grid;gap:10px}.meta .item{display:flex;justify-content:space-between;gap:14px;padding:11px 0;border-bottom:1px solid rgba(255,255,255,.08)}.meta .item:last-child{border-bottom:none;padding-bottom:0}.meta .name{color:var(--muted)}.meta .val{font-family:var(--mono);text-align:right;word-break:break-all}.empty{padding:16px;border-radius:16px;background:rgba(255,255,255,.04);color:var(--muted);line-height:1.7}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:13px 10px;border-bottom:1px solid rgba(255,255,255,.08);vertical-align:top}th{color:var(--muted);font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;font-weight:500}.actions{display:flex;gap:8px;align-items:center;min-width:220px}.chip{display:inline-flex;padding:6px 10px;border-radius:999px;background:rgba(255,255,255,.06);font-size:.8rem}.mono{font-family:var(--mono);word-break:break-all}.toast{position:fixed;right:16px;bottom:16px;padding:12px 15px;border-radius:14px;background:rgba(7,16,29,.96);border:1px solid rgba(78,205,196,.36);opacity:0;transform:translateY(12px);pointer-events:none;transition:.2s ease}.toast.show{opacity:1;transform:translateY(0)}
        @media (max-width:1024px){.hero,.grid,.stats,.latest{grid-template-columns:1fr}}@media (max-width:640px){.shell{width:min(100% - 18px,1180px);padding-top:18px}.card{padding:18px;border-radius:20px}.row,.meta .item,.linkrow,.actions{flex-direction:column;align-items:stretch}.meta .val{text-align:left}}
    </style>
</head>
<body>
<div class="shell">
    @if (session('status')) <div class="flash ok">{{ session('status') }}</div> @endif
    @if (session('error')) <div class="flash err">{{ session('error') }}</div> @endif

    <section class="hero">
        <div class="card hero">
            <div>
                <span class="eyebrow">Live Oxaam Scraper</span>
                <h1>Oxaam CG-AI tracker</h1>
                <p class="lead">This page creates or reuses an Oxaam account, keeps one session alive for up to 300 successful scrapes, captures the latest CG-AI email, password, and code link, then stores every unique combo in one clean report.</p>
                <div class="notes">
                    <span class="note">Run from browser</span>
                    <span class="note">Unique email + password + link rows</span>
                    <span class="note">Session rotation after 300 uses</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="row" style="margin-bottom:12px">
                <div>
                    <h2>Run a fresh scrape</h2>
                    <p class="sub" style="margin-top:6px">One click will register or relogin if needed, hit the Oxaam dashboard, and store a new CG-AI report.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('report.scrape') }}" style="display:grid;gap:12px">
                @csrf
                <button class="btn" type="submit">Run CG-AI scrape now</button>
                <div class="cli">Keep this running for browser clicks: php artisan queue:work</div>
            </form>
        </div>
    </section>

    <section class="stats" style="margin:18px 0">
        <article class="card stat"><div class="muted">Total Runs</div><div class="num">{{ number_format($stats['total_runs']) }}</div><div class="muted">{{ number_format($stats['successful_runs']) }} successful captures</div></article>
        <article class="card stat"><div class="muted">Unique Rows</div><div class="num">{{ number_format($stats['unique_credentials']) }}</div><div class="muted">Each unique email, password, and code link is kept once.</div></article>
        <article class="card stat"><div class="muted">Active Sessions</div><div class="num">{{ number_format($stats['active_sessions']) }}</div><div class="muted">@if ($activeSession) {{ $activeSession->uses_remaining }} uses left on current session @else First run will create one @endif</div></article>
        <article class="card stat"><div class="muted">Latest Capture</div><div class="num">{{ $latestRun?->scraped_at?->format('H:i:s') ?? '--:--:--' }}</div><div class="muted">{{ $latestRun?->scraped_at?->format('d M Y') ?? 'No run yet' }}</div></article>
    </section>

    <section class="grid" style="margin-bottom:18px">
        <article class="card">
            <div class="row">
                <div>
                    <h2>Latest Run Report</h2>
                    <p class="sub" style="margin-top:6px">The most recent CG-AI scrape and the exact values captured from the dashboard.</p>
                </div>
                @if ($latestRun)<span class="status {{ $latestRun->status }}">{{ $latestRun->status }}</span>@endif
            </div>

            @if ($latestRun)
                @if ($latestRun->status === 'success')
                    <div class="latest">
                        <div class="block">
                            <div class="label">Email</div>
                            <button class="copy" type="button" data-copy="{{ $latestRun->account_email }}">{{ $latestRun->account_email }}</button>
                        </div>
                        <div class="block">
                            <div class="label">Password</div>
                            <button class="copy" type="button" data-copy="{{ $latestRun->account_password }}">{{ $latestRun->account_password }}</button>
                        </div>
                        <div class="block">
                            <div class="label">Code Link</div>
                            <div class="linkrow">
                                <a class="link" href="{{ $latestRun->code_url }}" target="_blank" rel="noreferrer">Open link</a>
                                <button class="mini" type="button" data-copy="{{ $latestRun->code_url }}">Copy</button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="empty">{{ $latestRun->error_message ?: 'The last run failed before a credential payload could be stored.' }}</div>
                @endif

                <div class="meta">
                    <div class="item"><span class="name">Scraper account</span><span class="val">{{ $latestRun->session?->registration_email ?? 'Not available' }}</span></div>
                    <div class="item"><span class="name">Session cookie</span><span class="val">{{ $latestRun->session?->cookie_value ?? 'Not available' }}</span></div>
                    <div class="item"><span class="name">Uses after run</span><span class="val">{{ $latestRun->session_uses_after ?? 'n/a' }}</span></div>
                    <div class="item"><span class="name">Run duration</span><span class="val">{{ $latestRun->duration_ms ? $latestRun->duration_ms.' ms' : 'n/a' }}</span></div>
                    <div class="item"><span class="name">Captured at</span><span class="val">{{ $latestRun->scraped_at?->format('d M Y, h:i:s A') ?? 'n/a' }}</span></div>
                </div>
            @else
                <div class="empty">Nothing has been scraped yet. Hit <strong>Run CG-AI scrape now</strong> and the first report will show up here.</div>
            @endif
        </article>

        <aside class="card">
            <div class="row">
                <div>
                    <h2>Session Snapshot</h2>
                    <p class="sub" style="margin-top:6px">What the currently reusable Oxaam session looks like right now.</p>
                </div>
            </div>
            @if ($activeSession)
                <div class="meta">
                    <div class="item"><span class="name">Registered email</span><span class="val">{{ $activeSession->registration_email }}</span></div>
                    <div class="item"><span class="name">Phone</span><span class="val">{{ $activeSession->registration_phone }}</span></div>
                    <div class="item"><span class="name">PHPSESSID</span><span class="val">{{ $activeSession->cookie_value ?: 'Missing' }}</span></div>
                    <div class="item"><span class="name">Uses left</span><span class="val">{{ $activeSession->uses_remaining }}</span></div>
                    <div class="item"><span class="name">Last used</span><span class="val">{{ $activeSession->last_used_at?->diffForHumans() ?? 'Never' }}</span></div>
                </div>
            @else
                <div class="empty">No reusable session exists yet. The first successful scrape will generate one and store its cookie here.</div>
            @endif
        </aside>
    </section>

    <section class="card" style="margin-bottom:18px">
        <div class="row">
            <div>
                <h2>Unique Credential Rows</h2>
                <p class="sub" style="margin-top:6px">Every unique email, password, and code-link combo captured so far. Click email or password to copy. Open the link in a new tab or copy it separately.</p>
            </div>
        </div>
        <div style="overflow-x:auto">
            <table>
                <thead><tr><th>Email</th><th>Password</th><th>Code Link</th><th>Seen</th><th>Last Seen</th></tr></thead>
                <tbody>
                @forelse ($uniqueCredentials as $credential)
                    <tr>
                        <td><button class="copy" type="button" data-copy="{{ $credential->account_email }}">{{ $credential->account_email }}</button></td>
                        <td><button class="copy" type="button" data-copy="{{ $credential->account_password }}">{{ $credential->account_password }}</button></td>
                        <td><div class="actions"><a class="link" href="{{ $credential->code_url }}" target="_blank" rel="noreferrer">Open link</a><button class="mini" type="button" data-copy="{{ $credential->code_url }}">Copy link</button></div></td>
                        <td><span class="chip">{{ $credential->seen_count }}x</span></td>
                        <td>{{ $credential->last_seen_at?->diffForHumans() ?? 'n/a' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty">The unique credential table is still empty. Run the scraper once and the first row will land here automatically.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="row">
            <div>
                <h2>Recent Run History</h2>
                <p class="sub" style="margin-top:6px">A compact list of the latest scraper passes and what each one returned.</p>
            </div>
        </div>
        <div style="overflow-x:auto">
            <table>
                <thead><tr><th>Status</th><th>Captured Value</th><th>Scraper Session</th><th>Uses</th><th>When</th></tr></thead>
                <tbody>
                @forelse ($recentRuns as $run)
                    <tr>
                        <td><span class="status {{ $run->status }}">{{ $run->status }}</span></td>
                        <td class="mono">@if ($run->status === 'success'){{ $run->account_email }}@else{{ $run->error_message ?: 'Failed run' }}@endif</td>
                        <td class="mono">{{ $run->session?->registration_email ?? 'n/a' }}</td>
                        <td>{{ $run->session_uses_after ?? 'n/a' }}</td>
                        <td>{{ $run->scraped_at?->diffForHumans() ?? 'n/a' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty">No scraper history yet.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="toast" id="copyToast">Copied to clipboard</div>
<script>
    const toast=document.getElementById('copyToast'); let toastTimer=null;
    function showToast(message){toast.textContent=message;toast.classList.add('show');if(toastTimer){clearTimeout(toastTimer)}toastTimer=setTimeout(()=>toast.classList.remove('show'),1800)}
    async function copyValue(value){if(!value){return}if(navigator.clipboard&&navigator.clipboard.writeText){await navigator.clipboard.writeText(value);return}const area=document.createElement('textarea');area.value=value;area.style.position='fixed';area.style.opacity='0';document.body.appendChild(area);area.focus();area.select();document.execCommand('copy');document.body.removeChild(area)}
    document.addEventListener('click',async(event)=>{const button=event.target.closest('[data-copy]');if(!button){return}try{await copyValue(button.dataset.copy);showToast('Copied to clipboard')}catch(error){showToast('Copy failed')}})
</script>
</body>
</html>
