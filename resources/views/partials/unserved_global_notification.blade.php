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
    <div class="w68-unserved-reminder-backdrop" aria-hidden="true"></div>
    <section class="w68-unserved-reminder-dialog" role="dialog" aria-modal="true" aria-labelledby="w68-unserved-reminder-title">
        <div class="w68-unserved-reminder-head">
            <div class="w68-unserved-reminder-icon" aria-hidden="true">!</div>
            <div>
                <p class="w68-unserved-reminder-kicker">Scheduled Unserved Reminder</p>
                <h2 id="w68-unserved-reminder-title">Unserved Items Notification</h2>
                <p id="w68-unserved-reminder-time">Manila Time</p>
            </div>
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

        <a id="w68-unserved-reminder-open" class="w68-unserved-reminder-button" href="{{ route($w68UnservedRoutePrefix . '.unserved-report') }}">
            View Unserved Report
        </a>
    </section>
</div>

<style>
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
    }
    .w68-unserved-reminder-head {
        display: flex;
        gap: 14px;
        align-items: flex-start;
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
        font-size: 24px;
        line-height: 1.2;
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
    .w68-unserved-reminder-button {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        margin-top: 20px;
        min-height: 46px;
        border-radius: 12px;
        background: #7f1d1d;
        color: #ffffff !important;
        text-decoration: none !important;
        font-size: 14px;
        font-weight: 800;
        transition: transform .15s ease, background .15s ease;
    }
    .w68-unserved-reminder-button:hover {
        background: #991b1b;
        transform: translateY(-1px);
    }
    @media (max-width: 520px) {
        #w68-unserved-scheduled-notification { padding: 12px; }
        .w68-unserved-reminder-dialog { padding: 18px; border-radius: 16px; }
        #w68-unserved-reminder-title { font-size: 20px; }
        .w68-unserved-reminder-row { align-items: flex-start; }
        .w68-unserved-reminder-row span { max-width: 75%; }
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
    const servableCount = document.getElementById('w68-unserved-servable-count');
    const withStockCount = document.getElementById('w68-unserved-with-stock-count');
    const withoutStockCount = document.getElementById('w68-unserved-without-stock-count');
    const allCount = document.getElementById('w68-unserved-all-count');

    const memorySeen = new Set();
    let requestRunning = false;
    let activeSlotKey = null;

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

    checkSchedule();
    window.setInterval(checkSchedule, 30000);
})();
</script>
@endif
