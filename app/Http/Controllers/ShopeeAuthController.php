<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopeeAuthController extends Controller
{
    protected $partnerId;
    protected $partnerKey;
    protected $redirectUrl;

    public function __construct()
    {
        $this->partnerId = config('services.shopee.partner_id');
        $this->partnerKey = config('services.shopee.partner_key');
        $this->redirectUrl = "https://chamber-unify-starch.ngrok-free.dev/shopee/callback";
    }

    public function redirectToShopee()
    {
        $path = "/api/v2/shop/auth_partner";
        $timestamp = time();

        $baseString = $this->partnerId . $path . $timestamp;
        $sign = hash_hmac('sha256', $baseString, $this->partnerKey, false);

        $authUrl = "https://partner.shopeemobile.com/api/v2/shop/auth_partner?" . http_build_query([
            'partner_id' => (int)$this->partnerId,
            'timestamp'  => $timestamp,
            'sign'       => $sign,
            'redirect'   => $this->redirectUrl
        ]);

        return redirect()->away($authUrl);
    }

    public function handleCallback(Request $request)
    {
        $code = $request->query('code');
        $shopId = $request->query('shop_id');

        if (!$code || !$shopId) {
            return response('Authorization failed or denied.', 400);
        }

        $path = "/api/v2/auth/token/get";
        $timestamp = time();

        $baseString = $this->partnerId . $path . $timestamp;
        $sign = hash_hmac('sha256', $baseString, $this->partnerKey, false);

        $apiUrl = "https://partner.shopeemobile.com" . $path . "?" . http_build_query([
            'partner_id' => (int)$this->partnerId,
            'timestamp'  => $timestamp,
            'sign'       => $sign
        ]);

        $response = Http::post($apiUrl, [
            'code'       => $code,
            'partner_id' => (int)$this->partnerId,
            'shop_id'    => (int)$shopId
        ]);

        if ($response->successful()) {
            $data = $response->json();

            $accessToken = $data['access_token'];
            $refreshToken = $data['refresh_token'];

            $envPath = base_path('.env');
            $envContent = file_get_contents($envPath);
            $envContent = preg_replace('/^SHOPEE_ACCESS_TOKEN=.*/m', "SHOPEE_ACCESS_TOKEN={$accessToken}", $envContent);
            $envContent = preg_replace('/^SHOPEE_REFRESH_TOKEN=.*/m', "SHOPEE_REFRESH_TOKEN={$refreshToken}", $envContent);
            $envContent = preg_replace('/^SHOPEE_SHOP_ID=.*/m', "SHOPEE_SHOP_ID={$shopId}", $envContent);
            file_put_contents($envPath, $envContent);

            Log::info("LIVE ACCESS TOKEN: " . $accessToken);
            Log::info("LIVE REFRESH TOKEN: " . $refreshToken);

            return response("Success! Tokens saved to .env", 200);
        }

        return response()->json(['error' => 'Failed to retrieve access token', 'details' => $response->json()], 500);
    }
}
