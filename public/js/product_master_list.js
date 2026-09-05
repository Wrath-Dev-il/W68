/**
 * Product Master List Client Logic
 */

// Global State
let products = [];
let currentTab = "all"; // 'all', 'newly', 'low'
let filters = {
    search: {},
    modal: {
        category: "all",
        application: "all",
        status: "all"
    }
};

// Product Ledger modal state
let currentLedgerEntries = [];
let ledgerSearchFilters = {};

// Sort State
let currentSortColumn = null;
let currentSortDirection = 'asc';

// Image Carousel State
window.pendingProductImages = []; // Array of base64 strings
window.coverImageIndex = 0; // Index of the cover image
window.currentGalleryIndex = 0;

/**
 * Safely normalize a product image value for use in <img src="">
 * Supports: data:image/*, http/https URLs, relative/absolute file paths
 * Rejects: raw binary data (PNG magic bytes, IHDR/IDAT garbage)
 */
function normalizeProductImage(val) {
    if (!val || typeof val !== 'string') return null;
    const img = val.trim();
    if (!img) return null;

    // data:image/* → pass through
    if (img.startsWith('data:image/')) return img;
    // http:// or https:// → pass through
    if (/^https?:\/\//i.test(img)) return img;
    // Absolute paths like /storage/..., /uploads/..., /hatdog/public/...
    if (img.startsWith('/storage/') || img.startsWith('/uploads/') || img.startsWith('/hatdog/')) return img;
    // Relative paths with image extension
    if (/\.(png|jpg|jpeg|webp|gif|bmp|svg)(\?.*)?$/i.test(img)) return img;
    // Relative paths starting with storage/ or uploads/
    if (img.startsWith('storage/') || img.startsWith('uploads/')) return img;

    // Reject raw binary / PNG garbage (defense-in-depth)
    if (img.startsWith('PNG') || img.includes('IHDR') || img.includes('IDAT')) return null;

    return null;
}

// Initialize
window.addEventListener("DOMContentLoaded", () => {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Tab Handlers
    document.querySelectorAll(".prod-tab-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            switchTab(btn.getAttribute("data-tab"));
        });
    });

    setupEventListeners();

    const btnConfirmDelete = document.getElementById('btn-confirm-delete');
    if (btnConfirmDelete) {
        btnConfirmDelete.onclick = window.confirmDelete;
    }

    const btnConfirmUpdate = document.getElementById('btn-confirm-update');
    if (btnConfirmUpdate) {
        btnConfirmUpdate.onclick = window.confirmUpdateProduct;
    }

    const btnConfirmReorder = document.getElementById('btn-confirm-reorder');
    if (btnConfirmReorder) {
        btnConfirmReorder.onclick = window.confirmReorderLevel;
    }
    
    // Add Product Modal trigger reset
    const addProductBtn = document.querySelector('[onclick="toggleModal(\'add-product-modal\', true)"]');
    if (addProductBtn) {
        addProductBtn.addEventListener('click', () => {
            window.pendingProductImages = [];
            window.coverImageIndex = 0;
            window.renderPreviewGrid('add');
        });
    }

    // Initial fetch
    fetchProducts(1);
});

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function setupEventListeners() {
    // Column Searches with Debounce
    const debouncedFilter = debounce(() => fetchProducts(1), 300);
    
    document.querySelectorAll(".column-search-input").forEach(input => {
        input.addEventListener("input", (e) => {
            const col = e.target.getAttribute("data-col");
            filters.search[col] = e.target.value.toLowerCase().trim();
            debouncedFilter();
        });
    });

    // Product Ledger column searches (client-side, combined across all columns)
    document.querySelectorAll(".ledger-search-input").forEach(input => {
        input.addEventListener("input", (e) => {
            const col = e.target.getAttribute("data-ledger-col");
            const value = e.target.value.toLowerCase().trim();

            if (value) {
                ledgerSearchFilters[col] = value;
            } else {
                delete ledgerSearchFilters[col];
            }

            applyLedgerSearchFilters();
        });
    });

    // Add Image Logic
    const imageInput = document.getElementById("prod-image-input");
    if (imageInput) {
        imageInput.addEventListener("change", (e) => window.handleMultiImageUpload(e, 'add'));
    }

    const editImageInput = document.getElementById("edit-prod-image-input");
    if (editImageInput) {
        editImageInput.addEventListener("change", (e) => window.handleMultiImageUpload(e, 'edit'));
    }

    if (window.productMasterViewEditor) {
        document.querySelectorAll('[data-view-field]').forEach(input => {
            input.addEventListener('input', window.updateProductViewDirtyState);
            input.addEventListener('change', window.updateProductViewDirtyState);
        });

        document.querySelectorAll('.active-note-search-input').forEach(input => {
            input.addEventListener('input', () => {
                const col = input.getAttribute('data-active-note-col');
                const value = (input.value || '').toLowerCase().trim();
                if (value) window.activeNoteSearchFilters[col] = value;
                else delete window.activeNoteSearchFilters[col];
                window.applyActiveNoteSearchFilters();
            });
        });

        const viewImageInput = document.getElementById('view-edit-image-input');
        if (viewImageInput) {
            viewImageInput.addEventListener('change', e => window.handleMultiImageUpload(e, 'viewEdit'));
        }
    }
}

async function fetchProducts(page = 1) {
    const tbody = document.getElementById("product-tbody");
    if (tbody) {
        tbody.innerHTML = `
            <tr>
<td colspan="14" class="py-12 text-center text-slate-400 italic">
                    <div class="flex items-center justify-center space-x-2">
                        <i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i>
                        <span>Fetching products...</span>
                    </div>
                </td>
            </tr>
        `;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// Keyboard navigation: arrow keys scroll table horizontally, Ctrl+Arrow navigates form fields in modals
if (!window.__productMasterKeyboardNavBound) {
    window.__productMasterKeyboardNavBound = true;
    document.addEventListener('keydown', function(e) {
        const addModal = document.getElementById('add-product-modal');
        const editModal = document.getElementById('edit-product-modal');
        const modalOpen = (addModal && !addModal.classList.contains('hidden')) || (editModal && !editModal.classList.contains('hidden'));

        if (modalOpen && e.ctrlKey && ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
            e.preventDefault();
            const active = document.activeElement;
            const modal = addModal && !addModal.classList.contains('hidden') ? addModal : editModal;
            const form = modal.querySelector('form');
            const inputs = Array.from(form.querySelectorAll('input, select, textarea, button')).filter(el => !el.disabled && el.type !== 'hidden');
            const idx = inputs.indexOf(active);
            if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
                const next = idx + 1 < inputs.length ? inputs[idx + 1] : inputs[0];
                next.focus();
            } else {
                const prev = idx - 1 >= 0 ? inputs[idx - 1] : inputs[inputs.length - 1];
                prev.focus();
            }
            return;
        }

        if (!modalOpen && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
            const container = document.querySelector('.overflow-x-auto.custom-scrollbar');
            if (container) {
                e.preventDefault();
                container.scrollBy({ left: e.key === 'ArrowLeft' ? -100 : 100, behavior: 'smooth' });
            }
        }
    });
}

    try {
        const params = new URLSearchParams({
            page: page,
            perPage: 50,
            tab: currentTab
        });

        // Add search filters
        Object.entries(filters.search).forEach(([col, val]) => {
            if (val) params.append(`search[${col}]`, val);
        });

        // Add sort params
        if (currentSortColumn) {
            params.append('sort_by', currentSortColumn);
            params.append('sort_direction', currentSortDirection);
        }

        const response = await fetch(`${window.prodRoutes.fetch}?${params.toString()}`);
        if (!response.ok) {
            throw new Error(`Server returned ${response.status}`);
        }
        const result = await response.json();
        
        if (result.pagination) {
            products = result.pagination.data;
            renderProductTable();
            renderPagination(result.pagination);
            updateSortIndicators();
            
            // Update stats cards if present
            if (result.stats) {
                if (document.getElementById('card-count-total')) document.getElementById('card-count-total').innerText = result.stats.total;
                if (document.getElementById('card-count-newly')) document.getElementById('card-count-newly').innerText = result.stats.newly;
                if (document.getElementById('card-count-low')) document.getElementById('card-count-low').innerText = result.stats.low;
            }
        }
    } catch (error) {
        console.error('Error fetching products:', error);
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="14" class="py-12 text-center text-red-400 italic">Error loading products. Please try again.</td></tr>`;
        }
    }
}
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function normalizeApplicationText(value) {
    return String(value ?? '').replace(/\s+/g, ' ').trim();
}

function cleanApplicationPart(value) {
    return normalizeApplicationText(value)
        .replace(/^[|,;/\-\s]+/, '')
        .replace(/[|,;/\-\s]+$/, '')
        .trim();
}

function getApplicationValue(source, keys) {
    if (!source || typeof source !== 'object') return '';

    for (const key of keys) {
        if (source[key] !== undefined && source[key] !== null && String(source[key]).trim() !== '') {
            return String(source[key]).trim();
        }
    }

    return '';
}

function normalizeApplicationEntry(entry) {
    if (entry && typeof entry === 'object' && !Array.isArray(entry)) {
        const hasStructuredKeys = [
            'brand', 'car_brand', 'make', 'model', 'car_model', 'engine',
            'year_from', 'yearFrom', 'year_to', 'yearTo', 'date_from', 'date_to',
        ].some((key) => Object.prototype.hasOwnProperty.call(entry, key));

        if (!hasStructuredKeys && entry.application) {
            return parseApplicationSegment(entry.application);
        }

        return {
            brand: getApplicationValue(entry, ['brand', 'car_brand', 'make', 'Make', 'Brand']),
            model: getApplicationValue(entry, ['model', 'car_model', 'application', 'make_model', 'Model', 'Application']),
            engine: getApplicationValue(entry, ['engine', 'Engine']),
            yearFrom: getApplicationValue(entry, ['year_from', 'yearFrom', 'from', 'date_from', 'start_year', 'YearFrom']),
            yearTo: getApplicationValue(entry, ['year_to', 'yearTo', 'to', 'date_to', 'end_year', 'YearTo']),
        };
    }

    return parseApplicationSegment(String(entry ?? ''));
}

function tryParseApplicationJson(applicationValue) {
    if (Array.isArray(applicationValue)) return applicationValue;
    if (applicationValue && typeof applicationValue === 'object') return [applicationValue];

    const text = normalizeApplicationText(applicationValue);
    if (!/^[\[{]/.test(text)) return null;

    try {
        const parsed = JSON.parse(text);
        if (Array.isArray(parsed)) return parsed;
        if (parsed && typeof parsed === 'object') return [parsed];
    } catch (error) {
        return null;
    }

    return null;
}

function splitApplicationSegments(applicationStr) {
    const text = normalizeApplicationText(applicationStr);
    if (!text) return [];

    const segments = [];
    let current = '';

    for (let i = 0; i < text.length; i += 1) {
        const char = text[i];
        const next = text[i + 1] || '';
        const previous = text[i - 1] || '';

        if (char === '/' && /\s/.test(next) && previous !== '' && !/\s/.test(previous)) {
            if (current.trim()) segments.push(current.trim());
            current = '';
            while (i + 1 < text.length && /\s/.test(text[i + 1])) {
                i += 1;
            }
            continue;
        }

        current += char;
    }

    if (current.trim()) segments.push(current.trim());
    return segments;
}

function parseApplicationYear(yearText) {
    const cleaned = cleanApplicationPart(yearText).replace(/[–—]/g, '-');
    const match = cleaned.match(/(\d{4}|\d{2})(?:\s*-\s*(\d{4}|\d{2}|UP|PRESENT|CURRENT|NOW))?/i);

    return {
        yearFrom: match?.[1] || '',
        yearTo: match?.[2] || '',
    };
}

function looksLikeEngineToken(token) {
    const value = cleanApplicationPart(token);
    if (!value) return false;

    return /\d/.test(value)
        || /^[A-Z]+-[A-Z0-9]+$/i.test(value)
        || /^[A-Z]{1,4}\d+[A-Z0-9-]*$/i.test(value)
        || /^\d+[A-Z][A-Z0-9.-]*$/i.test(value);
}

function splitModelAndEngineFromNewOrder(text) {
    const words = cleanApplicationPart(text).split(/\s+/).filter(Boolean);
    if (words.length <= 1) {
        return { model: words.join(' '), engine: '' };
    }

    let engineStart = -1;
    for (let i = words.length - 1; i >= 1; i -= 1) {
        if (looksLikeEngineToken(words[i])) {
            engineStart = i;
        } else if (engineStart !== -1) {
            break;
        }
    }

    if (engineStart === -1) {
        return {
            model: words.slice(0, -1).join(' '),
            engine: words[words.length - 1] || '',
        };
    }

    return {
        model: cleanApplicationPart(words.slice(0, engineStart).join(' ')),
        engine: cleanApplicationPart(words.slice(engineStart).join(' ')),
    };
}

function splitDelimitedApplicationSegment(segment) {
    const parts = String(segment ?? '')
        .split(/\s*\|\s*/)
        .map(cleanApplicationPart)
        .filter(Boolean);

    if (parts.length < 3) return null;

    const firstWords = parts[0].split(/\s+/).filter(Boolean);
    const brand = cleanApplicationPart(firstWords.shift() || '');
    const model = cleanApplicationPart(firstWords.join(' '));
    const engine = cleanApplicationPart(parts[1]);
    const { yearFrom, yearTo } = parseApplicationYear(parts[2]);

    return { brand, model, yearFrom, yearTo, engine };
}

function parseApplicationSegment(segment) {
    const cleanSegment = cleanApplicationPart(segment);
    const delimited = splitDelimitedApplicationSegment(cleanSegment);
    if (delimited) return delimited;

    const yearMatches = [...cleanSegment.matchAll(/(?:\d{4}|'?\d{2})\s*[-–—]\s*(?:\d{4}|'?\d{2}|UP|PRESENT|CURRENT|NOW)|\b\d{4}\b/gi)];
    let brand = '', model = '', yearFrom = '', yearTo = '', engine = '';

    if (yearMatches.length > 0) {
        const yearMatch = yearMatches[yearMatches.length - 1];
        ({ yearFrom, yearTo } = parseApplicationYear(yearMatch[0]));

        const beforeYear = cleanApplicationPart(cleanSegment.substring(0, yearMatch.index));
        const afterYear = cleanApplicationPart(cleanSegment.substring(yearMatch.index + yearMatch[0].length));
        const beforeParts = beforeYear.split(/\s+/).filter(Boolean);

        if (beforeParts.length > 0) {
            brand = beforeParts[0];
            const remainingBeforeYear = cleanApplicationPart(beforeParts.slice(1).join(' '));

            if (afterYear) {
                // Old saved order: MAKE MODEL YEAR ENGINE
                model = cleanApplicationPart(remainingBeforeYear);
                engine = cleanApplicationPart(afterYear);
            } else {
                // New saved order: MAKE MODEL ENGINE YEAR
                const split = splitModelAndEngineFromNewOrder(remainingBeforeYear);
                model = cleanApplicationPart(split.model);
                engine = cleanApplicationPart(split.engine);
            }
        }
    } else {
        const words = cleanSegment.split(/\s+/).filter(Boolean);
        if (words.length >= 2) {
            brand = words[0];
            model = words.slice(1).join(' ');
        } else {
            brand = cleanSegment;
        }
    }

    return {
        brand: cleanApplicationPart(brand),
        model: cleanApplicationPart(model),
        yearFrom: cleanApplicationPart(yearFrom),
        yearTo: cleanApplicationPart(yearTo),
        engine: cleanApplicationPart(engine),
    };
}

// ── Column Sorting ──
window.handleSortClick = function(columnKey) {
    if (currentSortColumn === columnKey) {
        // Same column: cycle asc → desc → neutral
        if (currentSortDirection === 'asc') {
            currentSortDirection = 'desc';
        } else {
            // desc → neutral: clear sort
            currentSortColumn = null;
            currentSortDirection = 'asc';
        }
    } else {
        // New column, start ascending
        currentSortColumn = columnKey;
        currentSortDirection = 'asc';
    }
    fetchProducts(1);
};

function updateSortIndicators() {
    document.querySelectorAll('.sortable-header').forEach(th => {
        const key = th.getAttribute('data-sort-key');
        const indicator = th.querySelector('.sort-indicator');
        if (indicator) {
            if (key === currentSortColumn) {
                indicator.textContent = currentSortDirection === 'asc' ? ' \u2191' : ' \u2193';
                th.classList.add('sort-active');
            } else {
                indicator.textContent = ' \u2195';
                th.classList.remove('sort-active');
            }
        }
    });
}

function renderFloatingInfoCell(value, label, icon = 'info', cellClass = 'py-3 px-4 text-slate-600 align-middle', textClass = 'text-slate-600') {
    const displayValue = value === null || value === undefined || value === '' || value === '---' ? 'N/A' : value;
    const safeValue = escapeHtml(displayValue);
    const safeLabel = escapeHtml(label);

    return `
        <td class="${cellClass}">
            <div class="w-full break-words whitespace-normal line-clamp-2 leading-tight overflow-hidden text-xs relative group/tooltip" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; max-height: 2.5rem;" onmousemove="updateTooltipPos(event)">
                <span class="${textClass}">${safeValue}</span>
                <div class="fixed invisible group-hover/tooltip:visible opacity-0 group-hover/tooltip:opacity-100 transition-opacity duration-200 w-72 p-4 bg-slate-900 text-white text-[12px] rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.3)] z-[9999] pointer-events-none break-words whitespace-normal leading-relaxed border border-slate-700/50 backdrop-blur-md custom-tooltip-box">
                    <div class="flex items-center gap-2 mb-2 pb-2 border-b border-white/10">
                        <i data-lucide="${icon}" class="w-3.5 h-3.5 text-gold"></i>
                        <span class="font-black text-gold uppercase tracking-[0.2em] text-[10px]">${safeLabel}</span>
                    </div>
                    ${safeValue}
                </div>
            </div>
        </td>
    `;
}

function getProductRestockLevel(product) {
    if (!product || typeof product !== 'object') return null;

    const candidates = [
        product.restock_level,
        product.Re_order_level,
        product.re_order_level,
        product.Restock_Level
    ];

    for (const value of candidates) {
        if (value !== null && value !== undefined && value !== '') {
            const parsed = Number(value);
            return Number.isFinite(parsed) ? parsed : value;
        }
    }

    return null;
}

function formatLedgerCurrency(value) {
    const amount = parseFloat(value);
    return Number.isFinite(amount) && amount !== 0
        ? '\u20b1' + amount.toLocaleString(undefined, { minimumFractionDigits: 2 })
        : '---';
}

function resetLedgerSearchFilters() {
    ledgerSearchFilters = {};
    document.querySelectorAll('.ledger-search-input').forEach(input => {
        input.value = '';
    });
}

function ledgerSearchText(entry, column) {
    const dateStr = entry.date ? String(entry.date).split(' ')[0] : '';

    switch (column) {
        case 'type':
            return entry.transaction_type || '';
        case 'date':
            return dateStr;
        case 'transNum':
            return entry.transaction_number || '';
        case 'refNum':
            return entry.reference_number || '';
        case 'name':
            return entry.entity_name || '';
        case 'in':
            return String(parseFloat(entry.quantity_in) || 0);
        case 'out':
            return String(parseFloat(entry.quantity_out) || 0);
        case 'junk':
            return String(parseFloat(entry.junk) || 0);
        case 'stock':
            return String(parseFloat(entry.balance_stock) || 0);
        case 'oum':
            return entry.oum || '';
        case 'sell':
            return `${entry.price ?? ''} ${formatLedgerCurrency(entry.price)}`;
        case 'online':
            return `${entry.price_online ?? ''} ${formatLedgerCurrency(entry.price_online)}`;
        case 'cost':
            return `${entry.cost ?? ''} ${formatLedgerCurrency(entry.cost)}`;
        case 'remarks':
            return entry.remarks || '';
        default:
            return '';
    }
}

function renderLedgerEntries(entries) {
    const tbody = document.getElementById('ledger-tbody');
    const emptyState = document.getElementById('ledger-empty-state');
    if (!tbody) return;

    if (!entries.length) {
        tbody.innerHTML = '';
        if (emptyState) emptyState.classList.remove('hidden');
    } else {
        tbody.innerHTML = entries.map(entry => {
            const dateStr = entry.date ? String(entry.date).split(' ')[0] : '---';
            const remarks = entry.remarks || '---';

            return '<tr class="hover:bg-slate-50 transition-colors">' +
                '<td class="py-2.5 px-3 font-semibold whitespace-nowrap">' + escapeHtml(entry.transaction_type || '---') + '</td>' +
                '<td class="py-2.5 px-3 text-slate-500 whitespace-nowrap">' + escapeHtml(dateStr) + '</td>' +
                '<td class="py-2.5 px-3 font-mono text-slate-600 whitespace-nowrap">' + escapeHtml(entry.transaction_number || '---') + '</td>' +
                '<td class="py-2.5 px-3 font-mono text-slate-600 whitespace-nowrap">' + escapeHtml(entry.reference_number || '---') + '</td>' +
                '<td class="py-2.5 px-3 text-slate-700" style="min-width: 150px;">' + escapeHtml(entry.entity_name || '---') + '</td>' +
                '<td class="py-2.5 px-3 text-center font-bold text-emerald-600 whitespace-nowrap">' + (parseFloat(entry.quantity_in) || 0) + '</td>' +
                '<td class="py-2.5 px-3 text-center font-bold text-red-600 whitespace-nowrap">' + (parseFloat(entry.quantity_out) || 0) + '</td>' +
                '<td class="py-2.5 px-3 text-center font-bold whitespace-nowrap" style="color:#8B4513;">' + (parseFloat(entry.junk) || 0) + '</td>' +
                '<td class="py-2.5 px-3 text-center font-bold text-slate-800 border-x border-slate-100 whitespace-nowrap">' + (parseFloat(entry.balance_stock) || 0) + '</td>' +
                '<td class="py-2.5 px-3 text-slate-600 whitespace-nowrap">' + escapeHtml(entry.oum || '---') + '</td>' +
                '<td class="py-2.5 px-3 text-slate-600 whitespace-nowrap">' + formatLedgerCurrency(entry.price) + '</td>' +
                '<td class="py-2.5 px-3 text-slate-600 whitespace-nowrap">' + formatLedgerCurrency(entry.price_online) + '</td>' +
                '<td class="py-2.5 px-3 text-slate-600 whitespace-nowrap">' + formatLedgerCurrency(entry.cost) + '</td>' +
                '<td class="py-2.5 px-3 text-slate-500" style="min-width: 220px; white-space: normal; overflow-wrap: anywhere;" title="' + escapeHtml(remarks) + '">' + escapeHtml(remarks) + '</td>' +
            '</tr>';
        }).join('');

        if (emptyState) emptyState.classList.add('hidden');
    }

    const totalIn = entries.reduce((sum, entry) => sum + (parseFloat(entry.quantity_in) || 0), 0);
    const totalOut = entries.reduce((sum, entry) => sum + (parseFloat(entry.quantity_out) || 0), 0);
    const totalInEl = document.getElementById('ledger-total-in');
    const totalOutEl = document.getElementById('ledger-total-out');
    if (totalInEl) totalInEl.textContent = totalIn;
    if (totalOutEl) totalOutEl.textContent = totalOut;
}

function applyLedgerSearchFilters() {
    const activeFilters = Object.entries(ledgerSearchFilters)
        .filter(([, value]) => value !== '');

    if (!activeFilters.length) {
        renderLedgerEntries(currentLedgerEntries);
        return;
    }

    const filtered = currentLedgerEntries.filter(entry => {
        return activeFilters.every(([column, query]) => {
            return String(ledgerSearchText(entry, column))
                .toLowerCase()
                .includes(query);
        });
    });

    renderLedgerEntries(filtered);
}

function renderProductTable() {
    const tbody = document.getElementById("product-tbody");
    if (!tbody) return;

    if (products.length === 0) {
        tbody.innerHTML = `<tr><td colspan="14" class="py-12 text-center text-slate-400 italic">No products found matching your criteria.</td></tr>`;
        return;
    }

    tbody.innerHTML = products.map((product, index) => {
        let firstImg = null;
        if (product.Product_Picture) {
            try {
                const imgs = typeof product.Product_Picture === 'string' ? JSON.parse(product.Product_Picture) : product.Product_Picture;
                const raw = Array.isArray(imgs) && imgs.length > 0 ? imgs[0] : (typeof imgs === 'string' ? imgs : null);
                firstImg = normalizeProductImage(raw);
            } catch(e) { firstImg = normalizeProductImage(product.Product_Picture); }
        }

        const isSelected = product.is_selected_for_report || false;
        const restockValue = getProductRestockLevel(product);
        const restockLevel = restockValue === null ? null : Number(restockValue);
        const onHand = Number(product.on_hand ?? 0);
        const isLowStock = Number.isFinite(restockLevel) && onHand <= restockLevel;
        const lowStockRowClass = currentTab === 'all' && isLowStock ? 'product-low-stock-row' : '';

        return `
            <tr class="${lowStockRowClass} hover:bg-slate-50/60 table-row-animate transition-colors cursor-pointer group" onclick="if(!event.target.closest('button') && !event.target.closest('input')) viewProduct(${index})">
                <td class="py-3 px-4 text-center align-middle">
                    <input type="checkbox" class="rounded border-slate-300 product-checkbox" value="${product.id}" ${isSelected ? 'checked' : ''} onchange="window.handleRowSelect(this, ${product.id})">
                </td>
                <td class="py-3 px-4 text-center align-middle relative">
                    <div class="w-10 h-10 flex-shrink-0 mx-auto relative">
                        ${firstImg ? 
                            `<img src="${firstImg}" class="w-10 h-10 rounded-lg object-cover border border-slate-100 shadow-sm cursor-pointer hover:scale-110 transition-transform" onclick="event.stopPropagation(); ${window.productMasterViewEditor ? `viewProduct(${index})` : `window.openFullImagePreview(${index})`}">` :
                            `<div class="w-10 h-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center"><i data-lucide="image" class="w-4 h-4 text-slate-300"></i></div>`
                        }
                    </div>
                </td>
                <td class="py-3 px-4 font-bold text-slate-800 align-middle truncate relative group/tooltip" onmousemove="updateTooltipPos(event)">
                    ${product.product_code || ''}
                    ${product.product_code ? `
                    <div class="fixed invisible group-hover/tooltip:visible opacity-0 group-hover/tooltip:opacity-100 transition-opacity duration-200 w-72 p-4 bg-slate-900 text-white text-[12px] rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.3)] z-[9999] pointer-events-none break-words whitespace-normal leading-relaxed border border-slate-700/50 backdrop-blur-md custom-tooltip-box">
                        <div class="flex items-center gap-2 mb-2 pb-2 border-b border-white/10">
                            <i data-lucide="file-text" class="w-3.5 h-3.5 text-gold"></i>
                            <span class="font-black text-gold uppercase tracking-[0.2em] text-[10px]">Product Code</span>
                        </div>
                        ${escapeHtml(product.product_code)}
                    </div>
                    ` : ''}</td>
                ${renderFloatingInfoCell(product.position || product.Position, 'Position', 'map-pin', 'py-3 px-4 text-slate-600 align-middle truncate')}
                ${renderFloatingInfoCell(product.part_number, 'Part Number/Name', 'hash', 'py-3 px-4 text-slate-700 align-middle truncate', 'font-semibold text-slate-700')}
                ${renderFloatingInfoCell(product.description, 'Description', 'file-text', 'py-3 px-4 text-slate-500 align-middle')}
                ${renderFloatingInfoCell(product.application, 'Application', 'settings', 'py-3 px-4 text-slate-600 align-middle')}
                ${renderFloatingInfoCell(product.specification, 'Specification', 'info', 'py-3 px-4 text-slate-600 align-middle')}
                ${renderFloatingInfoCell(product.category, 'Brand', 'tag', 'py-3 px-4 text-slate-600 align-middle')}
                <td class="py-3 px-4 font-bold ${isLowStock ? 'text-red-500' : 'text-slate-700'} align-middle text-center">${product.on_hand}</td>
                <td class="py-3 px-4 font-bold text-slate-700 align-middle text-center">${restockLevel === null || !Number.isFinite(restockLevel) ? '---' : restockLevel}</td>
                <td class="py-3 px-4 font-mono text-slate-800 font-bold align-middle whitespace-nowrap">₱${parseFloat(product.selling_price).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td class="py-3 px-4 font-mono text-slate-800 font-bold align-middle whitespace-nowrap">${product.price_online != null ? '₱' + parseFloat(product.price_online).toLocaleString(undefined, {minimumFractionDigits: 2}) : '---'}</td>
                <td class="py-3 px-4 text-center align-middle">
                    <div class="flex items-center justify-center gap-2">
                        ${window.productMasterViewEditor ? '' : `
                            <button onclick='viewProduct(${index})' class="p-1.5 hover:bg-slate-100 rounded-lg text-slate-500 transition-colors" title="View">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                            <button onclick='editProduct(${index})' class="p-1.5 hover:bg-slate-100 rounded-lg text-maroon transition-colors" title="Edit">
                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                            </button>
                        `}
                        <button onclick="deleteProduct(${product.id})" class="p-1.5 hover:bg-slate-100 rounded-lg text-red-500 transition-colors" title="Delete">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function renderPagination(pagination) {
    const container = document.getElementById('pagination-container');
    const info = document.getElementById('pagination-info');
    if (!container) return;

    const from = (pagination.current_page - 1) * pagination.per_page + 1;
    const to = Math.min(pagination.current_page * pagination.per_page, pagination.total);
    if (info) info.innerText = `Showing ${pagination.total > 0 ? from : 0} to ${to} of ${pagination.total} entries`;

    let html = '';
    
    html += `
        <button onclick="fetchProducts(${pagination.current_page - 1})" 
            ${pagination.current_page === 1 ? 'disabled' : ''} 
            class="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-maroon hover:border-maroon disabled:opacity-50 disabled:cursor-not-allowed transition-all">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
        </button>
    `;

    for (let i = 1; i <= pagination.last_page; i++) {
        if (i === 1 || i === pagination.last_page || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
            html += `
                <button onclick="fetchProducts(${i})" 
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all ${i === pagination.current_page ? 'bg-maroon text-white shadow-md shadow-maroon/20' : 'text-slate-500 hover:bg-slate-100 border border-transparent'}">
                    ${i}
                </button>
            `;
        } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
            html += `<span class="text-slate-300 px-1">...</span>`;
        }
    }

    html += `
        <button onclick="fetchProducts(${pagination.current_page + 1})" 
            ${pagination.current_page === pagination.last_page ? 'disabled' : ''} 
            class="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-maroon hover:border-maroon disabled:opacity-50 disabled:cursor-not-allowed transition-all">
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </button>
    `;

    container.innerHTML = html;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.openFullImagePreview = function(imgs) {
    // Check if index was passed
    if (typeof imgs === 'number') {
        const product = products[imgs];
        if (product && product.Product_Picture) {
            imgs = product.Product_Picture;
        } else {
            imgs = [];
        }
    }

    let imagesToView = [];
    if (Array.isArray(imgs)) {
        imagesToView = imgs;
    } else if (typeof imgs === 'string' && imgs.startsWith('[')) {
        try { imagesToView = JSON.parse(imgs); } catch(e) { imagesToView = [imgs]; }
    } else if (imgs) {
        imagesToView = [imgs];
    }

    // Filter out invalid images before showing gallery
    imagesToView = imagesToView.filter(normalizeProductImage);

    if (imagesToView.length === 0) return;
    
    window.pendingProductImages = imagesToView;
    window.currentGalleryIndex = 0;
    
    updateGalleryUI();
    toggleModal('product-gallery-modal', true);
}

function updateGalleryUI() {
    const mainImg = document.getElementById('gallery-main-image');
    const thumbnails = document.getElementById('gallery-thumbnails');
    const prevBtn = document.getElementById('gallery-prev-btn');
    const nextBtn = document.getElementById('gallery-next-btn');

    if (!mainImg) return;

    mainImg.src = window.pendingProductImages[window.currentGalleryIndex];
    // Reset zoom when switching images (defer until image loads so naturalWidth is available)
    window.galleryZoom = 100;
    applyGalleryZoom(true); // true = initial layout only, don't use naturalWidth yet
    
    if (thumbnails) {
        thumbnails.innerHTML = '';
        window.pendingProductImages.forEach((img, idx) => {
            const thumb = document.createElement('div');
            thumb.className = `w-16 h-16 rounded-lg border-2 overflow-hidden cursor-pointer transition-all shrink-0 ${idx === window.currentGalleryIndex ? 'border-maroon ring-2 ring-maroon/20' : 'border-transparent opacity-50 hover:opacity-100'}`;
            thumb.innerHTML = `<img src="${img}" class="w-full h-full object-cover">`;
            thumb.onclick = () => {
                window.currentGalleryIndex = idx;
                updateGalleryUI();
            };
            thumbnails.appendChild(thumb);
        });
    }

    if (prevBtn) prevBtn.style.display = window.pendingProductImages.length > 1 ? 'block' : 'none';
    if (nextBtn) nextBtn.style.display = window.pendingProductImages.length > 1 ? 'block' : 'none';
}

window.nextGalleryImage = function() {
    window.currentGalleryIndex = (window.currentGalleryIndex + 1) % window.pendingProductImages.length;
    updateGalleryUI();
}

window.prevGalleryImage = function() {
    window.currentGalleryIndex = (window.currentGalleryIndex - 1 + window.pendingProductImages.length) % window.pendingProductImages.length;
    updateGalleryUI();
}

// --- Gallery Zoom Controls ---
window.galleryZoom = 100;

window.galleryZoomChange = function(value) {
    window.galleryZoom = parseFloat(value);
    applyGalleryZoom();
};

window.galleryZoomIn = function() {
    const slider = document.getElementById('gallery-zoom-slider');
    const val = Math.min(300, (window.galleryZoom || 100) + 20);
    window.galleryZoom = val;
    if (slider) slider.value = val;
    applyGalleryZoom();
};

window.galleryZoomOut = function() {
    const slider = document.getElementById('gallery-zoom-slider');
    const val = Math.max(50, (window.galleryZoom || 100) - 20);
    window.galleryZoom = val;
    if (slider) slider.value = val;
    applyGalleryZoom();
};

window.galleryZoomReset = function() {
    window.galleryZoom = 100;
    const slider = document.getElementById('gallery-zoom-slider');
    if (slider) slider.value = 100;
    applyGalleryZoom();
};

function applyGalleryZoom(deferIfNeeded) {
    const img = document.getElementById('gallery-main-image');
    const container = document.getElementById('gallery-image-container');
    const label = document.getElementById('gallery-zoom-label');
    if (!img) return;
    const zoom = window.galleryZoom || 100;
    
    if (zoom <= 100) {
        // Fit mode: constrain to viewport, keep natural size, no upscaling
        img.style.maxWidth = '90vw';
        img.style.maxHeight = '85vh';
        img.style.width = 'auto';
        img.style.height = 'auto';
        img.style.removeProperty('transform');
        if (container) {
            container.style.overflow = 'hidden';
            container.style.justifyContent = 'center';
            container.style.display = 'flex';
        }
        if (label) label.textContent = zoom + '%';
        // Clear any pending load handler
        img._zoomApplyOnLoad = false;
        return;
    }

    // Zoom above 100%: use explicit width based on natural resolution
    // This avoids CSS transform scale() which causes blurry upscaling
    const applyZoom = function() {
        const nw = img.naturalWidth || 0;
        if (nw > 0) {
            const zoomedW = Math.round((nw * zoom) / 100);
            const zoomedH = Math.round(((img.naturalHeight || 0) * zoom) / 100);
            img.style.maxWidth = 'none';
            img.style.maxHeight = 'none';
            img.style.width = zoomedW + 'px';
            img.style.height = 'auto';
            img.style.removeProperty('transform');
            if (container) {
                const cw = container.clientWidth;
                const ch = container.clientHeight;
                const needsScroll = zoomedW > cw || zoomedH > ch;
                container.style.overflow = needsScroll ? 'auto' : 'hidden';
                container.style.justifyContent = needsScroll ? 'flex-start' : 'center';
                container.style.alignItems = needsScroll ? 'flex-start' : 'center';
                container.style.display = 'flex';
            }
        } else {
            // Fallback: constraint-based, no transform scale
            img.style.maxWidth = (90 * zoom / 100) + 'vw';
            img.style.maxHeight = (85 * zoom / 100) + 'vh';
            img.style.width = 'auto';
            img.style.height = 'auto';
            img.style.removeProperty('transform');
            if (container) {
                container.style.overflow = 'auto';
                container.style.justifyContent = 'center';
                container.style.alignItems = 'center';
                container.style.display = 'flex';
            }
        }
        if (label) label.textContent = zoom + '%';
        img._zoomApplyOnLoad = false;
    };

    // If image hasn't loaded yet, defer zoom application
    if (deferIfNeeded && !img.complete) {
        img._zoomApplyOnLoad = true;
        // Set one-time load handler
        img.addEventListener('load', function onImgLoad() {
            if (img._zoomApplyOnLoad) applyZoom();
            img.removeEventListener('load', onImgLoad);
        }, { once: true });
        // Also set initial fit mode while loading
        img.style.maxWidth = '90vw';
        img.style.maxHeight = '85vh';
        img.style.width = 'auto';
        img.style.height = 'auto';
        img.style.removeProperty('transform');
        if (label) label.textContent = zoom + '%';
        return;
    }
    
    applyZoom();
}
// --- End Gallery Zoom Controls ---

window.handleMultiImageUpload = async function(e, mode = 'add') {
    const files = Array.from(e.target.files);
    const gridId = mode === 'add' ? "image-preview-grid" : (mode === 'viewEdit' ? "view-edit-image-grid" : "edit-image-preview-container");
    const grid = document.getElementById(gridId);
    if (!grid) return;

    const remainingSlots = 7 - window.pendingProductImages.length;
    const filesToProcess = files.slice(0, remainingSlots);

    for (const file of filesToProcess) {
        try {
            const compressedBase64 = await compressImage(file);
            window.pendingProductImages.push(compressedBase64);
        } catch (error) {
            console.error('Compression failed:', error);
        }
    }

    window.renderPreviewGrid(mode);
    if (mode === 'viewEdit') window.updateProductViewDirtyState();
    e.target.value = '';
}

async function compressImage(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = (event) => {
            const img = new Image();
            img.src = event.target.result;
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;
                const MAX_WIDTH = 800;
                const MAX_HEIGHT = 800;
                if (width > height) {
                    if (width > MAX_WIDTH) {
                        height *= MAX_WIDTH / width;
                        width = MAX_WIDTH;
                    }
                } else {
                    if (height > MAX_HEIGHT) {
                        width *= MAX_HEIGHT / height;
                        height = MAX_HEIGHT;
                    }
                }
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                resolve(canvas.toDataURL('image/jpeg', 0.7));
            };
            img.onerror = reject;
        };
        reader.onerror = reject;
    });
}

window.renderPreviewGrid = function(mode = 'add') {
    const gridId = mode === 'add' ? "image-preview-grid" : (mode === 'viewEdit' ? "view-edit-image-grid" : "edit-image-preview-container");
    const grid = document.getElementById(gridId);
    if (!grid) return;

    if (window.pendingProductImages.length === 0) {
        if (mode === 'add') {
            grid.innerHTML = `<div class="col-span-4 flex flex-col items-center justify-center h-full text-slate-300"><i data-lucide="image" class="w-10 h-10 mb-2"></i><span class="text-[10px] font-bold uppercase tracking-widest">Selected Images (0/7)</span></div>`;
        } else {
            grid.innerHTML = '';
        }
        if (typeof lucide !== 'undefined') lucide.createIcons();
        return;
    }

    grid.innerHTML = '';
    window.pendingProductImages.forEach((img, idx) => {
        const isCover = idx === window.coverImageIndex;
        const div = document.createElement('div');
        div.className = "relative group aspect-square rounded-xl overflow-hidden border bg-white shadow-sm cursor-pointer hover:ring-2 hover:ring-maroon/20 transition-all " + 
                        (mode === 'add' ? '' : 'w-24 h-24 ') + 
                        (isCover ? 'border-maroon ring-2 ring-maroon/50' : 'border-slate-200');
        
        div.innerHTML = `
            <img src="${img}" class="w-full h-full object-cover" onclick="window.openFullImagePreview(window.pendingProductImages[${idx}])">
            
            <!-- Cover Selection Checkbox -->
            <div class="absolute top-1 left-1 z-20">
                <label class="flex items-center justify-center w-5 h-5 bg-white/90 backdrop-blur-sm rounded-md border border-slate-200 cursor-pointer hover:bg-white transition-colors shadow-sm" title="Set as Cover">
                    <input type="checkbox" class="hidden cover-checkbox" ${isCover ? 'checked' : ''} onchange="window.setAsCover(${idx}, '${mode}')">
                    <i data-lucide="check" class="w-3 h-3 ${isCover ? 'text-maroon' : 'text-slate-200'}"></i>
                </label>
            </div>

            <!-- Remove Button -->
            <button type="button" onclick="window.removePendingImage(${idx}, '${mode}')" class="absolute top-1 right-1 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-20">
                <i data-lucide="x" class="w-3 h-3"></i>
            </button>
            
            ${isCover ? `<div class="absolute bottom-0 inset-x-0 bg-maroon/80 text-white text-[7px] font-black text-center py-0.5 uppercase tracking-tighter">Cover Image</div>` : ''}
        `;
        grid.appendChild(div);
    });
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.handleRowSelect = async function(checkbox, productId) {
    // Ignore if pending action already exists
    if (window._pendingCheckboxAction) return;

    const isSelected = checkbox.checked;
    const product = products.find(p => p.id === productId);

    // Revert immediately — will apply on confirm
    checkbox.checked = !isSelected;

    window._pendingCheckboxAction = {
        type: 'row',
        checkbox: checkbox,
        newState: isSelected,
        originalState: !isSelected,
        productId: productId,
        callback: async function() {
            if (product) product.is_selected_for_report = isSelected;
            try {
                const res = await fetch(window.prodRoutes.select, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ id: productId, is_selected: isSelected })
                });
                if (!res.ok) throw new Error('Selection update failed');
            } catch (error) {
                console.error('Error updating selection:', error);
                checkbox.checked = !isSelected;
                if (product) product.is_selected_for_report = !isSelected;
            }
        }
    };
    document.getElementById('confirm-checkbox-msg').textContent =
        'Are you sure you want to ' + (isSelected ? 'select' : 'deselect') + ' this product for the report?';
    toggleModal('confirm-checkbox-modal', true);
    if (window.lucide) lucide.createIcons();
}

window.handleSelectAll = async function(checkbox) {
    // Ignore if pending action already exists
    if (window._pendingCheckboxAction) return;

    const isSelected = checkbox.checked;
    const productCheckboxes = document.querySelectorAll('.product-checkbox');
    const originalProductStates = Array.from(productCheckboxes).map(cb => cb.checked);

    // Revert master and individual checkboxes immediately
    checkbox.checked = !isSelected;
    productCheckboxes.forEach((cb, i) => cb.checked = originalProductStates[i]);

    window._pendingCheckboxAction = {
        type: 'select-all',
        checkbox: checkbox,
        newState: isSelected,
        originalState: !isSelected,
        originalProductStates: originalProductStates,
        callback: async function() {
            productCheckboxes.forEach(cb => cb.checked = isSelected);
            products.forEach(p => p.is_selected_for_report = isSelected);
            try {
                const res = await fetch(window.prodRoutes.select, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ id: 'all', is_selected: isSelected })
                });
                if (!res.ok) throw new Error('Selection update failed');
            } catch (error) {
                console.error('Error updating all selections:', error);
                productCheckboxes.forEach((cb, i) => cb.checked = originalProductStates[i]);
                products.forEach((p, i) => p.is_selected_for_report = originalProductStates[i]);
            }
        }
    };
    document.getElementById('confirm-checkbox-msg').textContent =
        'Are you sure you want to ' + (isSelected ? 'select ALL' : 'deselect ALL') + ' products for the report?';
    toggleModal('confirm-checkbox-modal', true);
    if (window.lucide) lucide.createIcons();
}

window.openConfirmReorderModal = function() {
    const thresholdInput = document.getElementById('reorder-threshold-input');
    if (!thresholdInput) return;

    if (thresholdInput.value === '' || Number(thresholdInput.value) < 0) {
        thresholdInput.setCustomValidity('Please enter a valid reorder threshold.');
        thresholdInput.reportValidity();
        return;
    }
    thresholdInput.setCustomValidity('');

    const selectedIds = products
        .filter((product) => product.is_selected_for_report)
        .map((product) => product.id);

    if (selectedIds.length === 0) {
        alert('Please check at least one product before saving reorder level.');
        return;
    }

    toggleModal('confirm-reorder-modal', true);
}

window.confirmReorderLevel = async function() {
    const thresholdInput = document.getElementById('reorder-threshold-input');
    if (!thresholdInput) return;

    const selectedIds = products
        .filter((product) => product.is_selected_for_report)
        .map((product) => product.id);

    const threshold = Number(thresholdInput.value);
    if (!Number.isFinite(threshold) || threshold < 0) {
        thresholdInput.setCustomValidity('Please enter a valid reorder threshold.');
        thresholdInput.reportValidity();
        return;
    }
    thresholdInput.setCustomValidity('');

    if (selectedIds.length === 0) {
        alert('Please check at least one product before saving reorder level.');
        return;
    }

    try {
        const response = await fetch(window.prodRoutes.reorderLevel, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                threshold: threshold,
                ids: selectedIds
            })
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Failed to save reorder levels.');
        }

        const successText = document.getElementById('success-reorder-text');
        if (successText) {
            successText.textContent = `Reorder level ${threshold} applied to ${result.updated_count || selectedIds.length} selected product(s).`;
        }

        toggleModal('confirm-reorder-modal', false);
        toggleModal('success-reorder-modal', true);
    } catch (error) {
        console.error('Reorder level error:', error);
        alert(error.message || 'Failed to save reorder levels.');
    }
}

window.handleGenerateReport = async function() {
    const reportType = document.querySelector('input[name="report_type"]:checked').value;
    window._reportType = reportType;
    
    // Show filter modal IMMEDIATELY before fetch
    toggleModal('generate-report-modal', false);
    toggleModal('report-filter-modal', true);
    
    // Show loading state in all 4 combobox inputs
    ['description', 'brand', 'application', 'year'].forEach(key => {
        const input = document.getElementById('report-filter-' + key);
        const dropdown = document.getElementById('report-filter-' + key + '-dropdown');
        if (input) input.placeholder = 'Loading...';
        if (dropdown) dropdown.innerHTML = '<div class="report-filter-option disabled">Loading options...</div>';
    });
    
    try {
        const res = await fetch(window.prodRoutes.selectedFilters);
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        
        const filters = data.filters || {};
        if (filters.descriptions) initReportCombobox('description', filters.descriptions);
        if (filters.brands) initReportCombobox('brand', filters.brands);
        if (filters.applications) initReportCombobox('application', filters.applications);
        if (filters.years) initReportCombobox('year', filters.years);
    } catch (e) {
        console.error('Failed to load report filters:', e);
        // Show error in dropdowns
        ['description', 'brand', 'application', 'year'].forEach(key => {
            const input = document.getElementById('report-filter-' + key);
            const dropdown = document.getElementById('report-filter-' + key + '-dropdown');
            if (input) input.placeholder = 'All';
            if (dropdown) dropdown.innerHTML = '<div class="report-filter-option disabled">Failed to load options</div>';
        });
    }
};

window.selectReportFilterOption = function(key, value) {
    const input = document.getElementById('report-filter-' + key);
    const dropdown = document.getElementById('report-filter-' + key + '-dropdown');
    if (input) input.value = value;
    if (dropdown) dropdown.classList.add('hidden');
};

function initReportCombobox(key, options) {
    const input = document.getElementById('report-filter-' + key);
    const dropdown = document.getElementById('report-filter-' + key + '-dropdown');
    if (!input || !dropdown) return;
    
    // Reset placeholder
    input.placeholder = 'All ' + key.charAt(0).toUpperCase() + key.slice(1) + 's';
    if (key === 'year') input.placeholder = 'All Years';
    
    // Store options on the input element
    input._reportOptions = options;
    
    const renderDropdown = (filterText) => {
        const filtered = filterText
            ? options.filter(o => String(o).toLowerCase().includes(filterText.toLowerCase()))
            : options;
        
        if (filtered.length === 0) {
            dropdown.innerHTML = '<div class="report-filter-option disabled">No matching options</div>';
        } else {
            dropdown.innerHTML = filtered.map(o =>
                `<div class="report-filter-option" onclick="window.selectReportFilterOption('${key}', '${escapeHtml(String(o))}')">${escapeHtml(String(o))}</div>`
            ).join('');
        }
        dropdown.classList.remove('hidden');
    };
    
    // Event handlers
    input.addEventListener('focus', () => {
        renderDropdown(input.value);
    });
    
    input.addEventListener('input', () => {
        renderDropdown(input.value);
    });
    
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            dropdown.classList.add('hidden');
        }
    });
    
    // Close on outside click
    const closeHandler = (e) => {
        if (!input.parentElement.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    };
    document.addEventListener('click', closeHandler);
}

window.clearReportFilters = function() {
    ['description', 'brand', 'application', 'year'].forEach(key => {
        const input = document.getElementById('report-filter-' + key);
        if (input) input.value = '';
        const dropdown = document.getElementById('report-filter-' + key + '-dropdown');
        if (dropdown) dropdown.classList.add('hidden');
    });
};

window.applyReportFilters = function() {
    const reportType = window._reportType || 'catalog';
    const params = new URLSearchParams();
    
    const desc = document.getElementById('report-filter-description')?.value?.trim();
    const brand = document.getElementById('report-filter-brand')?.value?.trim();
    const app = document.getElementById('report-filter-application')?.value?.trim();
    const year = document.getElementById('report-filter-year')?.value?.trim();
    
    if (desc) params.set('description', desc);
    if (brand) params.set('brand', brand);
    if (app) params.set('application', app);
    if (year) params.set('year', year);
    
    const baseUrl = reportType === 'catalog' ? window.prodRoutes.catalog : window.prodRoutes.priceList;
    const qs = params.toString();
    window.open(qs ? baseUrl + '?' + qs : baseUrl, '_blank');
    
    toggleModal('report-filter-modal', false);
    toggleModal('generate-report-modal', false);
};

window.setAsCover = function(index, mode) {
    window.coverImageIndex = index;
    window.renderPreviewGrid(mode);
    if (mode === 'viewEdit') window.updateProductViewDirtyState();
}

window.removePendingImage = function(index, mode = 'add') {
    window.pendingProductImages.splice(index, 1);
    
    // Adjust cover index
    if (window.coverImageIndex === index) {
        window.coverImageIndex = 0; // Reset to first if deleted was cover
    } else if (window.coverImageIndex > index) {
        window.coverImageIndex--; // Shift left if deleted was before cover
    }
    
    window.renderPreviewGrid(mode);
    if (mode === 'viewEdit') window.updateProductViewDirtyState();
}

window.confirmAddProduct = async function() {
    if (!window.validateApplicationEntries('add')) {
        return;
    }

    // Collect application entries into hidden field before reading form
    window.collectApplicationString('add');
    window.collectPriceCodes('add');

    const form = document.getElementById('add-product-form');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Ensure cover image is at index 0
    let sortedImages = [...window.pendingProductImages];
    if (window.coverImageIndex > 0 && window.coverImageIndex < sortedImages.length) {
        const coverImg = sortedImages.splice(window.coverImageIndex, 1)[0];
        sortedImages.unshift(coverImg);
    }
    
    data.Product_Picture = JSON.stringify(sortedImages);

    try {
        const response = await fetch(window.prodRoutes.create, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        if (result.success) {
            window._skipUnsavedCheck = true;
            toggleModal('add-product-modal', false);
            toggleModal('confirm-add-modal', false);
            toggleModal('success-add-modal', true);
            window._skipUnsavedCheck = false;
            setTimeout(() => window.location.reload(), 1500);
        } else {
            alert(result.message || 'Failed to add product');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while adding the product');
    }
}

window.viewProduct = function(product) {
    if (typeof product === 'number') {
        product = products[product];
    }
    if (!product) return;

    document.getElementById("view-prod-code").textContent = product.product_code;
    const viewPricelistCode = document.getElementById("view-pricelist-code");
    if (viewPricelistCode) viewPricelistCode.value = product.pricelist_code || 'N/A';
    document.getElementById("view-prod-part").textContent = product.part_number;
    document.getElementById("view-prod-cat").textContent = product.category || 'N/A';
    document.getElementById("view-prod-spec").textContent = product.specification || 'N/A';
    document.getElementById("view-prod-desc").textContent = product.description || 'N/A';
    document.getElementById("view-prod-date").textContent = product.date_added;
    document.getElementById("view-prod-app").textContent = product.application || product.Application || 'N/A';
    // Populate price codes in view modal
    var viewPriceCodesEl = document.getElementById('view-prod-price-codes');
    if (viewPriceCodesEl) {
        var codes = product.price_codes;
        if (Array.isArray(codes) && codes.length > 0) {
            viewPriceCodesEl.innerHTML = codes.map(function(c) {
                // Handle both string (old format) and object (new format)
                var code = typeof c === 'string' ? c : (c.price_code || '');
                var price = typeof c === 'string' ? '' : (c.selling_price || '');
                var priceDisplay = price ? ' — ₱' + parseFloat(price).toLocaleString(undefined, {minimumFractionDigits: 2}) : '';
                return '<span class="inline-flex items-center px-2 py-0.5 rounded-md bg-maroon/5 border border-maroon/10 text-[10px] font-bold text-slate-700">' + escapeHtml(String(code)) + priceDisplay + '</span>';
            }).join('');
        } else {
            viewPriceCodesEl.innerHTML = '<span class="text-[10px] text-slate-400 italic">N/A</span>';
        }
    }
    document.getElementById("view-prod-pos").textContent = product.position || product.Position || 'N/A';
    
    if (document.getElementById("view-prod-hand")) {
        document.getElementById("view-prod-hand").textContent = product.on_hand;
    }
    const viewRestock = document.getElementById("view-prod-restock");
    if (viewRestock) {
        const restockLevel = getProductRestockLevel(product);
        viewRestock.textContent = restockLevel === null ? '---' : restockLevel;
    }
    
    const statusBadge = document.getElementById("view-prod-status-badge");
    if (statusBadge) {
        statusBadge.textContent = product.status;
        statusBadge.className = `mt-0.5 inline-flex px-1.5 py-0.5 rounded-full text-[9px] font-bold ${product.status === 'Newly' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-600'}`;
    }

    document.getElementById("view-prod-price").textContent = `₱${parseFloat(product.selling_price).toLocaleString(undefined, {minimumFractionDigits: 2})}`;
    document.getElementById("view-prod-cost").textContent = `₱${parseFloat(product.cost).toLocaleString(undefined, {minimumFractionDigits: 2})}`;

    var onlinePriceEl = document.getElementById('view-prod-online-price');
    if (onlinePriceEl) {
        var onlinePrice = (product.price_online !== null && product.price_online !== undefined && product.price_online !== '') ? parseFloat(product.price_online) : null;
        onlinePriceEl.textContent = onlinePrice !== null ? '₱' + onlinePrice.toLocaleString(undefined, {minimumFractionDigits: 2}) : '---';
    }
    
    // Set main image in view
    const viewImg = document.getElementById('view-prod-img');
    const noImg = document.getElementById('view-prod-no-img');
    
    window.pendingProductImages = []; // Reset for gallery
    
    if (product.Product_Picture) {
        let imgs = [];
        try {
            // Check if it's already an array or a JSON string
            if (Array.isArray(product.Product_Picture)) {
                imgs = product.Product_Picture;
            } else {
                imgs = JSON.parse(product.Product_Picture);
            }
        } catch(e) {
            imgs = [product.Product_Picture];
        }
        
        // Filter out invalid images
        imgs = (Array.isArray(imgs) ? imgs : [imgs]).filter(normalizeProductImage);
        
        if (imgs && imgs.length > 0) {
            viewImg.src = imgs[0];
            viewImg.classList.remove('hidden');
            if (noImg) noImg.classList.add('hidden');
            window.pendingProductImages = imgs; 
        } else {
            viewImg.classList.add('hidden');
            if (noImg) noImg.classList.remove('hidden');
        }
    } else {
        viewImg.classList.add('hidden');
        if (noImg) noImg.classList.remove('hidden');
    }

    toggleModal("view-product-modal", true);

    loadProductLedger(product.id);
}

function loadProductLedger(productId) {
    const tbody = document.getElementById('ledger-tbody');
    const emptyState = document.getElementById('ledger-empty-state');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="13" class="p-4 text-center text-slate-300 italic">Loading ledger...</td></tr>';
    if (emptyState) emptyState.classList.add('hidden');

    const url = (window.prodRoutes?.ledger || '/admin/masterlist/product/ledger/:id').replace(':id', productId);
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.ledger && data.ledger.length > 0) {
                const sorted = [...data.ledger].sort((a, b) => {
                    const dateDiff = new Date(b.date || 0) - new Date(a.date || 0);
                    if (dateDiff !== 0) return dateDiff;

                    return new Date(b.created_at || 0) - new Date(a.created_at || 0);
                });
                tbody.innerHTML = sorted.map(entry => {
                    const dateStr = entry.date ? entry.date.split(' ')[0] : '---';
                    return '<tr class="hover:bg-slate-50 transition-colors">' +
                        '<td class="py-2.5 px-3 font-semibold">' + (entry.transaction_type || '---') + '</td>' +
                        '<td class="py-2.5 px-3 text-slate-500">' + dateStr + '</td>' +
                        '<td class="py-2.5 px-3 font-mono text-slate-600">' + (entry.transaction_number || '---') + '</td>' +
                        '<td class="py-2.5 px-3 font-mono text-slate-600">' + (entry.reference_number || '---') + '</td>' +
                        '<td class="py-2.5 px-3 text-slate-700">' + (entry.entity_name || '---') + '</td>' +
                        '<td class="py-2.5 px-3 text-center font-bold text-emerald-600">' + (parseFloat(entry.quantity_in) || 0) + '</td>' +
                        '<td class="py-2.5 px-3 text-center font-bold text-red-600">' + (parseFloat(entry.quantity_out) || 0) + '</td>' +
                        '<td class="py-2.5 px-3 text-center font-bold" style="color:#8B4513;">' + (parseFloat(entry.junk) || 0) + '</td>' +
                        '<td class="py-2.5 px-3 text-center font-bold text-slate-800 border-x border-slate-100">' + (parseFloat(entry.balance_stock) || 0) + '</td>' +
                        '<td class="py-2.5 px-3 text-slate-600">' + (entry.oum || '---') + '</td>' +
                        '<td class="py-2.5 px-3 text-slate-600">' + (entry.price ? '₱' + parseFloat(entry.price).toLocaleString(undefined, {minimumFractionDigits: 2}) : '---') + '</td>' +
                        '<td class="py-2.5 px-3 text-slate-600">' + (entry.cost ? '₱' + parseFloat(entry.cost).toLocaleString(undefined, {minimumFractionDigits: 2}) : '---') + '</td>' +
                        '<td class="py-2.5 px-3 text-slate-400 italic max-w-[120px] truncate" title="' + (entry.remarks || '') + '">' + (entry.remarks || '---') + '</td>' +
                    '</tr>';
                }).join('');
                document.getElementById('ledger-total-in').textContent = data.total_in || 0;
                document.getElementById('ledger-total-out').textContent = data.total_out || 0;
                if (emptyState) emptyState.classList.add('hidden');

                var onlinePriceEl = document.getElementById('view-prod-online-price');
                if (onlinePriceEl) {
                    var latestEntry = sorted[0];
                    var onlinePrice = (latestEntry && latestEntry.price_online !== null && latestEntry.price_online !== undefined) ? parseFloat(latestEntry.price_online) : null;
                    onlinePriceEl.textContent = onlinePrice !== null ? '₱' + onlinePrice.toLocaleString(undefined, {minimumFractionDigits: 2}) : '---';
                }
            } else {
                tbody.innerHTML = '';
                document.getElementById('ledger-total-in').textContent = '0';
                document.getElementById('ledger-total-out').textContent = '0';
                if (emptyState) emptyState.classList.remove('hidden');
            }
        })
        .catch(err => {
            console.error('Error loading ledger:', err);
            tbody.innerHTML = '<tr><td colspan="13" class="p-4 text-center text-red-400 italic">Failed to load ledger.</td></tr>';
        });
}

loadProductLedger = function(productId) {
    const tbody = document.getElementById('ledger-tbody');
    const emptyState = document.getElementById('ledger-empty-state');
    if (!tbody) return;

    resetLedgerSearchFilters();
    currentLedgerEntries = [];

    tbody.innerHTML = '<tr><td colspan="14" class="p-4 text-center text-slate-300 italic">Loading ledger...</td></tr>';
    if (emptyState) emptyState.classList.add('hidden');

    const url = (window.prodRoutes?.ledger || '/admin/masterlist/product/ledger/:id').replace(':id', productId);
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success && Array.isArray(data.ledger) && data.ledger.length > 0) {
                currentLedgerEntries = [...data.ledger].sort((a, b) => {
                    const dateDiff = new Date(b.date || 0) - new Date(a.date || 0);
                    if (dateDiff !== 0) return dateDiff;

                    return new Date(b.created_at || 0) - new Date(a.created_at || 0);
                });

                renderLedgerEntries(currentLedgerEntries);

                const onlinePriceEl = document.getElementById('view-prod-online-price');
                if (onlinePriceEl) {
                    const latestEntry = currentLedgerEntries[0];
                    const onlinePrice = (latestEntry && latestEntry.price_online !== null && latestEntry.price_online !== undefined)
                        ? parseFloat(latestEntry.price_online)
                        : null;
                    onlinePriceEl.textContent = Number.isFinite(onlinePrice)
                        ? '\u20b1' + onlinePrice.toLocaleString(undefined, { minimumFractionDigits: 2 })
                        : '---';
                }
            } else {
                currentLedgerEntries = [];
                renderLedgerEntries([]);
            }
        })
        .catch(err => {
            console.error('Error loading ledger:', err);
            currentLedgerEntries = [];
            tbody.innerHTML = '<tr><td colspan="14" class="p-4 text-center text-red-400 italic">Failed to load ledger.</td></tr>';
            const totalInEl = document.getElementById('ledger-total-in');
            const totalOutEl = document.getElementById('ledger-total-out');
            if (totalInEl) totalInEl.textContent = '0';
            if (totalOutEl) totalOutEl.textContent = '0';
            if (emptyState) emptyState.classList.add('hidden');
        });
};


window.editProduct = function(product) {
    if (typeof product === 'number') {
        product = products[product];
    }
    if (!product) return;

    document.getElementById("edit-product-id").value = product.id;
    document.getElementById("edit-product-code").value = product.product_code;
    const editPricelistCode = document.getElementById("edit-pricelist-code");
    if (editPricelistCode) editPricelistCode.value = product.pricelist_code || '';
    document.getElementById("edit-part-number").value = product.part_number;
    document.getElementById("edit-category").value = product.category;
    document.getElementById("edit-specification").value = product.specification || '';
    
    // Handle dynamic tags in Edit Modal
    const dateAdded = product.date_added || product.Date_Added;
    document.getElementById("edit-date-added").value = dateAdded;
    document.getElementById("edit-date-display").textContent = new Date(dateAdded).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    
    const description = product.description || product.Description || '';
    document.getElementById("edit-description").value = description;
    document.getElementById("edit-unit").value = product.unit || '';
    
    const application = product.application || product.Application || '';
    window.parseApplicationString(application, 'edit');
    // Populate price codes in edit modal
    window.parsePriceCodes(product.price_codes, 'edit');
    
    const position = product.position || product.Position || '';
    document.getElementById("edit-position").value = position;
    
    document.getElementById("edit-on-hand").value = product.on_hand;
    const editRestockLevel = document.getElementById("edit-restock-level");
    if (editRestockLevel) {
        const restockLevel = getProductRestockLevel(product);
        editRestockLevel.value = restockLevel === null ? 0 : restockLevel;
    }
    document.getElementById("edit-selling-price").value = product.selling_price;
    document.getElementById("edit-cost").value = product.cost;
    document.getElementById("edit-price-online").value = product.price_online ?? 0;

    // Logic for NEW/OLD tag in Edit Modal
    const dateParsed = new Date(dateAdded);
    const threeMonthsAgo = new Date();
    threeMonthsAgo.setMonth(threeMonthsAgo.getMonth() - 3);
    
    const isNew = dateParsed > threeMonthsAgo;
    const statusTag = document.getElementById('edit-status-tag');
    const statusPulse = document.getElementById('edit-status-pulse');
    const statusText = document.getElementById('edit-status-text');
    const statusInput = document.getElementById('edit-status');

    if (isNew) {
        statusTag.className = "px-3 py-1.5 bg-emerald-50 text-emerald-600 text-[10px] font-bold rounded-full border border-emerald-100 flex items-center gap-1.5 shadow-sm";
        statusPulse.className = "w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse";
        statusText.textContent = "NEWLY ADDED";
        statusInput.value = "Newly";
    } else {
        statusTag.className = "px-3 py-1.5 bg-slate-100 text-slate-500 text-[10px] font-bold rounded-full border border-slate-200 flex items-center gap-1.5 shadow-sm";
        statusPulse.className = "w-1.5 h-1.5 rounded-full bg-slate-400";
        statusText.textContent = "OLD ITEM";
        statusInput.value = "Old";
    }
    
    // Handle images for edit
    window.pendingProductImages = [];
    window.coverImageIndex = 0; // Default to first image
    if (product.Product_Picture) {
        try {
            if (Array.isArray(product.Product_Picture)) {
                window.pendingProductImages = product.Product_Picture;
            } else {
                window.pendingProductImages = JSON.parse(product.Product_Picture);
            }
        } catch(e) {
            window.pendingProductImages = [product.Product_Picture];
        }
    }
    
    // Filter out invalid images (raw binary, garbage data)
    window.pendingProductImages = window.pendingProductImages.filter(normalizeProductImage);
    
    window.renderPreviewGrid('edit');

    toggleModal("edit-product-modal", true);
}

window.deleteProduct = function(id) {
    window.currentDeleteId = id;
    toggleModal("confirm-delete-modal", true);
}

window.confirmDelete = async function() {
    try {
        const deleteUrl = window.prodRoutes.delete.replace(':id', window.currentDeleteId);
        const response = await fetch(deleteUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        const result = await response.json();
        if (result.success) {
            toggleModal('confirm-delete-modal', false);
            toggleModal('success-delete-modal', true);
            setTimeout(() => window.location.reload(), 1500);
        } else {
            alert(result.message || 'Failed to delete product');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred during deletion');
    }
}

window.confirmUpdateProduct = async function() {
    if (!window.validateApplicationEntries('edit')) {
        return;
    }

    // Collect application entries into hidden field before reading form
    window.collectApplicationString('edit');
    window.collectPriceCodes('edit');

    const id = document.getElementById('edit-product-id').value;
    const form = document.getElementById('edit-product-form');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Ensure cover image is at index 0
    let sortedImages = [...window.pendingProductImages];
    if (window.coverImageIndex > 0 && window.coverImageIndex < sortedImages.length) {
        const coverImg = sortedImages.splice(window.coverImageIndex, 1)[0];
        sortedImages.unshift(coverImg);
    }
    
    data.Product_Picture = JSON.stringify(sortedImages);

    try {
        const updateUrl = window.prodRoutes.update.replace(':id', id);
        const response = await fetch(updateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.success) {
            const successText = document.getElementById('success-edit-text');
            if (successText) {
                const syncMessage = result.sync_message ? String(result.sync_message) : '';
                const syncStatus = result.sync_status ? String(result.sync_status) : '';
                let message = 'The product has been updated successfully.';
                if (syncMessage && syncStatus !== 'not_changed') {
                    message += ' ' + syncMessage;
                }
                successText.textContent = message;
            }
            window._skipUnsavedCheck = true;
            toggleModal('confirm-update-modal', false);
            toggleModal('edit-product-modal', false);
            toggleModal('success-edit-modal', true);
            window._skipUnsavedCheck = false;
            const needsLongerRead = result.sync_status && result.sync_status !== 'not_changed';
            setTimeout(() => window.location.reload(), needsLongerRead ? 3000 : 1500);
        } else {
            alert(result.message || 'Failed to update product');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred during update');
    }
}

// Global function to update tooltip position beside the cursor
window.updateTooltipPos = function(e) {
    const tooltips = document.querySelectorAll('.custom-tooltip-box');
    const x = e.clientX + 20; // 20px to the right of cursor
    const y = e.clientY + 20; // 20px below cursor
    
    tooltips.forEach(tooltip => {
        tooltip.style.left = x + 'px';
        tooltip.style.top = y + 'px';
    });
}

// Image Zoom Logic for View Modal
window.handleImageZoom = function(e) {
    const container = e.currentTarget;
    const img = container.querySelector('img');
    if (!img || img.classList.contains('hidden')) return;

    const rect = container.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;

    img.style.transformOrigin = `${x}% ${y}%`;
    img.style.transform = 'scale(2.5)';
}

window.resetImageZoom = function() {
    const img = document.getElementById('view-prod-img');
    if (img) {
        img.style.transform = 'scale(1)';
        img.style.transformOrigin = 'center center';
    }
}

window.openGalleryFromView = function() {
    if (window.pendingProductImages && window.pendingProductImages.length > 0) {
        window.openFullImagePreview(window.pendingProductImages);
    }
}

function switchTab(tabId) {
    currentTab = tabId;
    document.querySelectorAll(".prod-tab-btn").forEach(btn => {
        if (btn.getAttribute("data-tab") === tabId) {
            btn.className = "prod-tab-btn tab-active px-5 py-3 text-sm font-bold flex items-center space-x-2 transition-all duration-200 border-r border-slate-100";
        } else {
            btn.className = "prod-tab-btn tab-inactive px-5 py-3 text-sm font-semibold flex items-center space-x-2 transition-all duration-200 border-r border-slate-100";
        }
    });
    fetchProducts(1); // Re-fetch on tab switch
}

window._pendingCloseModal = null;
window._skipUnsavedCheck = false;

window.toggleModal = function(id, open) {
    const modal = document.getElementById(id);
    if (!modal) return;
    if (!open && !window._skipUnsavedCheck && (id === 'add-product-modal' || id === 'edit-product-modal')) {
        if (modal.classList.contains('hidden')) return;
        const form = modal.querySelector('form');
        if (form && Array.from(form.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), select, textarea')).some(el => el.value.trim() !== '')) {
            window._pendingCloseModal = id;
            toggleModal('confirm-discard-modal', true);
            return;
        }
    }
    if (open) modal.classList.remove("hidden");
    else modal.classList.add("hidden");
}

document.getElementById('btn-confirm-discard')?.addEventListener('click', function() {
    const targetId = window._pendingCloseModal;
    window._pendingCloseModal = null;
    toggleModal('confirm-discard-modal', false);

    if (!targetId) return;

    window._skipUnsavedCheck = true;

    if (targetId === 'view-product-modal') {
        // Confirmed discard from Product Ledger View: leave Edit Mode first,
        // then close the view modal without triggering the discard prompt again.
        window.setProductViewEditMode(false);
    }

    toggleModal(targetId, false);
    window._skipUnsavedCheck = false;
});

// ===== CHECKBOX CONFIRMATION STATE =====
window._pendingCheckboxAction = null;

window.cancelCheckboxAction = function() {
    const action = window._pendingCheckboxAction;
    if (!action) return;
    if (action.type === 'select-all' && action.originalProductStates) {
        document.querySelectorAll('.product-checkbox').forEach((cb, i) => {
            if (i < action.originalProductStates.length) cb.checked = action.originalProductStates[i];
        });
        products.forEach((p, i) => {
            if (i < action.originalProductStates.length) p.is_selected_for_report = action.originalProductStates[i];
        });
    }
    if (action.checkbox) {
        action.checkbox.checked = action.originalState;
    }
    window._pendingCheckboxAction = null;
};

window.confirmCheckboxAction = function() {
    const action = window._pendingCheckboxAction;
    if (!action) return;
    toggleModal('confirm-checkbox-modal', false);
    if (action.checkbox) {
        action.checkbox.checked = action.newState;
    }
    if (action.callback) action.callback();
    window._pendingCheckboxAction = null;
};

// ===== APPLICATION MULTI-ENTRY SYSTEM =====

/**
 * Creates the HTML template for one application entry block
 */
function createApplicationEntryHTML(data = {}, showRemove = true) {
    const brand = escapeHtml(data.brand || '');
    const model = escapeHtml(data.model || '');
    const yearFrom = escapeHtml(data.yearFrom || '');
    const yearTo = escapeHtml(data.yearTo || '');
    const engine = escapeHtml(data.engine || '');

    return `
        <div class="app-entry relative border border-slate-200 rounded-xl p-3 bg-slate-50/50 animate-fade-in">
            ${showRemove ? `<button type="button" onclick="window.removeApplicationEntry(this)" class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-colors shadow-md z-10" title="Remove entry">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>` : ''}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Car Brand <span class="text-red-500">*</span></label>
                    <input type="text" data-field="car_brand" value="${brand}" placeholder="e.g. Toyota" required class="block w-full px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm focus:border-maroon focus:ring-2 focus:ring-maroon/5 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Car Model <span class="text-red-500">*</span></label>
                    <input type="text" data-field="car_model" value="${model}" placeholder="e.g. Mirage" required class="block w-full px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm focus:border-maroon focus:ring-2 focus:ring-maroon/5 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Engine <span class="text-red-500">*</span></label>
                    <input type="text" data-field="engine" value="${engine}" placeholder="e.g. 1KD" required class="block w-full px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm focus:border-maroon focus:ring-2 focus:ring-maroon/5 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Year (From - To) <span class="text-red-500">*</span></label>
                    <div class="flex items-center gap-1.5">
                        <input type="text" data-field="year_from" value="${yearFrom}" placeholder="2020" maxlength="4" required class="block w-full px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm text-center focus:border-maroon focus:ring-2 focus:ring-maroon/5 outline-none transition-all">
                        <span class="text-slate-400 text-xs font-bold shrink-0">to</span>
                        <input type="text" data-field="year_to" value="${yearTo}" placeholder="2026" maxlength="4" required class="block w-full px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm text-center focus:border-maroon focus:ring-2 focus:ring-maroon/5 outline-none transition-all">
                    </div>
                </div>
            </div>
        </div>
    `;
}

window.validateApplicationEntries = function(mode = 'add') {
    const container = document.getElementById(`${mode}-application-entries`);
    if (!container) return true;

    const entries = container.querySelectorAll('.app-entry');
    let hasInvalid = false;

    entries.forEach((entry) => {
        const requiredInputs = entry.querySelectorAll('[data-field="car_brand"], [data-field="car_model"], [data-field="year_from"], [data-field="year_to"], [data-field="engine"]');
        requiredInputs.forEach((input) => {
            const value = (input.value || '').trim();
            if (!value) {
                input.setCustomValidity('This field is required.');
                hasInvalid = true;
            } else {
                input.setCustomValidity('');
            }
        });
    });

    if (hasInvalid) {
        const form = document.getElementById(mode === 'edit' ? 'edit-product-form' : 'add-product-form');
        if (form) form.reportValidity();
        return false;
    }

    return true;
}

// ==================== PRICE CODE FUNCTIONS ====================

window.addPriceCodeEntry = function(mode) {
    const container = document.getElementById(mode + '-price-codes-entries');
    if (!container) return;
    const entry = document.createElement('div');
    entry.className = 'pc-entry relative border border-slate-200 rounded-xl p-3 bg-slate-50/50';
    entry.innerHTML = `
        <div class="flex items-center gap-3">
            <div class="flex-[2]">
                <input type="text" data-field="price_code" placeholder="e.g. ABC-001"
                    class="block w-full px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm focus:border-maroon focus:ring-2 focus:ring-maroon/5 outline-none transition-all">
            </div>
            <div class="flex-1">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-slate-400 font-bold text-xs">₱</span>
                    <input type="number" data-field="selling_price" step="0.01" min="0" placeholder="0.00"
                        class="block w-full pl-6 pr-2.5 py-2 border border-slate-200 rounded-lg bg-white text-sm font-bold text-slate-700 focus:border-maroon focus:ring-2 focus:ring-maroon/5 outline-none transition-all">
                </div>
            </div>
            <button type="button" onclick="window.removePriceCodeEntry(this)"
                class="shrink-0 px-2.5 py-1.5 bg-red-50 text-red-500 text-[9px] font-bold rounded-lg hover:bg-red-100 transition-colors uppercase tracking-wider flex items-center gap-1">
                <i data-lucide="x" class="w-3 h-3"></i> Remove
            </button>
        </div>
    `;
    container.appendChild(entry);
    if (typeof lucide !== 'undefined' && lucide.createIcons) {
        lucide.createIcons();
    }
};

window.removePriceCodeEntry = function(btn) {
    const entry = btn.closest('.pc-entry');
    if (!entry) return;
    entry.style.opacity = '0';
    entry.style.transform = 'scale(0.95)';
    setTimeout(() => entry.remove(), 200);
};

window.collectPriceCodes = function(mode) {
    const containerId = mode + '-price-codes-entries';
    const hiddenId = mode + '-price-codes-hidden';
    const container = document.getElementById(containerId);
    const hidden = document.getElementById(hiddenId);
    if (!container || !hidden) return '[]';

    const entries = container.querySelectorAll('.pc-entry');
    const codes = [];

    entries.forEach(entry => {
        const codeInput = entry.querySelector('[data-field="price_code"]');
        const priceInput = entry.querySelector('[data-field="selling_price"]');
        if (!codeInput) return;
        const code = codeInput.value.trim();
        const sellingPrice = priceInput ? priceInput.value : '';
        if (code) {
            codes.push({
                price_code: code,
                selling_price: sellingPrice || '0.00'
            });
        }
    });

    // Remove duplicates by price_code while preserving order
    const seen = new Set();
    const unique = codes.filter(c => {
        const upper = c.price_code.toUpperCase();
        if (seen.has(upper)) return false;
        seen.add(upper);
        return true;
    });

    const result = JSON.stringify(unique);
    hidden.value = result;
    return result;
};

window.parsePriceCodes = function(data, mode) {
    const containerId = mode + '-price-codes-entries';
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = '';

    let codes = [];
    if (Array.isArray(data)) {
        codes = data;
    } else if (typeof data === 'string') {
        try { codes = JSON.parse(data); } catch(e) { codes = []; }
    }

    if (codes.length === 0) {
        window.addPriceCodeEntry(mode);
        return;
    }

    codes.forEach(function(entry) {
        // Handle both string (old format) and object (new format)
        const code = typeof entry === 'string' ? entry : (entry.price_code || '');
        const sellingPrice = typeof entry === 'string' ? '' : (entry.selling_price || '');

        const pce = document.createElement('div');
        pce.className = 'pc-entry relative border border-slate-200 rounded-xl p-3 bg-slate-50/50';
        pce.innerHTML = `
            <div class="flex items-center gap-3">
                <div class="flex-[2]">
                    <input type="text" data-field="price_code" value="${escapeHtml(String(code))}"
                        placeholder="e.g. ABC-001"
                        class="block w-full px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm focus:border-maroon focus:ring-2 focus:ring-maroon/5 outline-none transition-all">
                </div>
                <div class="flex-1">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-slate-400 font-bold text-xs">₱</span>
                        <input type="number" data-field="selling_price" step="0.01" min="0" value="${escapeHtml(String(sellingPrice))}"
                            placeholder="0.00"
                            class="block w-full pl-6 pr-2.5 py-2 border border-slate-200 rounded-lg bg-white text-sm font-bold text-slate-700 focus:border-maroon focus:ring-2 focus:ring-maroon/5 outline-none transition-all">
                    </div>
                </div>
                <button type="button" onclick="window.removePriceCodeEntry(this)"
                    class="shrink-0 px-2.5 py-1.5 bg-red-50 text-red-500 text-[9px] font-bold rounded-lg hover:bg-red-100 transition-colors uppercase tracking-wider flex items-center gap-1">
                    <i data-lucide="x" class="w-3 h-3"></i> Remove
                </button>
            </div>
        `;
        container.appendChild(pce);
    });

    if (typeof lucide !== 'undefined' && lucide.createIcons) {
        lucide.createIcons();
    }
};

/**
 * Add a new application entry block
 */
window.addApplicationEntry = function(mode = 'add') {
    const containerId = mode + '-application-entries';
    const container = document.getElementById(containerId);
    if (!container) return;

    const entryHTML = createApplicationEntryHTML({}, true);
    container.insertAdjacentHTML('beforeend', entryHTML);

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

/**
 * Remove an application entry block
 */
window.removeApplicationEntry = function(btn) {
    const entry = btn.closest('.app-entry');
    if (entry) {
        entry.style.opacity = '0';
        entry.style.transform = 'scale(0.95)';
        entry.style.transition = 'all 0.2s ease';
        setTimeout(() => entry.remove(), 200);
    }
}

/**
 * Collect all application entries and concatenate into the format:
 * BRAND MODEL ENGINE YEAR/ BRAND MODEL ENGINE YEAR
 */
window.collectApplicationString = function(mode = 'add') {
    const containerId = mode + '-application-entries';
    const hiddenId = mode + '-application-hidden';
    const container = document.getElementById(containerId);
    const hidden = document.getElementById(hiddenId);
    if (!container || !hidden) return '';

    const entries = container.querySelectorAll('.app-entry');
    const parts = [];

    entries.forEach(entry => {
        const brand = (entry.querySelector('[data-field="car_brand"]')?.value || '').trim().toUpperCase();
        const model = (entry.querySelector('[data-field="car_model"]')?.value || '').trim().toUpperCase();
        const yearFrom = (entry.querySelector('[data-field="year_from"]')?.value || '').trim();
        const yearTo = (entry.querySelector('[data-field="year_to"]')?.value || '').trim();
        const engine = (entry.querySelector('[data-field="engine"]')?.value || '').trim().toUpperCase();

        // Build year string
        let year = '';
        if (yearFrom && yearTo) {
            year = yearFrom + '-' + yearTo;
        } else if (yearFrom) {
            year = yearFrom;
        } else if (yearTo) {
            year = yearTo;
        }

        // Only include if at least one field has data
        if (brand || model || year || engine) {
            const segment = [brand, model, engine, year].filter(Boolean).join(' ');
            if (segment) parts.push(segment);
        }
    });

    const result = parts.join('/ ');
    hidden.value = result;
    return result;
}

/**
 * Parse an existing application string back into structured entry fields.
 * Supports old "BRAND MODEL YEAR ENGINE" and new "BRAND MODEL ENGINE YEAR" strings.
 */
window.parseApplicationString = function(applicationStr, mode = 'edit') {
    const containerId = mode + '-application-entries';
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = ''; // Clear existing entries

    if (!applicationStr || applicationStr.trim() === '') {
        // Add one empty entry
        container.innerHTML = createApplicationEntryHTML({}, false);
        if (typeof lucide !== 'undefined') lucide.createIcons();
        return;
    }

    const parsedJsonEntries = tryParseApplicationJson(applicationStr);
    const entries = parsedJsonEntries
        ? parsedJsonEntries.map(normalizeApplicationEntry)
        : splitApplicationSegments(applicationStr).map(normalizeApplicationEntry);

    entries.forEach((entry, index) => {
        const { brand, model, yearFrom, yearTo, engine } = entry;

        const showRemove = index > 0;
        container.insertAdjacentHTML('beforeend', createApplicationEntryHTML({
            brand, model, yearFrom, yearTo, engine
        }, showRemove));
    });

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// ===== SEARCH SPECIFIC MODAL =====
window.applySpecificSearch = function() {
    const code = document.getElementById('search-product-code')?.value?.trim() || '';
    const part = document.getElementById('search-part-number')?.value?.trim() || '';
    const category = document.getElementById('search-category')?.value?.trim() || '';
    const date = document.getElementById('search-date')?.value?.trim() || '';

    // Clear column search filters and set from Search Specific inputs
    filters.search = {};
    if (code) filters.search.productCode = code;
    if (part) filters.search.partNumber = part;
    if (category) filters.search.category = category;
    if (date) filters.search.dateAdded = date;

    toggleModal('search-specific-modal', false);
    fetchProducts(1);
};


// ============================================================================
// SPECIAL PRODUCT MASTER: VIEW-FIRST INLINE EDITOR + ACTIVE SN/PN
// ============================================================================
window.currentViewProduct = null;
window.currentViewProductIndex = -1;
window.viewProductEditMode = false;
window.viewProductOriginalData = null;
window.viewProductEditBaseline = '';
window.currentViewImageIndex = 0;
window.activeProductNotes = [];
window.activeNoteSearchFilters = {};
window._viewPictureManagerSnapshot = null;

function cloneProductMasterValue(value) {
    try { return JSON.parse(JSON.stringify(value)); } catch (e) { return value; }
}

function productImagesFromValue(value) {
    if (!value) return [];
    let images = value;
    if (!Array.isArray(images)) {
        try { images = JSON.parse(images); } catch (e) { images = [images]; }
    }
    return (Array.isArray(images) ? images : [images]).map(normalizeProductImage).filter(Boolean);
}

function renderViewPriceCodes(codes) {
    const el = document.getElementById('view-prod-price-codes');
    if (!el) return;
    const list = Array.isArray(codes) ? codes : [];
    if (!list.length) {
        el.innerHTML = '<span class="text-[10px] text-slate-400 italic">No pricing code configured.</span>';
        return;
    }
    el.innerHTML = list.map(c => {
        const code = typeof c === 'string' ? c : (c.price_code || '');
        const price = typeof c === 'string' ? null : Number(c.selling_price || 0);
        const priceText = Number.isFinite(price) ? ' — ₱' + price.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
        return '<span class="inline-flex items-center px-2 py-1 rounded-md bg-maroon/5 border border-maroon/10 text-[10px] font-bold text-slate-700">' + escapeHtml(String(code)) + escapeHtml(priceText) + '</span>';
    }).join('');
}

function setViewFieldValue(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    if ('value' in el) el.value = value ?? '';
    else el.textContent = value ?? '';
}

function renderCurrentViewProduct(product) {
    if (!product) return;
    setViewFieldValue('view-prod-code', product.product_code || '');
    setViewFieldValue('view-pricelist-code', product.pricelist_code || '');
    setViewFieldValue('view-prod-part', product.part_number || '');
    setViewFieldValue('view-prod-cat', product.category || '');
    setViewFieldValue('view-prod-spec', product.specification || '');
    setViewFieldValue('view-prod-desc', product.description || '');
    setViewFieldValue('view-prod-app', product.application || product.Application || '');
    setViewFieldValue('view-prod-pos', product.position || product.Position || '');
    setViewFieldValue('view-prod-unit', product.unit || '');
    setViewFieldValue('view-prod-hand', Number(product.on_hand || 0));
    const restock = getProductRestockLevel(product);
    setViewFieldValue('view-prod-restock', restock === null ? 0 : restock);
    setViewFieldValue('view-prod-price', Number(product.selling_price || 0).toFixed(2));
    setViewFieldValue('view-prod-online-price', Number(product.price_online || 0).toFixed(2));
    setViewFieldValue('view-prod-cost', Number(product.cost || 0).toFixed(2));

    const dateEl = document.getElementById('view-prod-date');
    if (dateEl) dateEl.textContent = product.date_added || product.Date_Added || '---';
    const statusBadge = document.getElementById('view-prod-status-badge');
    if (statusBadge) {
        const status = product.status || 'Old';
        statusBadge.textContent = status;
        statusBadge.className = 'mt-1 inline-flex px-2 py-1 rounded-full text-[9px] font-bold ' + (status === 'Newly' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-600');
    }
    renderViewPriceCodes(product.price_codes || []);
    window.renderViewProductImage();
}

window.renderViewProductImage = function() {
    const img = document.getElementById('view-prod-img');
    const noImg = document.getElementById('view-prod-no-img');
    const prev = document.getElementById('view-image-prev-btn');
    const next = document.getElementById('view-image-next-btn');
    const count = document.getElementById('view-image-counter');
    const images = window.pendingProductImages || [];

    if (window.currentViewImageIndex >= images.length) window.currentViewImageIndex = Math.max(0, images.length - 1);
    if (images.length) {
        if (img) { img.src = images[window.currentViewImageIndex]; img.classList.remove('hidden'); }
        if (noImg) noImg.classList.add('hidden');
    } else {
        if (img) img.classList.add('hidden');
        if (noImg) noImg.classList.remove('hidden');
    }
    const showSlide = !window.viewProductEditMode && images.length > 1;
    if (prev) prev.classList.toggle('hidden', !showSlide);
    if (next) next.classList.toggle('hidden', !showSlide);
    if (count) count.textContent = images.length ? ((window.currentViewImageIndex + 1) + ' / ' + images.length) : '0 / 0';
};

window.slideViewProductImage = function(direction) {
    const images = window.pendingProductImages || [];
    if (window.viewProductEditMode || images.length < 2) return;
    window.currentViewImageIndex = (window.currentViewImageIndex + direction + images.length) % images.length;
    window.renderViewProductImage();
};

window.handleViewProductPictureClick = function(event) {
    if (!window.productMasterViewEditor) return;
    if (window.viewProductEditMode) {
        if (event) event.stopPropagation();
        window.openViewPictureManager();
    } else if (window.pendingProductImages.length) {
        window.openGalleryFromView();
    }
};

window.viewProduct = function(product) {
    if (typeof product === 'number') {
        window.currentViewProductIndex = product;
        product = products[product];
    } else {
        window.currentViewProductIndex = products.findIndex(p => String(p.id) === String(product?.id));
    }
    if (!product) return;

    window.currentViewProduct = cloneProductMasterValue(product);
    window.viewProductOriginalData = cloneProductMasterValue(product);
    window.pendingProductImages = productImagesFromValue(product.Product_Picture);
    window.coverImageIndex = 0;
    window.currentViewImageIndex = 0;
    window.activeNoteSearchFilters = {};
    document.querySelectorAll('.active-note-search-input').forEach(el => el.value = '');

    window.setProductViewEditMode(false);
    window.switchProductDetailTab('history');
    renderCurrentViewProduct(window.currentViewProduct);
    toggleModal('view-product-modal', true);
    loadProductLedger(product.id);
    window.loadActiveProductNotes(product.id);
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

function editableViewElements() {
    return Array.from(document.querySelectorAll('#view-product-edit-form [data-view-field]'));
}

window.setProductViewEditMode = function(enabled) {
    window.viewProductEditMode = !!enabled;
    editableViewElements().forEach(el => {
        el.readOnly = !enabled;
        el.classList.toggle('border-transparent', !enabled);
        el.classList.toggle('bg-transparent', !enabled);
        el.classList.toggle('border-maroon/30', enabled);
        el.classList.toggle('bg-white', enabled);
        el.classList.toggle('focus:ring-2', enabled);
        el.classList.toggle('focus:ring-maroon/10', enabled);
    });

    const editBtn = document.getElementById('view-modal-edit-btn');
    const badge = document.getElementById('view-edit-mode-badge');
    const cancelBtn = document.getElementById('view-cancel-edit-btn');
    const saveBtn = document.getElementById('view-save-edit-btn');
    const editActionsSeparator = document.getElementById('view-edit-actions-separator');
    const priceBtn = document.getElementById('view-price-code-manage-btn');
    const pictureOverlay = document.getElementById('view-picture-edit-overlay');
    const pictureHint = document.getElementById('view-picture-edit-hint');
    const galleryBtn = document.getElementById('view-full-gallery-btn');

    if (editBtn) editBtn.classList.toggle('hidden', enabled);
    if (badge) {
        badge.classList.remove('hidden');
        badge.classList.toggle('invisible', !enabled);
    }
    if (cancelBtn) cancelBtn.classList.toggle('hidden', !enabled);
    if (saveBtn) saveBtn.classList.toggle('hidden', !enabled);
    if (editActionsSeparator) editActionsSeparator.classList.toggle('hidden', !enabled);
    if (priceBtn) priceBtn.classList.toggle('hidden', !enabled);
    if (pictureOverlay) { pictureOverlay.classList.toggle('hidden', !enabled); pictureOverlay.classList.toggle('flex', enabled); }
    if (pictureHint) pictureHint.classList.toggle('hidden', !enabled);
    if (galleryBtn) galleryBtn.classList.toggle('hidden', enabled);

    window.renderViewProductImage();
    window.updateProductViewDirtyState();
};

window.activateProductViewEditMode = function() {
    if (!window.currentViewProduct) return;
    window.viewProductOriginalData = cloneProductMasterValue(window.currentViewProduct);
    window.viewProductEditBaseline = window.captureProductViewEditState();
    window.setProductViewEditMode(true);
};

window.captureProductViewEditState = function() {
    const state = {};
    editableViewElements().forEach(el => { state[el.getAttribute('data-view-field')] = String(el.value ?? ''); });
    state.images = [...(window.pendingProductImages || [])];
    state.coverImageIndex = Number(window.coverImageIndex || 0);
    return JSON.stringify(state);
};

window.updateProductViewDirtyState = function() {
    const saveBtn = document.getElementById('view-save-edit-btn');
    const msg = document.getElementById('view-edit-dirty-message');
    if (!window.viewProductEditMode) {
        if (saveBtn) saveBtn.disabled = true;
        if (msg) msg.classList.add('hidden');
        return false;
    }
    const dirty = window.captureProductViewEditState() !== window.viewProductEditBaseline;
    if (saveBtn) saveBtn.disabled = !dirty;
    if (msg) msg.classList.toggle('hidden', !dirty);
    return dirty;
};

window.cancelProductViewEditMode = function() {
    if (!window.currentViewProduct) return;
    const preservedPriceCodes = cloneProductMasterValue(window.currentViewProduct.price_codes || []);
    window.currentViewProduct = cloneProductMasterValue(window.viewProductOriginalData || window.currentViewProduct);
    // Pricing codes can be saved independently while edit mode is active. Never roll those persisted changes back in the UI.
    window.currentViewProduct.price_codes = preservedPriceCodes;
    window.pendingProductImages = productImagesFromValue(window.currentViewProduct.Product_Picture);
    window.coverImageIndex = 0;
    window.currentViewImageIndex = 0;
    renderCurrentViewProduct(window.currentViewProduct);
    window.setProductViewEditMode(false);
};

window.closeProductViewModal = function() {
    if (window.viewProductEditMode && !window._skipUnsavedCheck) {
        // Reuse the existing Discard Changes modal whenever the user tries
        // to close Product Ledger View while Edit Mode is active.
        window._pendingCloseModal = 'view-product-modal';
        toggleModal('confirm-discard-modal', true);
        return;
    }

    window.setProductViewEditMode(false);
    toggleModal('view-product-modal', false);
};

window.saveProductViewChanges = async function() {
    if (!window.currentViewProduct || !window.viewProductEditMode || !window.updateProductViewDirtyState()) return;
    const saveBtn = document.getElementById('view-save-edit-btn');
    if (saveBtn) saveBtn.disabled = true;

    let sortedImages = [...(window.pendingProductImages || [])];
    if (window.coverImageIndex > 0 && window.coverImageIndex < sortedImages.length) {
        const cover = sortedImages.splice(window.coverImageIndex, 1)[0];
        sortedImages.unshift(cover);
    }

    const original = window.currentViewProduct;
    const get = id => document.getElementById(id)?.value ?? '';
    const payload = {
        product_code: get('view-prod-code').trim(),
        pricelist_code: get('view-pricelist-code').trim(),
        part_number: get('view-prod-part').trim(),
        specification: get('view-prod-spec').trim(),
        category: get('view-prod-cat').trim(),
        description: get('view-prod-desc').trim(),
        application: get('view-prod-app').trim(),
        position: get('view-prod-pos').trim(),
        unit: get('view-prod-unit').trim(),
        on_hand: Number(original.on_hand || 0),
        Re_order_level: Math.max(0, Number(get('view-prod-restock') || 0)),
        status: original.status || 'Old',
        selling_price: Math.max(0, Number(get('view-prod-price') || 0)),
        price_online: Math.max(0, Number(get('view-prod-online-price') || 0)),
        cost: Number(original.cost || 0),
        date_added: original.date_added || original.Date_Added,
        Product_Picture: JSON.stringify(sortedImages)
    };

    if (!payload.product_code || !payload.category) {
        alert('Product Code and Brand are required.');
        window.updateProductViewDirtyState();
        return;
    }

    try {
        const url = window.prodRoutes.update.replace(':id', original.id);
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Failed to update product');

        const saved = { ...original, ...(result.product || {}), ...payload };
        saved.Product_Picture = sortedImages;
        saved.price_codes = cloneProductMasterValue(window.currentViewProduct.price_codes || []);
        saved.restock_level = saved.Re_order_level;
        window.pendingProductImages = [...sortedImages];
        window.coverImageIndex = 0;
        window.currentViewImageIndex = 0;
        window.currentViewProduct = cloneProductMasterValue(saved);
        window.viewProductOriginalData = cloneProductMasterValue(saved);
        if (window.currentViewProductIndex >= 0 && products[window.currentViewProductIndex]) {
            products[window.currentViewProductIndex] = cloneProductMasterValue(saved);
        } else {
            const idx = products.findIndex(p => String(p.id) === String(saved.id));
            if (idx >= 0) products[idx] = cloneProductMasterValue(saved);
        }
        renderProductTable();
        renderCurrentViewProduct(window.currentViewProduct);
        window.viewProductEditBaseline = window.captureProductViewEditState();

        // Saving is complete: immediately leave Edit Mode so the user cannot
        // accidentally continue editing saved data behind the success message.
        window.setProductViewEditMode(false);

        const successText = document.getElementById('view-edit-success-text');
        if (successText) {
            successText.textContent = result.sync_message && result.sync_status !== 'not_changed'
                ? 'Product saved. ' + result.sync_message
                : 'The product changes were saved successfully.';
        }
        toggleModal('view-edit-success-modal', true);
    } catch (error) {
        console.error(error);
        alert(error.message || 'Unable to save product changes.');
        window.updateProductViewDirtyState();
    }
};

window.closeViewEditSuccess = function() {
    toggleModal('view-edit-success-modal', false);
    // A successful save returns Product Ledger View to normal View Mode.
    window.setProductViewEditMode(false);
};

window.switchProductDetailTab = function(tab) {
    const history = document.getElementById('product-history-panel');
    const active = document.getElementById('product-active-notes-panel');
    const historyBtn = document.getElementById('product-history-tab-btn');
    const activeBtn = document.getElementById('product-active-notes-tab-btn');
    const isActive = tab === 'active-notes';
    if (history) history.classList.toggle('hidden', isActive);
    if (active) active.classList.toggle('hidden', !isActive);
    if (historyBtn) historyBtn.className = 'product-detail-tab px-4 py-2 rounded-lg text-[10px] font-black uppercase tracking-wider flex items-center gap-2 transition-all ' + (!isActive ? 'bg-maroon text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-maroon/30 hover:text-maroon');
    if (activeBtn) activeBtn.className = 'product-detail-tab px-4 py-2 rounded-lg text-[10px] font-black uppercase tracking-wider flex items-center gap-2 transition-all ' + (isActive ? 'bg-maroon text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-maroon/30 hover:text-maroon');
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.loadActiveProductNotes = async function(productId) {
    const tbody = document.getElementById('active-notes-tbody');
    if (!tbody || !window.prodRoutes?.activeNotes) return;
    tbody.innerHTML = '<tr><td colspan="7" class="py-12 text-center text-slate-300 italic">Loading active SN/PN...</td></tr>';
    try {
        const response = await fetch(window.prodRoutes.activeNotes.replace(':id', productId));
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Failed to load active notes');
        window.activeProductNotes = Array.isArray(result.rows) ? result.rows : [];
        const count = document.getElementById('active-notes-count');
        if (count) count.textContent = window.activeProductNotes.length;
        window.applyActiveNoteSearchFilters();
    } catch (error) {
        console.error(error);
        window.activeProductNotes = [];
        tbody.innerHTML = '<tr><td colspan="7" class="py-12 text-center text-red-400 italic">Failed to load Active SN/PN.</td></tr>';
    }
};

function activeNoteColumnText(row, col) {
    const map = {
        number: [row.type, row.number].filter(Boolean).join(' '),
        date: row.date,
        in: row.quantity_in,
        out: row.quantity_out,
        name: row.name,
        price: row.price,
        cost: row.cost
    };
    return String(map[col] ?? '');
}

window.applyActiveNoteSearchFilters = function() {
    const filters = Object.entries(window.activeNoteSearchFilters || {}).filter(([,v]) => v !== '');
    const rows = (window.activeProductNotes || []).filter(row => filters.every(([col, query]) => activeNoteColumnText(row, col).toLowerCase().includes(query)));
    window.renderActiveProductNotes(rows);
};

window.renderActiveProductNotes = function(rows) {
    const tbody = document.getElementById('active-notes-tbody');
    const empty = document.getElementById('active-notes-empty-state');
    if (!tbody) return;
    if (!rows.length) {
        tbody.innerHTML = '';
        if (empty) { empty.classList.remove('hidden'); empty.classList.add('flex'); }
        return;
    }
    if (empty) { empty.classList.add('hidden'); empty.classList.remove('flex'); }
    tbody.innerHTML = rows.map(row => {
        const isPurchase = row.type === 'PN';
        const badgeClass = isPurchase ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-red-50 text-red-700 border-red-100';
        const money = value => value === null || value === undefined || value === '' ? '---' : '₱' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return '<tr class="hover:bg-slate-50/70 transition-colors">' +
            '<td class="py-3 px-3"><span class="inline-flex mr-2 px-1.5 py-0.5 rounded border text-[8px] font-black ' + badgeClass + '">' + escapeHtml(row.type) + '</span><span class="font-mono font-bold text-slate-700">' + escapeHtml(row.number || '---') + '</span></td>' +
            '<td class="py-3 px-3 text-slate-500">' + escapeHtml(row.date || '---') + '</td>' +
            '<td class="py-3 px-3 text-center font-black text-emerald-700">' + (Number(row.quantity_in) || 0) + '</td>' +
            '<td class="py-3 px-3 text-center font-black text-red-700">' + (Number(row.quantity_out) || 0) + '</td>' +
            '<td class="py-3 px-3 font-semibold text-slate-700">' + escapeHtml(row.name || '---') + '</td>' +
            '<td class="py-3 px-3 font-mono text-slate-700">' + money(row.price) + '</td>' +
            '<td class="py-3 px-3 font-mono text-slate-700">' + money(row.cost) + '</td>' +
            '</tr>';
    }).join('');
};

// ----- Price Code Setup -----
window.openViewPriceCodeSetup = function() {
    if (!window.viewProductEditMode || !window.currentViewProduct) return;
    window._viewPriceCodesWorking = cloneProductMasterValue(window.currentViewProduct.price_codes || []);
    window.renderViewPriceCodeEditor();
    toggleModal('view-price-code-modal', true);
};

window.closeViewPriceCodeSetup = function() {
    toggleModal('view-price-code-modal', false);
};

window.renderViewPriceCodeEditor = function() {
    const container = document.getElementById('view-price-code-entries');
    if (!container) return;
    const codes = Array.isArray(window._viewPriceCodesWorking) ? window._viewPriceCodesWorking : [];
    container.innerHTML = '';
    if (!codes.length) window._viewPriceCodesWorking = [{ price_code: '', selling_price: '0.00' }];
    window._viewPriceCodesWorking.forEach((entry, index) => {
        const code = typeof entry === 'string' ? entry : (entry.price_code || '');
        const price = typeof entry === 'string' ? '' : (entry.selling_price ?? '');
        const row = document.createElement('div');
        row.className = 'grid grid-cols-[1fr_180px_auto] gap-3 items-center p-3 border border-slate-200 rounded-xl bg-slate-50/50';
        row.innerHTML = '<input type="text" value="' + escapeHtml(String(code)) + '" oninput="updateViewPriceCodeEntry(' + index + ', \'price_code\', this.value)" placeholder="Pricing Code" class="px-3 py-2.5 border border-slate-200 rounded-lg text-sm outline-none focus:border-maroon">' +
            '<div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold">₱</span><input type="number" min="0" step="0.01" value="' + escapeHtml(String(price)) + '" oninput="updateViewPriceCodeEntry(' + index + ', \'selling_price\', this.value)" class="w-full pl-7 pr-3 py-2.5 border border-slate-200 rounded-lg text-sm font-bold outline-none focus:border-maroon"></div>' +
            '<button type="button" onclick="removeViewPriceCodeEntry(' + index + ')" class="px-3 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg text-[9px] font-black uppercase">Delete</button>';
        container.appendChild(row);
    });
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.addViewPriceCodeEntry = function() {
    if (!Array.isArray(window._viewPriceCodesWorking)) window._viewPriceCodesWorking = [];
    window._viewPriceCodesWorking.push({ price_code: '', selling_price: '0.00' });
    window.renderViewPriceCodeEditor();
};
window.updateViewPriceCodeEntry = function(index, field, value) {
    if (!window._viewPriceCodesWorking[index] || typeof window._viewPriceCodesWorking[index] === 'string') {
        window._viewPriceCodesWorking[index] = { price_code: field === 'price_code' ? value : '', selling_price: field === 'selling_price' ? value : '0.00' };
    } else window._viewPriceCodesWorking[index][field] = value;
};
window.removeViewPriceCodeEntry = function(index) {
    window._viewPriceCodesWorking.splice(index, 1);
    window.renderViewPriceCodeEditor();
};

window.saveViewPriceCodes = async function() {
    if (!window.currentViewProduct || !window.prodRoutes?.priceCodesUpdate) return;
    const seen = new Set();
    const clean = [];
    for (const entry of (window._viewPriceCodesWorking || [])) {
        const code = String(typeof entry === 'string' ? entry : (entry.price_code || '')).trim();
        if (!code) continue;
        const key = code.toUpperCase();
        if (seen.has(key)) { alert('Duplicate pricing code: ' + code); return; }
        seen.add(key);
        clean.push({ price_code: code, selling_price: Math.max(0, Number(typeof entry === 'string' ? 0 : entry.selling_price || 0)) });
    }
    const btn = document.getElementById('view-price-code-save-btn');
    if (btn) btn.disabled = true;
    try {
        const response = await fetch(window.prodRoutes.priceCodesUpdate.replace(':id', window.currentViewProduct.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ price_codes: clean })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Failed to save pricing codes');
        window.currentViewProduct.price_codes = cloneProductMasterValue(result.price_codes || clean);
        if (window.viewProductOriginalData) window.viewProductOriginalData.price_codes = cloneProductMasterValue(window.currentViewProduct.price_codes);
        if (window.currentViewProductIndex >= 0 && products[window.currentViewProductIndex]) products[window.currentViewProductIndex].price_codes = cloneProductMasterValue(window.currentViewProduct.price_codes);
        renderViewPriceCodes(window.currentViewProduct.price_codes);
        toggleModal('view-price-code-success-modal', true);
    } catch (error) {
        alert(error.message || 'Unable to save pricing codes.');
    } finally {
        if (btn) btn.disabled = false;
    }
};

window.confirmViewPriceCodeSuccess = function() {
    toggleModal('view-price-code-success-modal', false);
    toggleModal('view-price-code-modal', false);
    window.setProductViewEditMode(true);
};

// ----- Picture Manager -----
window.openViewPictureManager = function() {
    if (!window.viewProductEditMode) return;
    window._viewPictureManagerSnapshot = {
        images: [...(window.pendingProductImages || [])],
        coverImageIndex: Number(window.coverImageIndex || 0),
        currentViewImageIndex: Number(window.currentViewImageIndex || 0)
    };
    window.renderPreviewGrid('viewEdit');
    toggleModal('view-picture-manager-modal', true);
};

window.cancelViewPictureManager = function() {
    if (window._viewPictureManagerSnapshot) {
        window.pendingProductImages = [...window._viewPictureManagerSnapshot.images];
        window.coverImageIndex = window._viewPictureManagerSnapshot.coverImageIndex;
        window.currentViewImageIndex = window._viewPictureManagerSnapshot.currentViewImageIndex;
    }
    window._viewPictureManagerSnapshot = null;
    window.renderViewProductImage();
    toggleModal('view-picture-manager-modal', false);
    window.updateProductViewDirtyState();
};

window.applyViewPictureManager = function() {
    window._viewPictureManagerSnapshot = null;
    window.currentViewImageIndex = Math.min(window.coverImageIndex || 0, Math.max(0, window.pendingProductImages.length - 1));
    window.renderViewProductImage();
    toggleModal('view-picture-manager-modal', false);
    window.updateProductViewDirtyState();
};
