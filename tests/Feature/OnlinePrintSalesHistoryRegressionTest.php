<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\TestCase;

class OnlinePrintSalesHistoryRegressionTest extends TestCase
{
    public function test_online_print_sales_history_matches_corrected_product_ledger_fixtures(): void
    {
        $fixtures = [
            '260804ESVJWTDQ' => [
                6139 => [
                    2023 => ['total' => 315, 'local' => 235, 'online' => 80],
                    2024 => ['total' => 56, 'local' => 0, 'online' => 56],
                    2025 => ['total' => 51, 'local' => 8, 'online' => 43],
                    2026 => ['total' => 97, 'local' => 48, 'online' => 49],
                ],
            ],
            '26081021NWVVMV' => [
                6693 => [
                    2023 => ['total' => 106, 'local' => 30, 'online' => 76],
                    2024 => ['total' => 151, 'local' => 52, 'online' => 99],
                    2025 => ['total' => 61, 'local' => 22, 'online' => 39],
                    2026 => ['total' => 42, 'local' => 7, 'online' => 35],
                ],
            ],
            '2608114CENJUTK' => [
                6360 => [
                    2023 => ['total' => 58, 'local' => 5, 'online' => 53],
                    2024 => ['total' => 44, 'local' => 0, 'online' => 44],
                    2025 => ['total' => 57, 'local' => 0, 'online' => 57],
                    2026 => ['total' => 42, 'local' => 5, 'online' => 37],
                ],
                6359 => [
                    2023 => ['total' => 52, 'local' => 5, 'online' => 47],
                    2024 => ['total' => 44, 'local' => 0, 'online' => 44],
                    2025 => ['total' => 51, 'local' => 0, 'online' => 51],
                    2026 => ['total' => 41, 'local' => 5, 'online' => 36],
                ],
            ],
            '2608125HCXRKVT' => [
                28512 => [
                    2023 => ['total' => 52, 'local' => 0, 'online' => 52],
                    2024 => ['total' => 57, 'local' => 3, 'online' => 54],
                    2025 => ['total' => 11, 'local' => 0, 'online' => 11],
                    2026 => ['total' => 4, 'local' => 0, 'online' => 4],
                ],
            ],
            '260814A0BE35HK' => [
                33215 => [
                    2023 => ['total' => 9, 'local' => 0, 'online' => 9],
                    2024 => ['total' => 47, 'local' => 10, 'online' => 37],
                    2025 => ['total' => 48, 'local' => 3, 'online' => 45],
                    2026 => ['total' => 43, 'local' => 4, 'online' => 39],
                ],
                6351 => [
                    2023 => ['total' => 23, 'local' => 0, 'online' => 23],
                    2024 => ['total' => 37, 'local' => 0, 'online' => 37],
                    2025 => ['total' => 50, 'local' => 5, 'online' => 45],
                    2026 => ['total' => 43, 'local' => 4, 'online' => 39],
                ],
            ],
            '2608138VWPD2YH' => [
                39555 => [
                    2023 => ['total' => 0, 'local' => 0, 'online' => 0],
                    2024 => ['total' => 0, 'local' => 0, 'online' => 0],
                    2025 => ['total' => 10, 'local' => 10, 'online' => 0],
                    2026 => ['total' => 6, 'local' => 0, 'online' => 6],
                ],
            ],
            '2608138UUKE0GN' => [
                38774 => [
                    2023 => ['total' => 0, 'local' => 0, 'online' => 0],
                    2024 => ['total' => 42, 'local' => 42, 'online' => 0],
                    2025 => ['total' => 104, 'local' => 21, 'online' => 83],
                    2026 => ['total' => 139, 'local' => 46, 'online' => 93],
                ],
            ],
        ];

        foreach ($fixtures as $invoiceNumber => $products) {
            $report = DB::connection('sales')
                ->table('online_reports')
                ->where('invoice_numbers', 'like', '%' . $invoiceNumber . '%')
                ->orderByDesc('id')
                ->first(['id']);

            $this->assertNotNull($report, "Online report fixture is missing for invoice {$invoiceNumber}.");

            $this->startRouteSession();

            $request = Request::create(
                route('admin.sales-order.online-print', ['id' => $report->id], false),
                'GET'
            );
            app()->instance('request', $request);

            $action = Route::getRoutes()
                ->getByName('admin.sales-order.online-print')
                ->getAction('uses');

            $response = $action($report->id);

            $this->assertTrue(method_exists($response, 'getData'), "Online print did not return a view for invoice {$invoiceNumber}.");

            $salesByProduct = $response->getData()['salesByProduct'];

            foreach ($products as $productId => $years) {
                foreach ($years as $year => $expected) {
                    $local = (int) ($salesByProduct[$productId . '-' . $year . '-local'] ?? 0);
                    $online = (int) ($salesByProduct[$productId . '-' . $year . '-online'] ?? 0);

                    $this->assertSame($expected['local'], $local, "{$invoiceNumber} product {$productId} {$year} LOCAL mismatch.");
                    $this->assertSame($expected['online'], $online, "{$invoiceNumber} product {$productId} {$year} ONLINE mismatch.");
                    $this->assertSame($expected['total'], $local + $online, "{$invoiceNumber} product {$productId} {$year} TOTAL mismatch.");
                }
            }
        }
    }

    private function startRouteSession(): void
    {
        $store = new Store('array', new ArraySessionHandler(120));
        $store->start();
        $store->put('user', (object) [
            'id' => 1,
            'name' => 'Codex',
            'account_type' => 1,
            'User_ID' => 'codex',
        ]);

        Session::swap($store);
        app()->instance('session.store', $store);
    }
}
