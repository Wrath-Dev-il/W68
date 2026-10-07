<?php

namespace App\Http\Controllers;

use App\Models\ShopeeCredential;
use App\Services\ShopeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopeeAuthController extends Controller
{
    protected string $partnerId;
    protected string $partnerKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->partnerId = trim((string) config('services.shopee.partner_id', ''));
        $this->partnerKey = trim((string) config('services.shopee.partner_key', ''));
        $this->baseUrl = rtrim((string) config('services.shopee.base_url', 'https://partner.shopeemobile.com'), '/');
    }

    public function status(ShopeeService $service)
    {
        $this->requireAdmin();

        $status = $service->getAuthorizationStatus();
        $credential = null;

        try {
            $credential = ShopeeCredential::query()->latest('updated_at')->first();
        } catch (\Throwable $e) {
            Log::warning('Shopee authorization status could not read the credentials table.', [
                'error' => $e->getMessage(),
            ]);
        }

        return view('admin.shopee-authorization', [
            'status' => $status,
            'credential' => $credential,
            'redirectUrl' => $this->redirectUrl(request()),
        ]);
    }

    public function statusJson(ShopeeService $service)
    {
        $this->requireAdmin();

        return response()->json([
            'success' => true,
            'authorization' => $service->getAuthorizationStatus(),
            'redirect_url' => $this->redirectUrl(request()),
        ]);
    }

    public function redirectToShopee(Request $request)
    {
        $this->requireAdmin();

        if ($this->partnerId === '' || $this->partnerKey === '') {
            return redirect()
                ->route('w68.shopee.authorization')
                ->with('shopee_error', 'SHOPEE_PARTNER_ID or SHOPEE_PARTNER_KEY is missing from the server configuration.');
        }

        $path = '/api/v2/shop/auth_partner';
        $timestamp = time();
        $baseString = $this->partnerId . $path . $timestamp;
        $sign = hash_hmac('sha256', $baseString, $this->partnerKey, false);

        $authUrl = $this->baseUrl . $path . '?' . http_build_query([
            'partner_id' => (int) $this->partnerId,
            'timestamp' => $timestamp,
            'sign' => $sign,
            'redirect' => $this->redirectUrl($request),
        ]);

        return redirect()->away($authUrl);
    }

    public function handleCallback(Request $request, ShopeeService $service)
    {
        $this->requireAdmin();

        $code = trim((string) $request->query('code', ''));
        $shopId = trim((string) $request->query('shop_id', ''));

        if ($code === '' || $shopId === '') {
            return redirect()
                ->route('w68.shopee.authorization')
                ->with('shopee_error', 'Shopee authorization was denied or the callback did not contain code/shop_id.');
        }

        if ($this->partnerId === '' || $this->partnerKey === '') {
            return redirect()
                ->route('w68.shopee.authorization')
                ->with('shopee_error', 'Shopee Partner ID / Partner Key is not configured.');
        }

        $path = '/api/v2/auth/token/get';
        $timestamp = time();
        $baseString = $this->partnerId . $path . $timestamp;
        $sign = hash_hmac('sha256', $baseString, $this->partnerKey, false);

        $apiUrl = $this->baseUrl . $path . '?' . http_build_query([
            'partner_id' => (int) $this->partnerId,
            'timestamp' => $timestamp,
            'sign' => $sign,
        ]);

        try {
            $response = Http::connectTimeout(5)
                ->timeout(15)
                ->withOptions(['verify' => false])
                ->asJson()
                ->post($apiUrl, [
                    'code' => $code,
                    'partner_id' => (int) $this->partnerId,
                    'shop_id' => (int) $shopId,
                ]);

            $data = $response->json();
            $data = is_array($data) ? $data : [];

            if (!$response->successful() || !empty($data['error'])) {
                $error = trim((string) ($data['error'] ?? ('HTTP ' . $response->status())));
                $message = trim((string) ($data['message'] ?? 'Shopee rejected the authorization code.'));

                Log::error('Shopee authorization token exchange failed.', [
                    'error' => $error,
                    'message' => $message,
                    'shop_id' => $shopId,
                ]);

                return redirect()
                    ->route('w68.shopee.authorization')
                    ->with('shopee_error', 'Shopee authorization failed: ' . $error . ' - ' . $message);
            }

            $accessToken = trim((string) ($data['access_token'] ?? ''));
            $refreshToken = trim((string) ($data['refresh_token'] ?? ''));

            if ($accessToken === '' || $refreshToken === '') {
                return redirect()
                    ->route('w68.shopee.authorization')
                    ->with('shopee_error', 'Shopee returned an incomplete token response. Please authorize again.');
            }

            $accessExpiresIn = max(60, (int) ($data['expire_in'] ?? 14400));
            $refreshExpiresIn = isset($data['refresh_token_expire_in'])
                ? max(60, (int) $data['refresh_token_expire_in'])
                : null;
            $authorizationExpiresIn = isset($data['authorization_expire_in'])
                ? max(60, (int) $data['authorization_expire_in'])
                : null;

            $service->storeAuthorizationTokens(
                $shopId,
                $accessToken,
                $refreshToken,
                $accessExpiresIn,
                $refreshExpiresIn,
                $authorizationExpiresIn
            );

            Log::info('Shopee shop authorization saved successfully.', [
                'shop_id' => $shopId,
                'access_expires_in' => $accessExpiresIn,
            ]);

            return redirect()
                ->route('w68.shopee.authorization')
                ->with('shopee_success', 'Shopee authorization completed. W68 will now rotate the access and refresh tokens automatically.');
        } catch (\Throwable $e) {
            Log::error('Shopee authorization callback failed.', [
                'message' => $e->getMessage(),
                'shop_id' => $shopId,
            ]);

            return redirect()
                ->route('w68.shopee.authorization')
                ->with('shopee_error', 'Shopee authorization failed: ' . $e->getMessage());
        }
    }

    public function manualRefresh(ShopeeService $service)
    {
        $this->requireAdmin();

        if ($service->refreshAccessToken(true)) {
            return redirect()
                ->route('w68.shopee.authorization')
                ->with('shopee_success', 'Shopee tokens were refreshed and the new token pair was saved.');
        }

        return redirect()
            ->route('w68.shopee.authorization')
            ->with('shopee_error', $service->requiresReauthorization()
                ? 'The Shopee refresh token can no longer be used. Click Authorize / Reauthorize Shopee.'
                : ('Shopee token refresh failed. ' . ($service->getLastAuthError() ?? 'Check the Laravel log.')));
    }

    private function redirectUrl(Request $request): string
    {
        $configured = trim((string) config('services.shopee.redirect_url', ''));
        if ($configured !== '') {
            return $configured;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/') . '/admin/shopee/callback';
    }

    private function requireAdmin(): void
    {
        $user = session('user');
        if (is_array($user)) {
            $user = (object) $user;
        }

        if (!$user || (int) ($user->account_type ?? 0) !== 1) {
            abort(403, 'Only an Admin user can authorize or reauthorize Shopee.');
        }
    }
}
