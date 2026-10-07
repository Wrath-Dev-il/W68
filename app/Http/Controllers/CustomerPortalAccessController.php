<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\W68PricelistUrl;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;

class CustomerPortalAccessController extends Controller
{
    public function status(int $customerId): JsonResponse
    {
        $this->assertSignedInUser();
        $customer = Customer::query()->findOrFail($customerId);

        if (!Schema::connection('masterlist')->hasTable('customer_portal_authorizations')) {
            return response()->json([
                'success' => false,
                'message' => 'Portal authorization table is missing. Run the W68 portal migration first.',
            ], 503);
        }

        $authorization = DB::connection('masterlist')
            ->table('customer_portal_authorizations')
            ->where('customer_id', $customerId)
            ->first();

        return response()->json([
            'success' => true,
            'customer' => [
                'id' => (int) $customer->id,
                'name' => (string) $customer->name,
            ],
            'authorization' => $this->authorizationPayload($authorization),
            'linked_account' => $this->linkedAccountPayload($customerId),
        ]);
    }

    public function generate(Request $request, int $customerId): JsonResponse
    {
        $user = $this->assertSignedInUser();
        $customer = Customer::query()->findOrFail($customerId);
        $validated = $this->validateValidity($request);

        if (!Schema::connection('masterlist')->hasTable('customer_portal_authorizations')) {
            return response()->json([
                'success' => false,
                'message' => 'Portal authorization table is missing. Run the W68 portal migration first.',
            ], 503);
        }

        $existing = DB::connection('masterlist')
            ->table('customer_portal_authorizations')
            ->where('customer_id', $customerId)
            ->first();

        if ($existing && Carbon::parse($existing->expires_at)->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => 'This customer already has an active portal authorization. Use Change Validity or Delete Authorization.',
            ], 409);
        }

        $rawToken = bin2hex(random_bytes(32));
        $now = now();
        $expiresAt = $this->calculateExpiry(
            (int) $validated['validity_value'],
            (string) $validated['validity_unit']
        );

        $record = [
            'customer_id' => $customerId,
            'token_hash' => hash('sha256', $rawToken),
            'token_encrypted' => Crypt::encryptString($rawToken),
            'validity_value' => (int) $validated['validity_value'],
            'validity_unit' => (string) $validated['validity_unit'],
            'expires_at' => $expiresAt,
            'created_by' => $this->actorIdentifier($user),
            'updated_at' => $now,
        ];

        if ($existing) {
            $record['created_at'] = $now;
            DB::connection('masterlist')
                ->table('customer_portal_authorizations')
                ->where('id', $existing->id)
                ->update($record);
        } else {
            $record['created_at'] = $now;
            DB::connection('masterlist')
                ->table('customer_portal_authorizations')
                ->insert($record);
        }

        $authorization = DB::connection('masterlist')
            ->table('customer_portal_authorizations')
            ->where('customer_id', $customerId)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Portal authorization generated for ' . $customer->name . '.',
            'authorization' => $this->authorizationPayload($authorization),
            'linked_account' => $this->linkedAccountPayload($customerId),
        ]);
    }

    public function updateValidity(Request $request, int $customerId): JsonResponse
    {
        /* W68_CUSTOMER_PORTAL_CHANGE_PASSWORD_TEMP_20261007 */
        if ((string) $request->input('action', '') === 'reset_password') {
            return $this->resetLinkedAccountPassword($request, $customerId);
        }

        $this->assertSignedInUser();
        Customer::query()->findOrFail($customerId);
        $validated = $this->validateValidity($request);

        $authorization = DB::connection('masterlist')
            ->table('customer_portal_authorizations')
            ->where('customer_id', $customerId)
            ->first();

        if (!$authorization) {
            return response()->json([
                'success' => false,
                'message' => 'No portal authorization exists for this customer.',
            ], 404);
        }

        $expiresAt = $this->calculateExpiry(
            (int) $validated['validity_value'],
            (string) $validated['validity_unit']
        );

        DB::connection('masterlist')
            ->table('customer_portal_authorizations')
            ->where('id', $authorization->id)
            ->update([
                'validity_value' => (int) $validated['validity_value'],
                'validity_unit' => (string) $validated['validity_unit'],
                'expires_at' => $expiresAt,
                'updated_at' => now(),
            ]);

        $authorization = DB::connection('masterlist')
            ->table('customer_portal_authorizations')
            ->where('id', $authorization->id)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Portal authorization validity was updated.',
            'authorization' => $this->authorizationPayload($authorization),
            'linked_account' => $this->linkedAccountPayload($customerId),
        ]);
    }

    public function delete(Request $request, int $customerId): JsonResponse
    {
        if ((string) $request->input('target', '') === 'account') {
            return $this->deleteLinkedAccount($customerId);
        }

        $this->assertSignedInUser();
        Customer::query()->findOrFail($customerId);

        $deleted = DB::connection('masterlist')
            ->table('customer_portal_authorizations')
            ->where('customer_id', $customerId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => $deleted
                ? 'Portal authorization deleted. The old link and QR code are now invalid.'
                : 'No portal authorization existed for this customer.',
        ]);
    }

    /* W68_CUSTOMER_PORTAL_CHANGE_PASSWORD_TEMP_20261007 */
    private function resetLinkedAccountPassword(Request $request, int $customerId): JsonResponse
    {
        $this->assertPortalAccountManager();
        Customer::query()->findOrFail($customerId);

        $validated = $request->validate([
            'password' => ['required', 'string', 'max:255'],
        ]);

        if (!Schema::connection('mysql')->hasTable('customer_portal_accounts')) {
            return response()->json([
                'success' => false,
                'message' => 'Customer portal account linking is not configured.',
            ], 503);
        }

        $link = DB::connection('mysql')
            ->table('customer_portal_accounts')
            ->where('customer_id', $customerId)
            ->first();

        if (!$link) {
            return response()->json([
                'success' => false,
                'message' => 'This customer does not have a linked Pricelist account.',
            ], 404);
        }

        $login = DB::connection('mysql')
            ->table('logins')
            ->where('login_ID', (int) $link->login_id)
            ->first();

        if (!$login || (int) ($login->account_type ?? 0) !== 5) {
            return response()->json([
                'success' => false,
                'message' => 'The linked customer login could not be found.',
            ], 404);
        }

        /*
         * W68_CUSTOMER_PORTAL_CHANGE_PASSWORD_TEMP_20261007
         * Insert plain text password directly (NO HASHING)
         */
        $updates = [
            'Password' => (string) $validated['password'],
            'updated_at' => now(),
        ];

        if (Schema::connection('mysql')->hasColumn('logins', 'OTP_CODE')) {
            $updates['OTP_CODE'] = null;
        }

        DB::connection('mysql')
            ->table('logins')
            ->where('login_ID', (int) $link->login_id)
            ->where('account_type', 5)
            ->update($updates);

        if (
            Schema::connection('mysql')->hasTable('sessions')
            && Schema::connection('mysql')->hasColumn('sessions', 'user_id')
        ) {
            DB::connection('mysql')
                ->table('sessions')
                ->where('user_id', (int) $link->login_id)
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Customer password updated successfully as plain text.',
            'linked_account' => $this->linkedAccountPayload($customerId),
        ]);
    }

    private function deleteLinkedAccount(int $customerId): JsonResponse
    {
        $this->assertPortalAccountManager();
        Customer::query()->findOrFail($customerId);

        if (!Schema::connection('mysql')->hasTable('customer_portal_accounts')) {
            return response()->json([
                'success' => false,
                'message' => 'Customer portal account linking is not configured.',
            ], 503);
        }

        $link = DB::connection('mysql')
            ->table('customer_portal_accounts')
            ->where('customer_id', $customerId)
            ->first();

        if (!$link) {
            return response()->json([
                'success' => true,
                'message' => 'This customer does not have a linked Pricelist account.',
                'linked_account' => null,
            ]);
        }

        $login = DB::connection('mysql')
            ->table('logins')
            ->where('login_ID', (int) $link->login_id)
            ->first();

        if ($login && (int) ($login->account_type ?? 0) !== 5) {
            return response()->json([
                'success' => false,
                'message' => 'Delete stopped because the linked login is not a customer portal account.',
            ], 409);
        }

        DB::connection('mysql')->transaction(function () use ($link, $login): void {
            $loginId = (int) $link->login_id;

            if (
                Schema::connection('mysql')->hasTable('sessions')
                && Schema::connection('mysql')->hasColumn('sessions', 'user_id')
            ) {
                DB::connection('mysql')
                    ->table('sessions')
                    ->where('user_id', $loginId)
                    ->delete();
            }

            if (
                $login
                && !empty($login->Email)
                && Schema::connection('mysql')->hasTable('password_reset_tokens')
                && Schema::connection('mysql')->hasColumn('password_reset_tokens', 'email')
            ) {
                DB::connection('mysql')
                    ->table('password_reset_tokens')
                    ->where('email', (string) $login->Email)
                    ->delete();
            }

            DB::connection('mysql')
                ->table('customer_portal_accounts')
                ->where('id', (int) $link->id)
                ->delete();

            if ($login) {
                DB::connection('mysql')
                    ->table('logins')
                    ->where('login_ID', $loginId)
                    ->where('account_type', 5)
                    ->delete();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Pricelist account deleted. Customer Master and portal authorization were kept so the customer can register again.',
            'linked_account' => null,
        ]);
    }

    private function assertPortalAccountManager(): object
    {
        $user = $this->assertSignedInUser();
        $accountType = (int) ($user->account_type ?? 0);

        if (!in_array($accountType, [1, 2, 3], true)) {
            abort(403, 'This user is not allowed to manage customer portal accounts.');
        }

        return $user;
    }

    private function authorizationPayload(?object $authorization): ?array
    {
        if (!$authorization) {
            return null;
        }

        $expiresAt = Carbon::parse($authorization->expires_at);
        $active = $expiresAt->isFuture();
        $url = null;

        if ($active) {
            try {
                $token = Crypt::decryptString((string) $authorization->token_encrypted);
                $url = $this->portalBaseUrl() . '/authorized-access/' . rawurlencode($token);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return [
            'id' => (int) $authorization->id,
            'active' => $active,
            'expired' => !$active,
            'url' => $url,
            'validity_value' => (int) $authorization->validity_value,
            'validity_unit' => (string) $authorization->validity_unit,
            'expires_at' => $expiresAt->toIso8601String(),
            'created_at' => $authorization->created_at
                ? Carbon::parse($authorization->created_at)->toIso8601String()
                : null,
        ];
    }

    /* W68_CUSTOMER_PORTAL_VIEW_PASSWORD_20261007 */
    private function linkedAccountPayload(int $customerId): ?array
    {
        try {
            if (!Schema::connection('mysql')->hasTable('customer_portal_accounts')) {
                return null;
            }

            $link = DB::connection('mysql')
                ->table('customer_portal_accounts')
                ->where('customer_id', $customerId)
                ->first();

            if (!$link) {
                return null;
            }

            $login = DB::connection('mysql')
                ->table('logins')
                ->where('login_ID', $link->login_id)
                ->first(['login_ID', 'User_ID', 'Email', 'Password', 'account_type']);

            if (!$login) {
                return null;
            }

            $rawPassword = (string) ($login->Password ?? '');
            $isHashed = str_starts_with($rawPassword, '$2y$')
                || str_starts_with($rawPassword, '$2a$')
                || str_starts_with($rawPassword, '$2b$')
                || str_starts_with($rawPassword, '$argon2')
                || (str_starts_with($rawPassword, '$') && strlen($rawPassword) >= 30);

            return [
                'login_id' => (int) $link->login_id,
                'username' => (string) ($login->User_ID ?? ''),
                'email' => (string) ($login->Email ?? ''),
                'password' => $rawPassword,
                'is_hashed' => $isHashed,
                'linked_at' => $link->linked_at
                    ? Carbon::parse($link->linked_at)->toIso8601String()
                    : null,
                'password_protected' => true,
                'password_can_reset' => false,
                'account_can_delete' => true,
            ];
        } catch (Throwable $exception) {
            report($exception);
            return null;
        }
    }

    private function validateValidity(Request $request): array
    {
        return $request->validate([
            'validity_value' => ['required', 'integer', 'min:1', 'max:525600'],
            'validity_unit' => ['required', Rule::in(['minutes', 'hours', 'days'])],
        ]);
    }

    private function calculateExpiry(int $value, string $unit): Carbon
    {
        return match ($unit) {
            'minutes' => now()->addMinutes($value),
            'hours' => now()->addHours($value),
            'days' => now()->addDays($value),
            default => now()->addDays(30),
        };
    }

    private function portalBaseUrl(): string
    {
        return W68PricelistUrl::baseUrl(request());
    }

    private function assertSignedInUser(): object
    {
        $user = session('user');
        if (!$user) {
            abort(401, 'Unauthorized');
        }

        if (is_array($user)) {
            $user = (object) $user;
        }

        return $user;
    }

    private function actorIdentifier(object $user): string
    {
        return (string) (
            $user->User_ID
            ?? $user->username
            ?? $user->name
            ?? $user->login_ID
            ?? 'W68 User'
        );
    }
}
