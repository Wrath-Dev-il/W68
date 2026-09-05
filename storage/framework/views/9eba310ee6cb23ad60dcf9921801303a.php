<?php
$tpRoutes = [
    'customers' => route('special.top-products.customers'),
    'brands' => route('special.top-products.brands'),
    'descriptions' => route('special.top-products.descriptions'),
    'agents' => route('special.top-products.agents'),
    'data' => route('special.top-products.data'),
];
?>

<?php $__env->startPush('styles'); ?>
<style>
.tp-radio-row{display:inline-flex;align-items:center;gap:.55rem;cursor:pointer}.tp-radio-row input{accent-color:#7f1d1d}
.tp-dropdown{max-height:280px;overflow:auto}.tp-option{display:block;width:100%;padding:.65rem .85rem;text-align:left;font-size:12px}.tp-option:hover{background:#f8fafc;color:#7f1d1d}
.tp-year-chip{display:inline-flex;align-items:center;gap:.4rem;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:999px;padding:.4rem .7rem;font-size:12px;font-weight:700}
.tp-year-chip button{font-weight:900;color:#991b1b}.tp-toast{position:fixed;right:24px;bottom:24px;z-index:9999;background:#111827;color:#fff;padding:.8rem 1rem;border-radius:.75rem;font-size:12px;box-shadow:0 15px 35px rgba(0,0,0,.18)}
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('sales_report_content'); ?>
<div id="page-top-products" class="rep-page space-y-6">
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 bg-white border border-slate-200 rounded-xl p-5 shadow-sm no-print">
        <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-maroon-700">Reports Engine</p>
            <h2 class="mt-1 text-xl font-black text-slate-900">Top Products Sales</h2>
            <p class="text-xs font-semibold text-slate-500 mt-1">Rank products by net sales with Local / Online breakdown.</p>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm no-print">
        <h3 class="text-xs font-black uppercase tracking-widest text-slate-700 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="filter" class="w-4 h-4 text-maroon-700"></i> Report Configuration
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2 space-y-2">
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Date Type</label>
                <div class="flex flex-wrap gap-5">
                    <label class="tp-radio-row"><input type="radio" name="tp-date-type" value="monthly" checked><span class="text-xs font-semibold">Monthly</span></label>
                    <label class="tp-radio-row"><input type="radio" name="tp-date-type" value="annual"><span class="text-xs font-semibold">Annual</span></label>
                    <label class="tp-radio-row"><input type="radio" name="tp-date-type" value="as-of"><span class="text-xs font-semibold">As Of</span></label>
                    <label class="tp-radio-row"><input type="radio" name="tp-date-type" value="from-to"><span class="text-xs font-semibold">From To</span></label>
                </div>
            </div>

            <div id="tp-date-fields" class="md:col-span-2"></div>

            <div class="space-y-2 relative">
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Sales Source</label>
                <div class="tp-searchable" data-static='[{"id":"both","name":"Both"},{"id":"local","name":"Local"},{"id":"online","name":"Online"}]' data-default="Both">
                    <div class="relative"><input id="tp-channel" value="Both" data-value="both" autocomplete="off" class="tp-input w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 pr-10 text-xs font-semibold outline-none focus:border-maroon-900"><button type="button" class="tp-toggle absolute inset-y-0 right-0 px-3 text-slate-400"><i data-lucide="chevron-down" class="w-4 h-4"></i></button></div>
                    <div class="tp-dropdown hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50"></div>
                </div>
            </div>

            <div class="space-y-2 relative">
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Customer</label>
                <div class="tp-searchable" data-endpoint="<?php echo e($tpRoutes['customers']); ?>" data-default="All" data-object="1">
                    <div class="relative"><input id="tp-customer" value="All" data-value="" autocomplete="off" class="tp-input w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 pr-10 text-xs font-semibold outline-none focus:border-maroon-900"><button type="button" class="tp-toggle absolute inset-y-0 right-0 px-3 text-slate-400"><i data-lucide="chevron-down" class="w-4 h-4"></i></button></div>
                    <div class="tp-dropdown hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50"></div>
                </div>
            </div>

            <div class="space-y-2 relative">
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Brand</label>
                <div class="tp-searchable" data-endpoint="<?php echo e($tpRoutes['brands']); ?>" data-default="All">
                    <div class="relative"><input id="tp-brand" value="All" autocomplete="off" class="tp-input w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 pr-10 text-xs font-semibold outline-none focus:border-maroon-900"><button type="button" class="tp-toggle absolute inset-y-0 right-0 px-3 text-slate-400"><i data-lucide="chevron-down" class="w-4 h-4"></i></button></div>
                    <div class="tp-dropdown hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50"></div>
                </div>
            </div>

            <div class="space-y-2 relative">
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Description</label>
                <div class="tp-searchable" data-endpoint="<?php echo e($tpRoutes['descriptions']); ?>" data-default="All">
                    <div class="relative"><input id="tp-description" value="All" autocomplete="off" class="tp-input w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 pr-10 text-xs font-semibold outline-none focus:border-maroon-900"><button type="button" class="tp-toggle absolute inset-y-0 right-0 px-3 text-slate-400"><i data-lucide="chevron-down" class="w-4 h-4"></i></button></div>
                    <div class="tp-dropdown hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50"></div>
                </div>
            </div>

            <div class="space-y-2 relative md:col-span-2">
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Agent</label>
                <div class="tp-searchable" data-endpoint="<?php echo e($tpRoutes['agents']); ?>" data-default="All">
                    <div class="relative"><input id="tp-agent" value="All" autocomplete="off" class="tp-input w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 pr-10 text-xs font-semibold outline-none focus:border-maroon-900"><button type="button" class="tp-toggle absolute inset-y-0 right-0 px-3 text-slate-400"><i data-lucide="chevron-down" class="w-4 h-4"></i></button></div>
                    <div class="tp-dropdown hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50"></div>
                </div>
            </div>
        </div>

        <div class="flex justify-end mt-6 pt-4 border-t border-slate-100">
            <button type="button" id="tp-print" class="inline-flex items-center gap-2 rounded-xl bg-maroon-900 px-6 py-3 text-[10px] font-black uppercase tracking-widest text-white shadow hover:bg-maroon-800"><i data-lucide="printer" class="h-4 w-4 text-goldlining-400"></i>Print</button>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(() => {
    const routes = <?php echo json_encode($tpRoutes, 15, 512) ?>;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const esc = (v) => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
    const money = (n) => Number(n || 0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
    const qty = (n) => Number(n || 0).toLocaleString('en-PH',{maximumFractionDigits:4});
    const today = new Date();
    let years = [today.getFullYear()];

    const toast = (msg) => { const d=document.createElement('div'); d.className='tp-toast'; d.textContent=msg; document.body.appendChild(d); setTimeout(()=>d.remove(),2600); };

    function renderDateFields(){
        const type=document.querySelector('input[name="tp-date-type"]:checked')?.value || 'monthly';
        const host=document.getElementById('tp-date-fields');
        if(type==='monthly'){
            host.innerHTML=`<label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Month</label><input id="tp-month" type="month" value="${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold">`;
        } else if(type==='annual'){
            host.innerHTML=`<label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Annual Years</label><div class="flex gap-2"><input id="tp-year-input" type="number" min="2000" max="2100" value="${today.getFullYear()}" class="w-40 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold"><button id="tp-add-year" type="button" class="rounded-xl border border-maroon-800 px-4 py-2 text-xs font-black text-maroon-800">Add Year</button></div><div id="tp-year-chips" class="flex flex-wrap gap-2 mt-3"></div>`;
            renderYearChips();
            document.getElementById('tp-add-year').onclick=()=>{ const y=Number(document.getElementById('tp-year-input').value); if(y>=2000&&y<=2100&&!years.includes(y)){years.push(y);years.sort((a,b)=>b-a);renderYearChips();} };
        } else if(type==='as-of'){
            host.innerHTML=`<label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">As Of Date</label><input id="tp-as-of" type="date" value="${today.toISOString().slice(0,10)}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold">`;
        } else {
            host.innerHTML=`<div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div><label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">From</label><input id="tp-from" type="date" value="${today.getFullYear()}-01-01" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold"></div><div><label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">To</label><input id="tp-to" type="date" value="${today.toISOString().slice(0,10)}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold"></div></div>`;
        }
    }
    function renderYearChips(){ const h=document.getElementById('tp-year-chips'); if(!h)return; h.innerHTML=years.map(y=>`<span class="tp-year-chip">${y}<button type="button" data-y="${y}">×</button></span>`).join(''); h.querySelectorAll('button').forEach(b=>b.onclick=()=>{ if(years.length===1)return; years=years.filter(y=>y!==Number(b.dataset.y));renderYearChips(); }); }
    document.querySelectorAll('input[name="tp-date-type"]').forEach(r=>r.addEventListener('change',renderDateFields)); renderDateFields();

    async function fillDropdown(wrap, query=''){
        const dd=wrap.querySelector('.tp-dropdown');
        let rows=[];
        if(wrap.dataset.static){ rows=JSON.parse(wrap.dataset.static); }
        else { const res=await fetch(`${wrap.dataset.endpoint}?query=${encodeURIComponent(query)}`,{headers:{'Accept':'application/json'}}); rows=await res.json(); }
        const isObj=wrap.dataset.object==='1' || wrap.dataset.static;
        const all = wrap.dataset.default || 'All';
        let html = wrap.dataset.static ? '' : `<button type="button" class="tp-option" data-value="" data-label="${esc(all)}">${esc(all)}</button>`;
        html += rows.map(row=>{ const label=isObj?row.name:row; const value=isObj?row.id:row; return `<button type="button" class="tp-option" data-value="${esc(value)}" data-label="${esc(label)}">${esc(label)}</button>`; }).join('');
        dd.innerHTML=html || '<div class="p-3 text-xs text-slate-400">No matches</div>'; dd.classList.remove('hidden');
        dd.querySelectorAll('.tp-option').forEach(btn=>btn.onclick=()=>{ const input=wrap.querySelector('.tp-input'); input.value=btn.dataset.label; input.dataset.value=btn.dataset.value; dd.classList.add('hidden'); });
    }
    document.querySelectorAll('.tp-searchable').forEach(wrap=>{
        const input=wrap.querySelector('.tp-input'), toggle=wrap.querySelector('.tp-toggle'), dd=wrap.querySelector('.tp-dropdown');
        const open=()=>{ if(input.value===wrap.dataset.default){input.value='';input.dataset.value='';} fillDropdown(wrap,input.value.trim()).catch(()=>{}); };
        input.addEventListener('focus',open); toggle.addEventListener('click',open);
        let timer; input.addEventListener('input',()=>{ input.dataset.value=''; clearTimeout(timer); timer=setTimeout(()=>fillDropdown(wrap,input.value.trim()).catch(()=>{}),180); });
        document.addEventListener('click',e=>{ if(!wrap.contains(e.target))dd.classList.add('hidden'); });
    });

    function payload(){
        const type=document.querySelector('input[name="tp-date-type"]:checked')?.value || 'monthly';
        const val=id=>document.getElementById(id)?.value || '';
        const clean=id=>{ const v=val(id).trim(); return /^all$/i.test(v)||/^select all$/i.test(v)?'':v; };
        return {date_type:type, month:val('tp-month'), years:type==='annual'?years:[], as_of:val('tp-as-of'), date_from:val('tp-from'), date_to:val('tp-to'), channel:document.getElementById('tp-channel')?.dataset.value||'both', customer_id:Number(document.getElementById('tp-customer')?.dataset.value||0), customer:clean('tp-customer'), brand:clean('tp-brand'), description:clean('tp-description'), agent:clean('tp-agent')};
    }

    function printReport(data){
        const p=payload();
        const showLocal=p.channel!=='online', showOnline=p.channel!=='local';
        const reportPeriods=Array.isArray(data.periods)?data.periods:[];
        const showPeriod=data.date_type==='annual' && reportPeriods.length>1;
        const labels=reportPeriods.map(x=>x.label).join(', ');
        const showTotal=showLocal&&showOnline;
        const salesCols=(showPeriod?1:0)+(showTotal?1:0)+(showLocal?1:0)+(showOnline?1:0);
        const priceHeader=showLocal&&showOnline?'LOCAL / ONLINE':(showOnline?'ONLINE':'LOCAL');
        const costDate=(value)=>{
            const raw=String(value||'').trim();
            if(!raw)return '--';
            const parts=raw.slice(0,10).split('-');
            if(parts.length!==3)return raw;
            return `${parts[1]}/${parts[2]}/${parts[0]}`;
        };
        const salesValue=(value,unit)=>`<div class="sales-qty">${qty(value)}</div>${String(unit||'').trim()?`<small class="sales-unit">${esc(String(unit).trim().toUpperCase())}</small>`:''}`;

        // Preserve the existing Top Products ranking: highest total units sold first,
        // then sales amount, then item code.
        const sortedProducts=[...(data.products||[])].sort((a,b)=>{
            const qtyDiff=Number(b.total_sales||0)-Number(a.total_sales||0);
            if(qtyDiff!==0)return qtyDiff;
            const amountDiff=Number(b.sales_amount||0)-Number(a.sales_amount||0);
            return amountDiff!==0?amountDiff:String(a.item_code||'').localeCompare(String(b.item_code||''));
        });

        const win=window.open('','_blank','width=1100,height=900');
        if(!win){toast('Allow pop-ups to print.');return;}

        const doc=win.document;
        const colgroup=`<colgroup><col class="item-col"><col class="part-col"><col class="description-col"><col class="cost-col"><col class="price-col"><col class="inventory-col">${showPeriod?'<col class="sales-col">':''}${showTotal?'<col class="sales-col">':''}${showLocal?'<col class="sales-col">':''}${showOnline?'<col class="sales-col">':''}</colgroup>`;
        const tableHead=`<thead><tr><th rowspan="2">ITEM CODE</th><th rowspan="2">PART NO.</th><th rowspan="2" class="description-head">DESCRIPTION</th><th rowspan="2">COST<br><small>DATE / SUPPLIER</small></th><th rowspan="2">PRICE<br><small>${priceHeader}</small></th><th rowspan="2">INV.</th><th colspan="${salesCols}" class="sales-group">SALES</th></tr><tr>${showPeriod?'<th class="sales-subhead">PERIOD</th>':''}${showTotal?'<th class="sales-subhead">TOTAL</th>':''}${showLocal?'<th class="sales-subhead">LOCAL</th>':''}${showOnline?'<th class="sales-subhead">ONLINE</th>':''}</tr></thead>`;

        // Write only the lightweight shell first. The product rows are appended later in
        // small batches so Chrome never has to hold multiple copies of one enormous HTML
        // string while parsing the print window.
        doc.open();
        doc.write(`<!doctype html><html><head><title>Top Products Sales</title><style>
@page{size:Letter portrait;margin:.35in}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:13px;color:#111;background:#e5e7eb}
.actions{position:sticky;top:0;z-index:10;display:flex;justify-content:flex-end;align-items:center;gap:8px;padding:10px 14px;background:#fff;border-bottom:1px solid #d1d5db}
.actions .status{margin-right:auto;font-size:12px;font-weight:700;color:#475569}
.actions button{padding:8px 15px;font-weight:700;cursor:pointer}.actions button:disabled{opacity:.5;cursor:not-allowed}
#print-root{padding:14px 0}
.print-page{width:7.8in;height:10.3in;margin:0 auto 18px;background:#fff;overflow:visible;break-after:page;page-break-after:always}
.print-page:last-child{break-after:auto;page-break-after:auto}
.head{text-align:center;margin-bottom:16px}.head h1{font-size:18px;margin:0 0 4px}.head h2{font-size:15px;margin:0 0 3px}.head div{font-size:13px;font-weight:700}
table{width:100%;border-collapse:collapse;table-layout:fixed}
th,td{border:1px solid #111;padding:5px 5px;vertical-align:top;word-break:normal;overflow-wrap:break-word}
th{font-size:12px;text-align:center;background:#f3f4f6}td{font-size:13px}
.item-col{width:11%}.part-col{width:10%}.description-col{width:22%}.cost-col{width:16%}.price-col{width:11%}.inventory-col{width:6%}.sales-col{width:6%}
.description-head,.description-cell{word-break:keep-all;overflow-wrap:normal;white-space:normal;hyphens:none}
.cost-cell,.cost-cell div,.cost-cell small{text-align:left}.cost-value{font-weight:700;white-space:nowrap}.cost-cell small{display:block;font-size:10px;line-height:1.2}.cost-meta{margin-top:2px}.cost-currency{font-weight:800;margin-left:2px}.cost-supplier{font-weight:700;margin-top:2px}
.price-cell,.price-cell div{text-align:left}.online-price{color:green;font-weight:700;margin-top:3px;text-align:left}
.inventory-cell{text-align:center;font-weight:700}
.period{font-size:10px;font-weight:700;text-align:center;white-space:nowrap;word-break:keep-all;overflow-wrap:normal}
.sales-cell{text-align:center}.sales-qty{font-size:13px;line-height:1.05}.sales-unit{display:block;font-size:10px;font-weight:700;line-height:1.1;margin-top:2px;white-space:nowrap}
.sales-group{text-align:center}.sales-subhead{font-size:10px;white-space:nowrap;word-break:keep-all;overflow-wrap:normal;padding-left:2px;padding-right:2px}
.product-group tr{break-inside:auto;page-break-inside:auto}
@media print{
  body{background:#fff}
  .actions{display:none!important}
  #print-root{padding:0}
  .print-page{width:7.8in;height:10.3in;margin:0;box-shadow:none;break-after:page;page-break-after:always}
  .print-page:last-child{break-after:auto;page-break-after:auto}
}
</style></head><body><div class="actions"><span id="tp-print-status" class="status">Preparing report...</span><button type="button" onclick="window.close()">CLOSE</button><button type="button" id="tp-print-now" disabled>PRINT</button></div><main id="print-root"></main></body></html>`);
        doc.close();

        const root=doc.getElementById('print-root');
        const statusEl=doc.getElementById('tp-print-status');
        const printBtn=doc.getElementById('tp-print-now');
        if(!root||!statusEl||!printBtn){win.close();toast('Unable to prepare print window.');return;}

        const makePage=(firstPage)=>{
            const page=doc.createElement('section');
            page.className='print-page';
            const header=firstPage?`<div class="head"><h1>W68 AUTOPARTS &amp; SERVICE CENTER</h1><h2>TOP PRODUCTS SALES FROM</h2><div>${esc(labels)}</div></div>`:'';
            page.innerHTML=`${header}<table>${colgroup}${tableHead}</table>`;
            root.appendChild(page);
            return {page,table:page.querySelector('table')};
        };

        const makeProductGroup=(prod)=>{
            const periods=Array.isArray(prod.periods)?prod.periods:[];
            const safePeriods=periods.length?periods:[{label:'',total:0,local:0,online:0}];
            const priceBody=`${showLocal?`<div>${money(prod.local_price)}</div>`:''}${showOnline?`<div class="online-price">${money(prod.online_price)}</div>`:''}`;
            const costBody=`<div class="cost-value">${money(prod.cost)}${prod.currency?` <span class="cost-currency">${esc(prod.currency)}</span>`:''}</div><small class="cost-meta">${esc(costDate(prod.cost_date))}</small><small class="cost-supplier" title="${esc(prod.supplier_full)}">${esc(prod.supplier)}</small>`;
            const group=doc.createElement('tbody');
            group.className='product-group';
            group.innerHTML=safePeriods.map((period,idx)=>`<tr>${idx===0?`<td rowspan="${safePeriods.length}" class="item-cell">${esc(prod.item_code)}</td><td rowspan="${safePeriods.length}" class="part-cell">${esc(prod.part_no)}</td><td rowspan="${safePeriods.length}" class="description-cell">${esc(prod.description)}</td><td rowspan="${safePeriods.length}" class="cost-cell">${costBody}</td><td rowspan="${safePeriods.length}" class="price-cell">${priceBody}</td><td rowspan="${safePeriods.length}" class="inventory-cell">${qty(prod.on_hand)}</td>`:''}${showPeriod?`<td class="period">${esc(period.label)}</td>`:''}${showTotal?`<td class="sales-cell">${salesValue(period.total,prod.unit)}</td>`:''}${showLocal?`<td class="sales-cell">${salesValue(period.local,prod.unit)}</td>`:''}${showOnline?`<td class="sales-cell">${salesValue(period.online,prod.unit)}</td>`:''}</tr>`).join('');
            return group;
        };

        // Explicitly pack complete products into Letter-sized page containers. This avoids
        // Chrome's expensive global table pagination with thousands of break-inside:avoid
        // groups while still keeping a product's annual rows together.
        let current=makePage(true);
        let currentGroups=0;
        let index=0;
        const total=sortedProducts.length;
        const BATCH_SIZE=40;

        const addEmptyRow=()=>{
            const body=doc.createElement('tbody');
            body.innerHTML=`<tr><td colspan="${6+salesCols}" style="text-align:center;padding:28px">No sales found for the selected filters.</td></tr>`;
            current.table.appendChild(body);
        };

        const finish=()=>{
            if(total===0)addEmptyRow();
            statusEl.textContent=`Ready — ${total.toLocaleString()} product${total===1?'':'s'}`;
            printBtn.disabled=false;
            printBtn.addEventListener('click',()=>win.print());
            win.focus();
            // Release the temporary sorted array as soon as the DOM is ready.
            sortedProducts.length=0;
        };

        const renderBatch=()=>{
            const end=Math.min(index+BATCH_SIZE,total);
            for(;index<end;index++){
                const group=makeProductGroup(sortedProducts[index]);
                current.table.appendChild(group);

                // If the newly appended whole product crossed the Letter page boundary,
                // move that same tbody to a fresh page. Do not ask the print engine to
                // solve thousands of non-breakable groups by itself.
                if(currentGroups>0 && current.page.scrollHeight>current.page.clientHeight+1){
                    group.remove();
                    current=makePage(false);
                    current.table.appendChild(group);
                    currentGroups=1;
                }else{
                    currentGroups++;
                }
            }
            statusEl.textContent=`Preparing ${index.toLocaleString()} / ${total.toLocaleString()} products...`;
            if(index<total){
                win.requestAnimationFrame(renderBatch);
            }else{
                win.requestAnimationFrame(finish);
            }
        };

        win.requestAnimationFrame(renderBatch);
    }

    document.getElementById('tp-print').addEventListener('click', async()=>{
        const btn=document.getElementById('tp-print'); btn.disabled=true; btn.textContent='Loading...';
        try{ const res=await fetch(routes.data,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload())}); const data=await res.json(); if(!res.ok||!data.success)throw new Error(data.message||'Unable to build report.'); printReport(data); }
        catch(e){toast(e.message||'Unable to build report.');} finally{btn.disabled=false;btn.innerHTML='<i data-lucide="printer" class="h-4 w-4 text-goldlining-400"></i>Print'; if(window.lucide)lucide.createIcons();}
    });
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('partials.special_user.special_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\hatdog\resources\views/Special_User/Reports/TOP-PRODUCTS.blade.php ENDPATH**/ ?>