<div class="report-page page-break catalog-page-item">
    <style>
        .price-col {
            font-weight: 900;
            color: #1e3a8a; /* Dark Blue */
            text-align: right;
            white-space: nowrap;
        }
        td {
            font-size: 14px !important; /* Force font size 14 for PDF */
            padding: 8px;
            border: 2px solid black !important; /* Increased visibility */
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
            vertical-align: top;
        }
        table {
            border: 2px solid black !important;
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }
        .desc-header {
            font-size: 18px !important; /* Increased for headers */
            padding: 10px 12px !important;
        }
    </style>
    <!-- Tiled Watermark Background Overlay -->
    <div class="watermark-container"></div>

    <div class="report-content">
        <!-- Header -->
        <div class="flex justify-between items-end border-b-4 border-slate-400 pb-2 mb-6">
            <div>
                <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter leading-none">W68 AUTO PARTS</h1>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Price List Content</p>
            </div>
            <div class="text-right">
                <p class="text-[14px] text-black font-bold uppercase tracking-widest"><?php echo e(date('M d, Y')); ?></p>
            </div>
        </div>

        <!-- Price List Table -->
        <table class="mb-4">
            <colgroup>
                <col style="width:14%">
                <col style="width:16%">
                <col style="width:38%">
                <col style="width:17%">
                <col style="width:15%">
            </colgroup>
            <tbody>
                <?php $lastDesc = null; ?>
                <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php $currentDesc = trim(strtoupper($product->description ?: 'NO DESCRIPTION')); ?>
                    
                    <?php if($currentDesc !== $lastDesc): ?>
                        <!-- Group Description Header Row -->
                        <tr>
                            <td colspan="5" class="desc-header bg-slate-100 text-black font-black uppercase py-2 px-3 border-[2px] border-black">
                                <?php echo e($currentDesc); ?>

                            </td>
                        </tr>
                        <?php $lastDesc = $currentDesc; ?>
                    <?php endif; ?>

                    <!-- Details Row -->
                    <tr>
                        <td class="font-bold text-slate-800"><?php echo e($product->product_code); ?></td>
                        <td class="font-mono text-black font-bold"><?php echo e($product->part_number ?: '---'); ?></td>
                        <td>
                            <div class="font-bold text-slate-800"><?php echo e($product->application ?: $product->Application ?: '---'); ?></div>
                            <?php $pos = $product->position ?: $product->Position; ?>
                            <?php if($pos): ?>
                                <div class="text-[9px] text-slate-500 font-bold uppercase mt-1 border-t border-slate-100 pt-0.5">
                                    <span class="text-[7px] text-slate-400 mr-1">POS:</span><?php echo e($pos); ?>

                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="uppercase text-black font-bold"><?php echo e($product->category); ?></td>
                        <td class="price-col">₱<?php echo e(number_format($product->selling_price, 2)); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>

        <!-- Footer -->
        <div class="mt-auto pt-4 border-t-2 border-slate-300 flex justify-between items-center">
            <p class="text-[12px] text-black font-bold uppercase tracking-widest">
                <?php echo e(strtoupper($products->first()->description ?? 'PRICE LIST')); ?>

            </p>
            <p class="text-[12px] text-black font-bold uppercase">PAGE <?php echo e($pageIndex + 1); ?> OF <?php echo e($totalPages); ?></p>
        </div>
    </div>
</div><?php /**PATH C:\xampp\htdocs\hatdog\resources\views\Regular_User\master_list\partials\pricelist_chunk.blade.php ENDPATH**/ ?>