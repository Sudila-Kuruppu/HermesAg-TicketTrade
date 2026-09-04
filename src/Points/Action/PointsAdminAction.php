<?php

/**
 * TicketTrade — Points\Action\PointsAdminAction
 *
 * Phase 6 Plan 06-02 ships the methods; Phase 8 wires the routes
 * (`POST /admin/points/void`, `POST /admin/points/clear-freeze`).
 * The class is unreachable from HTTP in Phase 6 because
 * `config/routes.php` is intentionally UNCHANGED — per D-02 the
 * admin UI for points management lands in Phase 8.
 *
 * Gating (mirrors `Admin\Action\CronAction` per AD-19):
 *   - Router opts.admin=true → non-admin gets 404 (D-10).
 *   - Router opts.csrf=true → POST must carry a CSRF token.
 *   - `Support\Auth::requireReAuth(300)` → 403 JSON on stale.
 *   - Phase 8 also wires a rate limit (the existing `admin_cron`
 *     bucket or a new `admin_points` bucket — TBD with the Phase 8
 *     planner).
 *
 * Phase 6 ships the class body so:
 *   - the methods are reachable from Phase 8's admin UI directly;
 *   - the re-auth envelope is enforced inside the method body
 *     (defense-in-depth in case Phase 8 forgets the router opts);
 *   - the AD-16 envelope shape is locked before Phase 8 builds
 *     the UI on top.
 */

declare(strict_types=1);

namespace App\Points\Action;

use App\Points\Service\points_service;
use App\Support\Auth as AuthGuard;

class PointsAdminAction
{
    /**
     * POST /admin/points/void  (Phase 8 route)
     *
     * Validates $_POST['user_id'] (int), $_POST['delta'] (int > 0),
     * and $_POST['reason'] (string, non-empty) before calling
     * points_service::voidPoints().
     *
     * @return void Emits the JSON envelope and exits.
     */
    public function handleVoidPoints(): void
    {
        $user = AuthGuard::requireReAuth(300);
        $actorUserId = (int) ($user['user_id'] ?? 0);

        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $delta = filter_input(INPUT_POST, 'delta', FILTER_VALIDATE_INT);
        $reason = isset($_POST['reason']) ? trim((string) $_POST['reason']) : '';

        if ($userId === false || $userId === null || $userId <= 0) {
            $this->emitValidationError('user_id must be a positive integer.');
        }
        if ($delta === false || $delta === null || $delta <= 0) {
            $this->emitValidationError('delta must be a positive integer.');
        }
        if ($reason === '') {
            $this->emitValidationError('reason must be non-empty.');
        }

        $result = points_service::voidPoints((int) $userId, (int) $delta, $reason);
        if ($result['ok'] === true) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'data' => $result['data'] ?? [],
                'actor_user_id' => $actorUserId,
            ]);
            exit;
        }
        $this->emitServiceError($result);
    }

    /**
     * POST /admin/points/clear-freeze  (Phase 8 route)
     *
     * Validates $_POST['user_id'] (int) then calls
     * points_service::clearPointsFreeze(). The Service writes its
     * own audit row 'points.unfrozen'; this Action does NOT double-
     * write (avoids redundant metadata in the audit trail).
     *
     * @return void Emits the JSON envelope and exits.
     */
    public function handleClearFreeze(): void
    {
        $user = AuthGuard::requireReAuth(300);
        $actorUserId = (int) ($user['user_id'] ?? 0);

        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        if ($userId === false || $userId === null || $userId <= 0) {
            $this->emitValidationError('user_id must be a positive integer.');
        }

        $result = points_service::clearPointsFreeze((int) $userId);
        if ($result['ok'] === true) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'data' => $result['data'] ?? [],
                'actor_user_id' => $actorUserId,
            ]);
            exit;
        }
        $this->emitServiceError($result);
    }

    /**
     * Internal: 400 + AD-16 error envelope for validation failures.
     */
    private function emitValidationError(string $message): void
    {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => [
                'code' => 'E_VALIDATION',
                'message' => $message,
            ],
        ]);
        exit;
    }

    /**
     * Internal: 500 + AD-16 error envelope for Service failures.
     */
    private function emitServiceError(array $result): void
    {
        $code = isset($result['error']['code']) ? (string) $result['error']['code'] : 'E_INTERNAL';
        $message = isset($result['error']['message']) ? (string) $result['error']['message'] : 'Service failed.';
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
        exit;
    }
}
