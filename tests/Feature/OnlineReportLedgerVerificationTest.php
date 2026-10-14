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

    public function test_online_report_sync_does_not_reassign_another_reports_ledger_row(): void
    {
        $servicePath = app_path('Services/OnlineReportProductLedgerSyncService.php');
        $this->assertFileExists($servicePath);

        $content = file_get_contents($servicePath);

        $this->assertStringContainsString(
            'canAdoptLedgerRowForReport',
            $content,
            'Legacy recovery must verify ledger ownership before adopting a row.'
        );

        $this->assertStringContainsString(
            "->where('report_id', '!=', \$reportId)",
            $content,
            'A ledger row linked to another Online Report must not be reassigned.'
        );

        $this->assertStringContainsString(
            "if (\$sourceType === 'online_report')",
            $content,
            'Online Report source ownership must be checked before reusing a ledger row.'
        );
    }

    public function test_online_report_sync_supports_repeated_same_product_lines(): void
    {
        $servicePath = app_path('Services/OnlineReportProductLedgerSyncService.php');
        $this->assertFileExists($servicePath);

        $content = file_get_contents($servicePath);

        $this->assertStringNotContainsString(
            'Online Report contains the same product more than once',
            $content,
            'Online Report sync must not reject a second sale of the same product.'
        );

        $this->assertStringContainsString(
            '$existingCursorByProduct',
            $content,
            'Sales Order sync must match repeated products by occurrence.'
        );

        $this->assertStringContainsString(
            '$actualCursorByProduct',
            $content,
            'Verification must match repeated products by occurrence.'
        );

        $this->assertStringContainsString(
            "'source_item_id' => $itemId",
            $content,
            'Each repeated sale must keep its own Sales Order Item identity in Product Ledger.'
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
