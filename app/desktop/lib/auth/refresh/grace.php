<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Refresh rotation grace period (宽限期幂等, Auth0/Firebase-style rotation leeway).
 *
 * Problem: N tabs fire act=refresh concurrently with the same refresh cookie.
 * The first consume blacklists the old jti; tabs that lose the race (kvstore
 * write latency / parallel reads before the blacklist lands) fail with
 * "Token has been revoked" and revokeSessionOnRefreshReuse() then bumps
 * token_version — killing every tab/device of the account for a race that is
 * not theft.
 *
 * Fix: when a refresh token is consumed, record a grace marker keyed by the
 * OLD jti (TTL = JWT_REFRESH_GRACE_SECONDS, default 90s). When that same jti
 * is replayed within the grace window, the replay is treated as the SAME
 * logical rotation: the stored claims are returned and rotation proceeds
 * normally — no failure, no family revocation. Replays outside the window
 * (or jti never legitimately consumed) keep the full theft response.
 *
 * Note on parallel races: several consumes may all succeed before any marker
 * lands (each issues its own new tokens). Each success records the marker, so
 * the losing replays afterwards still resolve idempotently to a stored claim
 * set and get a fresh rotation — every tab ends up logged in.
 *
 * All failures are fail-closed: any storage error surfaces as "marker absent"
 * and the existing theft response applies.
 */
class desktop_auth_refresh_grace
{
    private $kvstoreKey = 'pam_token_grace';

    /** @var base_kvstore|null lazily-created; null when kvstore is unavailable */
    private $kvstore;

    public function __construct()
    {
        try {
            $this->kvstore = base_kvstore::instance($this->kvstoreKey);
        } catch (Throwable $e) {
            $this->kvstore = null;
        }
    }

    /**
     * Grace window in seconds (JWT_REFRESH_GRACE_SECONDS, default 90).
     */
    public static function windowSeconds(): int
    {
        $configured = defined('JWT_REFRESH_GRACE_SECONDS') ? (int)constant('JWT_REFRESH_GRACE_SECONDS') : 90;
        if ($configured <= 0) {
            return 90;
        }

        return min($configured, 600);
    }

    /**
     * Record that $oldJti was just consumed and rotation produced $newClaims.
     *
     * Best-effort: storage failures must not break the happy rotation path.
     */
    public function record(string $oldJti, array $newClaims): void
    {
        if ($oldJti === '' || $this->kvstore === null) {
            return;
        }

        try {
            $this->kvstore->store(
                $this->key($oldJti),
                json_encode($newClaims),
                self::windowSeconds()
            );
        } catch (Throwable $e) {
            kernel::log('refresh grace record failed: ' . $e->getMessage());
        }
    }

    /**
     * Stored claims for a replayed jti still inside the grace window.
     *
     * @return array<string, mixed>|null null = no grace (theft response applies)
     */
    public function claimsFor(string $oldJti): ?array
    {
        if ($oldJti === '' || $this->kvstore === null) {
            return null;
        }

        try {
            $raw = null;
            if (!$this->kvstore->fetch($this->key($oldJti), $raw) || !is_string($raw)) {
                return null;
            }

            $claims = json_decode($raw, true);

            return is_array($claims) ? $claims : null;
        } catch (Throwable $e) {
            kernel::log('refresh grace lookup failed: ' . $e->getMessage());

            return null;
        }
    }

    private function key(string $jti): string
    {
        return 'grace_' . md5($jti);
    }
}
