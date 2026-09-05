<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CalculateTotalPaidTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Load the routes file to ensure the function is available
        if (!function_exists('calculateTotalPaid')) {
            require_once __DIR__ . '/../../routes/web.php';
        }
    }

    /**
     * Test that calculateTotalPaid sums all amountPaid values correctly.
     *
     * @return void
     */
    public function test_calculate_total_paid_with_multiple_invoices(): void
    {
        $invoices = [
            ['amountPaid' => 1000.00],
            ['amountPaid' => 2500.50],
            ['amountPaid' => 750.25],
        ];

        $result = calculateTotalPaid($invoices);

        $this->assertEquals(4250.75, $result);
    }

    /**
     * Test that calculateTotalPaid handles empty array correctly.
     *
     * @return void
     */
    public function test_calculate_total_paid_with_empty_array(): void
    {
        $invoices = [];

        $result = calculateTotalPaid($invoices);

        $this->assertEquals(0.0, $result);
    }

    /**
     * Test that calculateTotalPaid handles missing amountPaid key.
     *
     * @return void
     */
    public function test_calculate_total_paid_with_missing_amount_paid(): void
    {
        $invoices = [
            ['amountPaid' => 1000.00],
            ['invoiceNo' => 'INV-001'], // Missing amountPaid
            ['amountPaid' => 500.00],
        ];

        $result = calculateTotalPaid($invoices);

        $this->assertEquals(1500.00, $result);
    }

    /**
     * Test that calculateTotalPaid handles string numbers correctly.
     *
     * @return void
     */
    public function test_calculate_total_paid_with_string_amounts(): void
    {
        $invoices = [
            ['amountPaid' => '1000.00'],
            ['amountPaid' => '2500.50'],
        ];

        $result = calculateTotalPaid($invoices);

        $this->assertEquals(3500.50, $result);
    }

    /**
     * Test that calculateTotalPaid returns float type.
     *
     * @return void
     */
    public function test_calculate_total_paid_returns_float(): void
    {
        $invoices = [
            ['amountPaid' => 100],
        ];

        $result = calculateTotalPaid($invoices);

        $this->assertIsFloat($result);
    }

    /**
     * Test that calculateTotalPaid handles single invoice.
     *
     * @return void
     */
    public function test_calculate_total_paid_with_single_invoice(): void
    {
        $invoices = [
            ['amountPaid' => 9500.00],
        ];

        $result = calculateTotalPaid($invoices);

        $this->assertEquals(9500.00, $result);
    }

    /**
     * Test that calculateTotalPaid handles zero amounts.
     *
     * @return void
     */
    public function test_calculate_total_paid_with_zero_amounts(): void
    {
        $invoices = [
            ['amountPaid' => 0],
            ['amountPaid' => 0.00],
            ['amountPaid' => 0],
        ];

        $result = calculateTotalPaid($invoices);

        $this->assertEquals(0.0, $result);
    }
}
