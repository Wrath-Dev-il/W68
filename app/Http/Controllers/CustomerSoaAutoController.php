<?php

namespace App\Http\Controllers;

use App\Services\CustomerAutoSoaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerSoaAutoController extends Controller
{
    public function status(int $customerId, CustomerAutoSoaService $service): JsonResponse
    {
        $this->authorizeEmployeeRoute();

        try {
            return response()->json([
                'success' => true,
                ...$service->configurationPayload($customerId),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function save(Request $request, int $customerId, CustomerAutoSoaService $service): JsonResponse
    {
        $user = $this->authorizeEmployeeRoute();

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'lead_value' => ['required', 'integer', 'min:1', 'max:525600'],
            'lead_unit' => ['required', Rule::in(['minutes', 'days', 'months'])],
        ]);

        try {
            $payload = $service->saveConfiguration(
                $customerId,
                (bool) $validated['enabled'],
                (int) $validated['lead_value'],
                (string) $validated['lead_unit'],
                $this->actorIdentifier($user)
            );

            return response()->json([
                'success' => true,
                'message' => (bool) $validated['enabled']
                    ? 'SOA(AUTO) configuration enabled for this customer.'
                    : 'SOA(AUTO) configuration saved and disabled for this customer.',
                ...$payload,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    private function authorizeEmployeeRoute(): object
    {
        $user = session('user');
        abort_unless($user, 401, 'Unauthorized');

        if (is_array($user)) {
            $user = (object) $user;
        }

        $accountType = (int) ($user->account_type ?? 0);
        abort_unless(in_array($accountType, [1, 2, 3], true), 403, 'Employee account required.');

        $routeName = (string) (request()->route()?->getName() ?? '');
        $expected = match ($accountType) {
            1 => 'admin.customer-master.soa-auto.',
            2 => 'regular.customer-master.soa-auto.',
            3 => 'special.customer-master.soa-auto.',
            default => '',
        };

        if ($routeName !== '' && $expected !== '' && !str_starts_with($routeName, $expected)) {
            abort(403, 'Use the Customer Master route assigned to your account type.');
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
