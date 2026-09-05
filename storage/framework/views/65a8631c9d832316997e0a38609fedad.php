<?php $__env->startSection('expense_cheque_voucher_content'); ?>
<?php echo $__env->make('partials.accounting.expense-cheque-voucher-content', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/expense_cheque_voucher.css')); ?>?v=<?php echo e(time()); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/expense_cheque_voucher.js')); ?>?v=<?php echo e(time()); ?>"></script>
<?php $__env->stopPush(); ?>


<?php echo $__env->make('partials.user_account.user_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\hatdog\resources\views\Regular_User\Accounting\Expense-Cheque-Voucher.blade.php ENDPATH**/ ?>