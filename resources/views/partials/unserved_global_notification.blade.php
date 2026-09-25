@php
    $w68UnservedNotificationUser = session('user');
    if (is_array($w68UnservedNotificationUser)) {
        $w68UnservedNotificationUser = (object) $w68UnservedNotificationUser;
    }

    $w68UnservedAccountType = (int) ($w68UnservedNotificationUser->account_type ?? 0);
    $w68UnservedRoutePrefix = match ($w68UnservedAccountType) {
        1 => 'admin',
        2 => 'regular',
        3 => 'special',
        default => null,
    };

    $w68UnservedNotificationUserKey = (string) (
        $w68UnservedNotificationUser->login_ID
        ?? $w68UnservedNotificationUser->id
        ?? $w68UnservedNotificationUser->username
        ?? 'user'
    );
@endphp

@if($w68UnservedRoutePrefix)
<div
    id="w68-unserved-scheduled-notification"
    data-summary-url="{{ route($w68UnservedRoutePrefix . '.unserved-report.notification-summary') }}"
    data-report-url="{{ route($w68UnservedRoutePrefix . '.unserved-report') }}"
    data-user-key="{{ $w68UnservedAccountType }}-{{ $w68UnservedNotificationUserKey }}"
    hidden
>
    <div class="w68-unserved-reminder-backdrop" id="w68-unserved-reminder-backdrop" aria-hidden="true"></div>
    <section class="w68-unserved-reminder-dialog" role="dialog" aria-modal="true" aria-labelledby="w68-unserved-reminder-title">
        <div class="w68-unserved-reminder-head">
            <div class="w68-unserved-reminder-head-main">
                <div class="w68-unserved-reminder-icon" aria-hidden="true">!</div>
                <div>
                    <p class="w68-unserved-reminder-kicker">Scheduled Unserved Reminder</p>
                    <h2 id="w68-unserved-reminder-title">Unserved Items Notification</h2>
                    <p id="w68-unserved-reminder-time">Manila Time</p>
                </div>
            </div>
            <button type="button" id="w68-unserved-reminder-close" class="w68-unserved-reminder-close-btn" title="View later / Close" aria-label="Close notification">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <p class="w68-unserved-reminder-copy">
            Please review the current unserved items. These totals are live as of today.
        </p>

        <div class="w68-unserved-reminder-counts" aria-live="polite">
            <div class="w68-unserved-reminder-row">
                <span>SERVABLE ITEMS:</span>
                <strong id="w68-unserved-servable-count">0</strong>
            </div>
            <div class="w68-unserved-reminder-row">
                <span>Unserved With Stocks:</span>
                <strong id="w68-unserved-with-stock-count">0</strong>
            </div>
            <div class="w68-unserved-reminder-row">
                <span>Unserved Items Without Stocks:</span>
                <strong id="w68-unserved-without-stock-count">0</strong>
            </div>
            <div class="w68-unserved-reminder-row w68-unserved-reminder-total">
                <span>ALL UNSERVED ITEMS:</span>
                <strong id="w68-unserved-all-count">0</strong>
            </div>
        </div>

        <div class="w68-unserved-reminder-actions">
            <button type="button" id="w68-unserved-reminder-later" class="w68-unserved-reminder-button-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span>View Later</span>
            </button>
            <a id="w68-unserved-reminder-open" class="w68-unserved-reminder-button" href="{{ route($w68UnservedRoutePrefix . '.unserved-report') }}">
                <span>View Unserved Report</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </a>
        </div>
    </section>
</div>

<style>
    html.w68-unserved-reminder-open,
    html.w68-unserved-reminder-open body {
        overflow: hidden !important;
    }
    #w68-unserved-scheduled-notification[hidden] { display: none !important; }
    #w68-unserved-scheduled-notification {
        position: fixed;
        inset: 0;
        z-index: 2147483000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        font-family: inherit;
    }
    .w68-unserved-reminder-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.72);
        backdrop-filter: blur(4px);
        cursor: pointer;
    }
    .w68-unserved-reminder-dialog {
        position: relative;
        width: min(560px, 100%);
        max-height: calc(100vh - 40px);
        overflow: auto;
        border-radius: 20px;
        background: #ffffff;
        border: 1px solid rgba(148, 163, 184, 0.35);
        box-shadow: 0 24px 70px rgba(15, 23, 42, 0.35);
        padding: 24px;
        color: #0f172a;
        animation: w68-unserved-pop-in 0.22s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    @keyframes w68-unserved-pop-in {
        from {
            opacity: 0;
            transform: scale(0.96) translateY(6px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    .w68-unserved-reminder-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
    }
    .w68-unserved-reminder-head-main {
        display: flex;
        gap: 14px;
        align-items: flex-start;
        flex: 1;
    }
    .w68-unserved-reminder-close-btn {
        flex: 0 0 34px;
        width: 34px;
        height: 34px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 0;
        transition: background .15s ease, border-color .15s ease, color .15s ease, transform .15s ease;
    }
    .w68-unserved-reminder-close-btn:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #0f172a;
        transform: scale(1.05);
    }
    .w68-unserved-reminder-icon {
        flex: 0 0 46px;
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: #7f1d1d;
        color: #ffffff;
        font-size: 24px;
        font-weight: 800;
        line-height: 1;
    }
    .w68-unserved-reminder-kicker {
        margin: 0 0 4px;
        color: #991b1b;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
    }
    #w68-unserved-reminder-title {
        margin: 0;
        color: #0f172a;
        font-size: 22px;
        line-height: 1.25;
        font-weight: 800;
    }
    #w68-unserved-reminder-time {
        margin: 5px 0 0;
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
    }
    .w68-unserved-reminder-copy {
        margin: 18px 0 16px;
        color: #475569;
        font-size: 14px;
        line-height: 1.55;
    }
    .w68-unserved-reminder-counts {
        display: grid;
        gap: 9px;
    }
    .w68-unserved-reminder-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 12px 14px;
        border-radius: 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        font-size: 14px;
        font-weight: 700;
    }
    .w68-unserved-reminder-row strong {
        min-width: 44px;
        text-align: right;
        color: #0f172a;
        font-size: 20px;
        font-variant-numeric: tabular-nums;
    }
    .w68-unserved-reminder-total {
        background: #fff7ed;
        border-color: #fdba74;
        color: #9a3412;
    }
    .w68-unserved-reminder-total strong { color: #9a3412; }
    .w68-unserved-reminder-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 22px;
    }
    .w68-unserved-reminder-button-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 46px;
        padding: 0 20px;
        border-radius: 12px;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        color: #334155;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        transition: background .15s ease, border-color .15s ease, color .15s ease, transform .15s ease;
        flex: 1;
        font-family: inherit;
    }
    .w68-unserved-reminder-button-secondary:hover {
        background: #e2e8f0;
        border-color: #94a3b8;
        color: #0f172a;
        transform: translateY(-1px);
    }
    .w68-unserved-reminder-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 46px;
        padding: 0 22px;
        border-radius: 12px;
        background: #7f1d1d;
        color: #ffffff !important;
        text-decoration: none !important;
        font-size: 14px;
        font-weight: 800;
        box-shadow: 0 4px 14px rgba(127, 29, 29, 0.28);
        transition: transform .15s ease, background .15s ease, box-shadow .15s ease;
        border: none;
        cursor: pointer;
        flex: 1.25;
        font-family: inherit;
    }
    .w68-unserved-reminder-button:hover {
        background: #991b1b;
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(127, 29, 29, 0.38);
    }
    @media (max-width: 520px) {
        #w68-unserved-scheduled-notification { padding: 12px; }
        .w68-unserved-reminder-dialog { padding: 18px; border-radius: 16px; }
        #w68-unserved-reminder-title { font-size: 19px; }
        .w68-unserved-reminder-row { align-items: flex-start; }
        .w68-unserved-reminder-row span { max-width: 75%; }
        .w68-unserved-reminder-actions {
            flex-direction: column-reverse;
            gap: 10px;
        }
        .w68-unserved-reminder-button-secondary,
        .w68-unserved-reminder-button {
            width: 100%;
            flex: auto;
        }
    }
</style>

<script>
(() => {
    const root = document.getElementById('w68-unserved-scheduled-notification');
    if (!root || root.dataset.initialized === '1') return;
    root.dataset.initialized = '1';

    const summaryUrl = root.dataset.summaryUrl;
    const reportUrl = root.dataset.reportUrl;
    const userKey = root.dataset.userKey || 'user';
    const title = document.getElementById('w68-unserved-reminder-title');
    const timeLabel = document.getElementById('w68-unserved-reminder-time');
    const openButton = document.getElementById('w68-unserved-reminder-open');
    const laterButton = document.getElementById('w68-unserved-reminder-later');
    const closeButton = document.getElementById('w68-unserved-reminder-close');
    const backdrop = document.getElementById('w68-unserved-reminder-backdrop');
    const servableCount = document.getElementById('w68-unserved-servable-count');
    const withStockCount = document.getElementById('w68-unserved-with-stock-count');
    const withoutStockCount = document.getElementById('w68-unserved-without-stock-count');
    const allCount = document.getElementById('w68-unserved-all-count');

    const memorySeen = new Set();
    let requestRunning = false;
    let activeSlotKey = null;

    function closeNotification() {
        root.hidden = true;
        document.documentElement.classList.remove('w68-unserved-reminder-open');
    }

    if (laterButton) laterButton.addEventListener('click', closeNotification);
    if (closeButton) closeButton.addEventListener('click', closeNotification);
    if (backdrop) backdrop.addEventListener('click', closeNotification);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !root.hidden) {
            closeNotification();
        }
    });

    const manilaFormatter = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
        hourCycle: 'h23',
    });

    function manilaNowParts() {
        const values = {};
        manilaFormatter.formatToParts(new Date()).forEach(part => {
            if (part.type !== 'literal') values[part.type] = part.value;
        });

        return {
            year: values.year,
            month: values.month,
            day: values.day,
            hour: Number(values.hour),
            minute: Number(values.minute),
        };
    }

    function scheduledSlot(parts) {
        if (parts.hour === 16) return { key: '16', label: '4:00 PM' };
        if (parts.hour === 17) return { key: '17', label: '5:00 PM' };
        return null;
    }

    function storageKey(parts, slot) {
        return `w68-unserved-reminder:${userKey}:${parts.year}-${parts.month}-${parts.day}:${slot.key}`;
    }

    function hasSeen(key) {
        try {
            return window.localStorage.getItem(key) === '1';
        } catch (error) {
            return memorySeen.has(key);
        }
    }

    function markSeen(key) {
        memorySeen.add(key);
        try {
            window.localStorage.setItem(key, '1');
        } catch (error) {
            // In-memory fallback keeps this page from repeating the same slot.
        }
    }

    function formatCount(value) {
        const number = Number(value ?? 0);
        return Number.isFinite(number) ? number.toLocaleString() : '0';
    }

    async function showNotification(parts, slot, key) {
        if (requestRunning || activeSlotKey === key) return;
        requestRunning = true;

        try {
            const response = await fetch(summaryUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload?.success) {
                throw new Error(payload?.message || `Unable to load Unserved Report totals (${response.status}).`);
            }

            const counts = payload.counts || {};
            servableCount.textContent = formatCount(counts.servable_items);
            withStockCount.textContent = formatCount(counts.unserved_with_stocks);
            withoutStockCount.textContent = formatCount(counts.unserved_without_stocks);
            allCount.textContent = formatCount(counts.all_unserved_items);

            title.textContent = `Unserved Items Notification - ${slot.label}`;
            timeLabel.textContent = `${parts.year}-${parts.month}-${parts.day} • ${slot.label} Manila Time`;
            openButton.href = reportUrl;

            activeSlotKey = key;
            markSeen(key);
            root.hidden = false;
            document.documentElement.classList.add('w68-unserved-reminder-open');
        } catch (error) {
            console.warn('Unserved scheduled notification:', error.message);
        } finally {
            requestRunning = false;
        }
    }

    function checkSchedule() {
        if (window.location.pathname.endsWith('/sales/unserved-report')) return;

        const parts = manilaNowParts();
        const slot = scheduledSlot(parts);
        if (!slot) return;

        const key = storageKey(parts, slot);
        if (hasSeen(key)) return;

        showNotification(parts, slot, key);
    }

    openButton.addEventListener('click', () => {
        window.location.href = reportUrl;
    });

    window.w68TestUnservedReminder = function() {
        const parts = manilaNowParts();
        const slot = { key: 'test', label: 'Preview Reminder' };
        showNotification(parts, slot, 'test-' + Date.now());
    };

    checkSchedule();
    window.setInterval(checkSchedule, 30000);
})();
</script>
@endif
