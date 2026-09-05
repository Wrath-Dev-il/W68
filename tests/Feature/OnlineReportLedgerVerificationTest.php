<?php

namespace Tests\Feature;

use Tests\TestCase;

class OnlineReportLedgerVerificationTest extends TestCase
{
    public function test_public_js_contains_new_loader_and_verify_functions(): void
    {
        $jsPath = public_path('js/Sales-Order.js');
        $this->assertFileExists($jsPath);

        $content = file_get_contents($jsPath);

        $requiredFunctions = [
            'verifyLedgerThenPrint',
            'showOnlineReportLoader',
            'hideOnlineReportLoader',
            'showSyncAndPrintButton',
            'onlineVerifyLedgerUrl',
        ];

        foreach ($requiredFunctions as $fn) {
            $this->assertStringContainsString(
                $fn,
                $content,
                "Public JS must contain reference to '{$fn}'"
            );
        }
    }

    public function test_special_user_view_contains_loader_and_verify_route(): void
    {
        $viewPath = resource_path('views/Special_User/sales/sales-order.blade.php');
        $this->assertFileExists($viewPath);

        $content = file_get_contents($viewPath);

        $this->assertStringContainsString(
            'onlineVerifyLedgerUrl',
            $content,
            'View must define onlineVerifyLedgerUrl route'
        );

        $this->assertStringContainsString(
            'or-ledger-loader',
            $content,
            'View must contain the loader HTML element'
        );
    }

    public function test_source_js_contains_verify_and_loader_functions(): void
    {
        $jsPath = resource_path('js/Sales-Order.js');
        $this->assertFileExists($jsPath);

        $content = file_get_contents($jsPath);

        $requiredFunctions = [
            'window.verifyLedgerThenPrint',
            'window.showOnlineReportLoader',
            'window.hideOnlineReportLoader',
            'window.showSyncAndPrintButton',
            'onlineVerifyLedgerUrl',
        ];

        foreach ($requiredFunctions as $fn) {
            $this->assertStringContainsString(
                $fn,
                $content,
                "Source JS must contain '{$fn}'"
            );
        }
    }

    public function test_routes_contain_verify_product_ledger_endpoint(): void
    {
        $routesPath = base_path('routes/web.php');
        $content = file_get_contents($routesPath);

        $this->assertStringContainsString(
            'verify-product-ledger',
            $content,
            'Routes must contain verify-product-ledger endpoint'
        );

        $this->assertStringContainsString(
            'online-verify-product-ledger',
            $content,
            'Routes must contain online-verify-product-ledger named route'
        );
    }

    public function test_generate_online_receipt_calls_verify_before_redirect(): void
    {
        $jsPath = resource_path('js/Sales-Order.js');
        $content = file_get_contents($jsPath);

        $this->assertStringContainsString(
            'verifyLedgerThenPrint(json.report_id)',
            $content,
            'generateOnlineReceipt must call verifyLedgerThenPrint instead of directly redirecting'
        );
    }

    public function test_verify_function_has_min_3s_delay(): void
    {
        $jsPath = resource_path('js/Sales-Order.js');
        $content = file_get_contents($jsPath);

        $this->assertStringContainsString(
            '3000',
            $content,
            'verifyLedgerThenPrint must have a minimum 3-second delay (3000ms)'
        );

        $this->assertStringContainsString(
            'Date.now()',
            $content,
            'verifyLedgerThenPrint must measure elapsed time with Date.now()'
        );
    }
}
