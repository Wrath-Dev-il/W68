@extends($unservedLayout)
{{-- W68_UNSERVED_STOCK_STATUS_FILTER_V2_20260921 --}}
{{-- W68_UNSERVED_OPEN_PARTIAL_STATUS_FIX_V2_20260921 --}}

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/unserved-report.css') }}?v={{ @filemtime(public_path('css/unserved-report.css')) ?: time() }}">
@endpush

@section('unserved_report_content')
<div id="unserved-report-root"
     class="unserved-report-page"
     data-customers-url="{{ route($unservedRoutePrefix . '.unserved-report.customers') }}"
     data-salesmen-url="{{ route($unservedRoutePrefix . '.unserved-report.salesmen') }}"
     data-data-url="{{ route($unservedRoutePrefix . '.unserved-report.data') }}"
     data-product-history-url="{{ route($unservedRoutePrefix . '.unserved-report.product-history') }}"
     data-print-url="{{ route($unservedRoutePrefix . '.unserved-report.print') }}">

    <section class="unserved-hero">
        <div>
            <p class="unserved-eyebrow">Sales Reports</p>
            <h2>Unserved Details</h2>
            <p>Remaining quantities from <strong>Open and Partial Sales Notes</strong>. Additional quantity is not counted as ordered/actual quantity. ON HAND is read from the latest Product Ledger balance.</p>
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
                <h3 id="unserved-preview-title">Unserved Details</h3>
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
                <label for="unserved-stock-filter">Stock</label>
                <div class="unserved-input-wrap">
                    <i data-lucide="boxes"></i>
                    <select id="unserved-stock-filter">
                        <option value="all">All</option>
                        <option value="without">Without Stock</option>
                        <option value="with">With Stock</option>
                    </select>
                </div>
            </div>

            <div class="unserved-field">
                <label for="unserved-rush-filter">Rush</label>
                <div class="unserved-input-wrap">
                    <i data-lucide="zap"></i>
                    <select id="unserved-rush-filter">
                        <option value="all">All</option>
                        <option value="rush">Rush</option>
                        <option value="not-rush">Not Rush</option>
                    </select>
                </div>
            </div>

            <div class="unserved-field">
                <label for="unserved-status-filter">Status</label>
                <div class="unserved-input-wrap">
                    <i data-lucide="tags"></i>
                    <select id="unserved-status-filter">
                        <option value="all">All (Open + Partial)</option>
                        <option value="open">Open</option>
                        <option value="partial">Partial</option>
                    </select>
                </div>
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
                <span>Preview shows remaining quantities from Open and Partial Sales Notes.</span>
            </div>
            <button id="unserved-print-btn" type="button" class="unserved-print-button">
                <i data-lucide="printer"></i>
                <span>Print Report</span>
            </button>
        </div>
    </section>

    {{-- W68_UNSERVED_SUMMARY_CARDS_V3_20260921 --}}
    <section class="unserved-summary-grid">

        <article
            id="unserved-all-card"
            class="unserved-summary-action"
            role="button"
            tabindex="0"
            title="Show all Open and Partial unserved items">

            <span>All Unserved Items</span>
            <strong id="unserved-all-count">0</strong>

            <div class="unserved-note-breakdown">
                <span>
                    <small>TOTAL</small>
                    <b id="unserved-total-notes-count">0</b>
                </span>

                <span class="is-open">
                    <small>OPEN</small>
                    <b id="unserved-open-notes-count">0</b>
                </span>

                <span class="is-partial">
                    <small>PARTIAL</small>
                    <b id="unserved-partial-notes-count">0</b>
                </span>
            </div>
        </article>

        <article
            id="unserved-without-stock-card"
            class="unserved-summary-action"
            role="button"
            tabindex="0"
            title="Show unserved items where ON HAND equals zero">

            <span>Unserved Items Without Stocks</span>
            <strong id="unserved-without-stock-count">0</strong>

            <small class="unserved-summary-rule">
                ON HAND = 0
            </small>
        </article>

        <article
            id="unserved-with-stock-card"
            class="unserved-summary-action"
            role="button"
            tabindex="0"
            title="Show unserved items that currently have stock">

            <span>Unserved With Stocks</span>
            <strong id="unserved-with-stock-count">0</strong>

            <div class="unserved-stock-status-breakdown">
                <span class="unserved-stock-status-item is-open">
                    <small>OPEN</small>
                    <b id="unserved-with-stock-open-count">0</b>
                </span>

                <span class="unserved-stock-status-item is-partial">
                    <small>PARTIAL</small>
                    <b id="unserved-with-stock-partial-count">0</b>
                </span>
            </div>
        </article>

        <article
            id="unserved-servable-card"
            class="unserved-summary-action"
            role="button"
            tabindex="0"
            title="Show items where ON HAND exactly equals UNSERVED">

            <span>Servable Item</span>
            <strong id="unserved-servable-count">0</strong>

            <small class="unserved-summary-rule">
                ON HAND = UNSERVED
            </small>
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
                    <tr class="unserved-main-head">
                        <th>S.O. No.</th><th>Name</th><th>Date</th><th>Product Code</th><th>Part No.</th><th>Description</th>
                        <th class="num">On Hand</th><th class="num">Served</th><th class="num">Unserved</th><th class="num">Unit Price</th><th class="num">Total Amount</th>
                    </tr>
                    <tr class="unserved-column-search-row">
                        <th><input data-column-search="so_no" type="text" placeholder="Search"></th>
                        <th><input data-column-search="customer" type="text" placeholder="Search"></th>
                        <th><input data-column-search="order_date" type="text" placeholder="Search"></th>
                        <th><input data-column-search="product_code" type="text" placeholder="Search"></th>
                        <th><input data-column-search="part_number" type="text" placeholder="Search"></th>
                        <th><input data-column-search="description" type="text" placeholder="Search"></th>
                        <th><input data-column-search="on_hand" type="text" placeholder="Search"></th>
                        <th><input data-column-search="served" type="text" placeholder="Search"></th>
                        <th><input data-column-search="unserved" type="text" placeholder="Search"></th>
                        <th><input data-column-search="unit_price" type="text" placeholder="Search"></th>
                        <th><input data-column-search="total_amount" type="text" placeholder="Search"></th>
                    </tr>
                </thead>
                <tbody id="unserved-table-body">
                    <tr><td colspan="11" class="unserved-empty">Loading unserved details…</td></tr>
                </tbody>
            </table>
        </div>
    </section>


    <div id="unserved-history-modal" class="unserved-history-modal hidden" aria-hidden="true">
        <div class="unserved-history-backdrop" data-history-close></div>
        <section class="unserved-history-dialog" role="dialog" aria-modal="true" aria-labelledby="unserved-history-title">
            <header class="unserved-history-header">
                <div>
                    <span class="unserved-card-kicker">Latest Product History</span>
                    <h3 id="unserved-history-title">Product History</h3>
                    <p id="unserved-history-product-label">-</p>
                </div>
                <button id="unserved-history-close" type="button" class="unserved-history-close" aria-label="Close product history">
                    <i data-lucide="x"></i>
                </button>
            </header>

            <div id="unserved-history-loading" class="unserved-history-loading hidden">
                Loading latest purchase and sales history...
            </div>

            <div id="unserved-history-content" class="unserved-history-content">
                <section class="unserved-history-section">
                    <div class="unserved-history-section-title">
                        <h4>LATEST PURCHASE TABLE</h4>
                    </div>
                    <div class="unserved-history-scroll">
                        <table class="unserved-history-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>P.O No.</th>
                                    <th>Supplier Invoice</th>
                                    <th>Supplier Name</th>
                                    <th class="num">Qty</th>
                                    <th class="num">Unit Cost</th>
                                </tr>
                            </thead>
                            <tbody id="unserved-history-purchase-body">
                                <tr><td colspan="6" class="unserved-history-empty">Click an item to load purchase history.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="unserved-history-section">
                    <div class="unserved-history-section-title">
                        <h4>LATEST SALES TABLE</h4>
                    </div>
                    <div class="unserved-history-scroll">
                        <table class="unserved-history-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Sales Invoice</th>
                                    <th>Customer Name</th>
                                    <th class="num">Qty</th>
                                    <th class="num">Unit Price</th>
                                </tr>
                            </thead>
                            <tbody id="unserved-history-sales-body">
                                <tr><td colspan="5" class="unserved-history-empty">Click an item to load sales history.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </section>
    </div>

    <div id="unserved-toast" class="unserved-toast hidden" role="status">
        <i data-lucide="circle-alert"></i>
        <span></span>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/unserved-report.js') }}?v={{ @filemtime(public_path('js/unserved-report.js')) ?: time() }}"></script>
@endpush
