import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/admin_sidebar_navbar.css',
                'resources/js/admin_sidebar_navbar.js',
                'resources/css/login.css',
                'resources/js/login.js',
                'resources/css/developer_control_panel.css',
                'resources/js/developer_control_panel.js',
                'resources/css/usm.css',
                'resources/js/usm.js',
                'resources/css/product_master_list.css',
                'resources/js/product_master_list.js',
                'resources/css/supplier_master_list.css',
                'resources/js/supplier_master_list.js',
                'resources/css/Customer_Master-list.css',
                'resources/js/Customer_Master-list.js',
                'resources/css/Forwarder_Master-list.css',
                'resources/js/Forwarder_Master-list.js',
                'resources/css/inventory_adjustment.css',
                'resources/js/inventory_adjustment.js',
                'resources/css/Purchase-Note.css',
                'resources/js/Purchase-Note.js',
                'resources/css/Purchase-Order.css',
                'resources/js/Purchase-Order.js',
                'resources/css/Purchase-Return.css',
                'resources/js/Purchase-Return.js',
                'resources/css/Sales-Note.css',
                'resources/js/Sales-Note.js',
                'resources/css/Sales-Order.css',
                'resources/js/Sales-Order.js',
                'resources/css/Waybill.css',
                'resources/css/Sales-Return.css',
                'resources/js/Sales-Return.js',
                'resources/css/purchase-viber.css',
                'resources/js/Purchase-Viber.js',
                'resources/css/payments.css',
                'resources/js/payments.js',
                'resources/css/inventory_reports.css',
                'resources/js/inventory_reports.js'
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
