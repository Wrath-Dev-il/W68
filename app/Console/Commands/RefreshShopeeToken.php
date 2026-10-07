<?php

namespace App\Console\Commands;

use App\Services\ShopeeService;
use Illuminate\Console\Command;

class RefreshShopeeToken extends Command
{
    protected $signature = 'shopee:refresh-token';

    protected $description = 'Rotate and persist the Shopee access/refresh token pair.';

    public function handle(ShopeeService $service): int
    {
        $status = $service->getAuthorizationStatus();

        if (!($status['partner_configured'] ?? false)) {
            $this->error('Shopee Partner ID / Partner Key is not configured.');
            return self::FAILURE;
        }

        if ($service->refreshAccessToken(true)) {
            $status = $service->getAuthorizationStatus();
            $this->info('Shopee token pair refreshed successfully.');
            $this->line('Shop ID: ' . ($status['shop_id'] ?? 'unknown'));
            $this->line('Access token expires: ' . ($status['access_token_expires_at'] ?? 'unknown'));
            return self::SUCCESS;
        }

        if ($service->requiresReauthorization()) {
            $this->error('Shopee reauthorization is required. Open /admin/shopee/authorization as an Admin user.');
        } else {
            $this->error('Shopee token refresh failed: ' . ($service->getLastAuthError() ?? 'unknown error'));
        }

        return self::FAILURE;
    }
}
