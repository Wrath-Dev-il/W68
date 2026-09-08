<?php

namespace App\Http\Controllers;

use App\Services\DataSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class DataSyncController extends Controller
{
    public function config(DataSyncService $service): JsonResponse
    {
        if (!$this->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        try {
            return response()->json(array_merge([
                'success' => true,
            ], $service->configuration()));
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function run(Request $request, DataSyncService $service): JsonResponse
    {
        if (!$this->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $connection = trim((string) $request->input('connection', ''));
        $table = trim((string) $request->input('table', ''));
        $offset = max(0, (int) $request->input('offset', 0));

        $knownTotal = $request->has('knownTotal')
            ? max(0, (int) $request->input('knownTotal'))
            : null;

        $limit = $request->has('limit')
            ? max(50, min(1000, (int) $request->input('limit')))
            : null;

        try {
            $result = $service->pushChunk(
                $connection,
                $table,
                $offset,
                $knownTotal,
                $limit
            );

            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Token-protected HostForge receiver. This endpoint intentionally does not
     * use the browser admin session because localhost pushes server-to-server.
     */
    public function receive(Request $request, DataSyncService $service): JsonResponse
    {
        $token = (string) $request->header('X-Datasync-Token', '');
        $connection = trim((string) $request->input('connection', ''));
        $table = trim((string) $request->input('table', ''));
        $rows = $request->input('rows', []);

        if (!is_array($rows)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid rows payload.',
            ], 422);
        }

        try {
            $result = $service->receiveChunk(
                $token,
                $connection,
                $table,
                $rows
            );

            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'token')
                ? 403
                : 422;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'DataSync receiver failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function isAdmin(): bool
    {
        $user = session('user');

        if (!$user) {
            return false;
        }

        if (is_array($user)) {
            $user = (object) $user;
        }

        return (int) ($user->account_type ?? 0) === 1;
    }
}
