@extends('partials.special_user.special_sidebar_navbar')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/unserved-report.css') }}?v={{ @filemtime(public_path('css/unserved-report.css')) ?: time() }}">
@endpush

@section('unserved_report_content')
<div id="unserved-report-root"
     class="unserved-report-page"
     data-customers-url="{{ route('special.unserved-report.customers') }}"
     data-salesmen-url="{{ route('special.unserved-report.salesmen') }}"
     data-data-url="{{ route('special.unserved-report.data') }}"
     data-print-url="{{ route('special.unserved-report.print') }}">

    <section class="unserved-hero">
        <div>
            <p class="unserved-eyebrow">Sales Reports</p>
            <h2>Unserved Details</h2>
            <p>Remaining quantities from <strong>Open</strong> and <strong>Partial</strong> Sales Notes. ON HAND is read from the latest Product Ledger balance.</p>
        </div>
        <div class="unserved-hero-badge">
            <i data-lucide="package-search"></i>
            <span>Open + Partial</span>
        </div>
    </section>

    <section class="unserved-filter-card no-print">
        <div class="unserved-card-heading">
            <div>
                <span class="unserved-card-kicker">Report Filters</span>
                <h3>Unserved Details</h3>
            </div>
            <i data-lucide="sliders-horizontal"></i>
        </div>

        <div class="unserved-filter-grid">
            <div class="unserved-field unserved-combobox" data-combobox="customer">
                <label for="unserved-customer">Customer</label>
                <div class="unserved-input-wrap">
                    <i data-lucide="building-2"></i>
                    <input id="unserved-customer" type="text" autocomplete="off" placeholder="All customers">
                    <button type="button" class="unserved-combo-toggle" aria-label="Browse customers">
                        <i data-lucide="chevron-down"></i>
                    </button>
                </div>
                <div class="unserved-combo-menu hidden"></div>
            </div>

            <div class="unserved-field unserved-combobox" data-combobox="salesman">
                <label for="unserved-salesman">Sales Man</label>
                <div class="unserved-input-wrap">
                    <i data-lucide="user-round"></i>
                    <input id="unserved-salesman" type="text" autocomplete="off" placeholder="All sales men">
                    <button type="button" class="unserved-combo-toggle" aria-label="Browse sales men">
                        <i data-lucide="chevron-down"></i>
                    </button>
                </div>
                <div class="unserved-combo-menu hidden"></div>
            </div>

            <div class="unserved-field">
                <label for="unserved-date-type">Date</label>
                <div class="unserved-input-wrap">
                    <i data-lucide="calendar-range"></i>
                    <select id="unserved-date-type">
                        <option value="annual">Annual</option>
                        <option value="monthly" selected>Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="half-year">Half Year</option>
                        <option value="as-of">As Of</option>
                        <option value="from-to">From - To</option>
                    </select>
                </div>
            </div>

            <div id="unserved-date-fields" class="unserved-date-fields"></div>
        </div>

        <div class="unserved-actions">
            <div class="unserved-live-hint">
                <i data-lucide="database"></i>
                <span>Preview refreshes from the database using your selected filters.</span>
            </div>
            <button id="unserved-print-btn" type="button" class="unserved-print-button">
                <i data-lucide="printer"></i>
                <span>Print</span>
            </button>
        </div>
    </section>

    <section class="unserved-summary-grid">
        <article>
            <span>Open / Partial Notes</span>
            <strong id="unserved-note-count">0</strong>
        </article>
        <article>
            <span>Unserved Lines</span>
            <strong id="unserved-line-count">0</strong>
        </article>
        <article>
            <span>Total Unserved Qty</span>
            <strong id="unserved-qty-count">0</strong>
        </article>
        <article>
            <span>Selected Period</span>
            <strong id="unserved-period-label">—</strong>
        </article>
    </section>

    <section class="unserved-table-card">
        <div class="unserved-table-heading">
            <div>
                <span class="unserved-card-kicker">Live Preview</span>
                <h3>Unserved Details</h3>
            </div>
            <span id="unserved-loading-label" class="unserved-loading-label">Ready</span>
        </div>
        <div class="unserved-table-scroll">
            <table class="unserved-table">
                <thead>
                    <tr>
                        <th>S.O. No.</th>
                        <th>Product Code</th>
                        <th>Part No.</th>
                        <th>Description</th>
                        <th class="num">On Hand</th>
                        <th class="num">Served</th>
                        <th class="num">Unserved</th>
                        <th class="num">Unit Price</th>
                        <th class="num">Total Amount</th>
                    </tr>
                </thead>
                <tbody id="unserved-table-body">
                    <tr><td colspan="9" class="unserved-empty">Loading unserved details…</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <div id="unserved-toast" class="unserved-toast hidden" role="status">
        <i data-lucide="circle-alert"></i>
        <span></span>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/unserved-report.js') }}?v={{ @filemtime(public_path('js/unserved-report.js')) ?: time() }}"></script>
@endpush
