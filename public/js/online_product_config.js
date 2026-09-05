let selectedOnlineProduct = null;
let selectedSystemProduct = null;
let currentConversionDirection = null;
let onlineDebounceTimer = null;
let systemDebounceTimer = null;

function fetchOnlineProducts(search) {
    search = search || (document.getElementById('search-online').value || '');
    const container = document.getElementById('online-products-list');
    container.innerHTML = '<div class="text-center py-8 text-slate-400 text-xs">Loading...</div>';
    fetch(window.onlineProductConfigRoutes.fetchOnline + '?search=' + encodeURIComponent(search))
        .then(r => r.json())
        .then(data => {
            const products = data.products || [];
            document.getElementById('online-count').textContent = products.length;
            if (!products.length) {
                container.innerHTML = '<div class="text-center py-8 text-slate-400 text-xs">No online products found.</div>';
                return;
            }
            container.innerHTML = products.map(p => `
                <div class="product-card ${selectedOnlineProduct && selectedOnlineProduct.id === p.id ? 'selected' : ''}" onclick="selectOnlineProduct(${JSON.stringify(p).replace(/"/g, '&quot;')})">
                    <div class="product-info">
                        <div class="product-code">${p.product_code || p.name || 'N/A'}</div>
                        <div class="product-desc">${p.description || ''}</div>
                    </div>
                    <span class="product-badge online">Online</span>
                </div>
            `).join('');
        })
        .catch(() => container.innerHTML = '<div class="text-center py-8 text-slate-400 text-xs">Failed to load.</div>');
}

function fetchSystemProducts(search) {
    search = search || (document.getElementById('search-system').value || '');
    const container = document.getElementById('system-products-list');
    container.innerHTML = '<div class="text-center py-8 text-slate-400 text-xs">Loading...</div>';
    fetch(window.onlineProductConfigRoutes.fetchSystem + '?search=' + encodeURIComponent(search))
        .then(r => r.json())
        .then(data => {
            const products = data.products || [];
            document.getElementById('system-count').textContent = products.length;
            if (!products.length) {
                container.innerHTML = '<div class="text-center py-8 text-slate-400 text-xs">No system products found.</div>';
                return;
            }
            container.innerHTML = products.map(p => `
                <div class="product-card ${selectedSystemProduct && selectedSystemProduct.id === p.id ? 'selected' : ''}" onclick="selectSystemProduct(${JSON.stringify(p).replace(/"/g, '&quot;')})">
                    <div class="product-info">
                        <div class="product-code">${p.product_code || 'N/A'}</div>
                        <div class="product-desc">${p.description || p.part_number || ''}</div>
                    </div>
                    <span class="product-badge system">System</span>
                </div>
            `).join('');
        })
        .catch(() => container.innerHTML = '<div class="text-center py-8 text-slate-400 text-xs">Failed to load.</div>');
}

function onOnlineSearchInput() {
    clearTimeout(onlineDebounceTimer);
    onlineDebounceTimer = setTimeout(() => fetchOnlineProducts(), 300);
}

function onSystemSearchInput() {
    clearTimeout(systemDebounceTimer);
    systemDebounceTimer = setTimeout(() => fetchSystemProducts(), 300);
}

function selectOnlineProduct(product) {
    selectedOnlineProduct = product;
    fetchOnlineProducts(document.getElementById('search-online').value);
    showConvertModal('online-to-system', product);
}

function selectSystemProduct(product) {
    selectedSystemProduct = product;
    fetchSystemProducts(document.getElementById('search-system').value);
    showConvertModal('system-to-online', product);
}

function showConvertModal(direction, product) {
    currentConversionDirection = direction;
    const modal = document.getElementById('convert-modal');
    const title = document.getElementById('convert-modal-title');
    const body = document.getElementById('convert-modal-body');
    const btn = document.getElementById('convert-confirm-btn');

    if (direction === 'online-to-system') {
        title.textContent = 'Convert Online Product to System';
        body.innerHTML = `
            <p>Convert <strong>${product.product_code || product.name || 'N/A'}</strong> to a System Product?</p>
            <div class="bg-slate-50 rounded-lg p-3 text-xs space-y-1">
                <div><span class="font-semibold">Code:</span> ${product.product_code || product.name || 'N/A'}</div>
                <div><span class="font-semibold">Description:</span> ${product.description || 'N/A'}</div>
            </div>
        `;
        btn.textContent = 'Convert to System';
    } else {
        title.textContent = 'Convert System Product to Online';
        body.innerHTML = `
            <p>Convert <strong>${product.product_code || 'N/A'}</strong> to an Online Product?</p>
            <div class="bg-slate-50 rounded-lg p-3 text-xs space-y-1">
                <div><span class="font-semibold">Code:</span> ${product.product_code || 'N/A'}</div>
                <div><span class="font-semibold">Description:</span> ${product.description || 'N/A'}</div>
            </div>
        `;
        btn.textContent = 'Convert to Online';
    }
    toggleConvertModal(true);
}

function toggleConvertModal(show) {
    document.getElementById('convert-modal').classList.toggle('hidden', !show);
}

function confirmConversion() {
    const btn = document.getElementById('convert-confirm-btn');
    btn.disabled = true;
    btn.textContent = 'Converting...';

    let url, payload;
    if (currentConversionDirection === 'online-to-system') {
        url = window.onlineProductConfigRoutes.convertToSystem;
        payload = { product: selectedOnlineProduct };
    } else {
        url = window.onlineProductConfigRoutes.convertToOnline;
        payload = { product: selectedSystemProduct };
    }

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Conversion successful!');
            toggleConvertModal(false);
            fetchOnlineProducts();
            fetchSystemProducts();
        } else {
            alert(data.message || 'Conversion failed.');
        }
    })
    .catch(() => alert('An error occurred during conversion.'))
    .finally(() => {
        btn.disabled = false;
        btn.textContent = currentConversionDirection === 'online-to-system' ? 'Convert to System' : 'Convert to Online';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('search-online').addEventListener('input', onOnlineSearchInput);
    document.getElementById('search-system').addEventListener('input', onSystemSearchInput);
});
