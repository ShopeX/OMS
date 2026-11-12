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
 * Session-family revocation for JWT auth (SCRUM-7917 Plan B).
 *
 * Every browser login mints a new session family id (claim `fid`) that is
 * shared by all access/refresh tokens derived from that login (refresh
 * rotation inherits the fid). Revoking a family invalidates every token of
 * that one browser session without touching other concurrent sessions of
 * the same account (which the global token_version bump would kick offline).
 *
 * Storage: kvstore key per family with an explicit TTL covering the maximum
 * possible remaining refresh lifetime (JWT_REFRESH_TTL + slack), so entries
 * self-expire and steady-state key count ≈ manual logouts per TTL window.
 * Tokens issued before this feature carry no fid and are never family-
 * revoked (graceful no-op), keeping the rollout backward compatible.
 */
class desktop_auth_family_revoker
{
    /** @var string kvstore instance segment */
    private $kvstoreKey = 'pam_token_family';

    /** @var base_kvstore */
    private $kvstore;

    public function __construct()
    {
        $this->kvstore = base_kvstore::instance($this->kvstoreKey);
    }

    /**
     * Revoke every token of a session family.
     *
     * @param string $familyId non-empty fid claim
     * @param int    $ttl      seconds to keep the revocation marker; must be > 0
     *                         (kvstore store() with ttl=0 is silently dropped by
     *                         some backends — see oms-kvstore-usage skill)
     */
    public function revoke(string $familyId, int $ttl): void
    {
        if ($familyId === '' || $ttl <= 0) {
            return;
        }
        $key = 'family_' . $familyId;
        if (method_exists($this->kvstore, 'setcr')) {
            // Redis/memcached backends: base_kvstore store() issues a plain SET
            // and only lazy-expires on fetch — unre-fetched keys would pile up
            // forever (unbounded growth under a login+logout flood). setcr()
            // uses SETEX so the OS evicts the key natively. The payload keeps
            // the same JSON envelope store()/fetch() use, staying read-compatible.
            $envelope = json_encode(array('value' => '1', 'dateline' => time(), 'ttl' => $ttl));
            $this->kvstore->setcr($key, $envelope, $ttl);
        } else {
            // Backends without native TTL (e.g. mysql): rows carry the ttl column
            // and are bounded by table storage; expiry stays lazy via fetch().
            $this->kvstore->store($key, '1', $ttl);
        }
    }

    /**
     * True when the session family has been revoked.
     * Empty/missing fid (legacy tokens) is never revoked.
     */
    public function isRevoked(?string $familyId): bool
    {
        if ($familyId === null || $familyId === '') {
            return false;
        }
        $value = null;
        $this->kvstore->fetch('family_' . $familyId, $value);
        return $value === '1';
    }

    /**
     * TTL for a revocation marker: remaining refresh lifetime of the family
     * plus slack. Falls back to the configured refresh TTL when the exact
     * expiry is unknown (logout via access-token-only path).
     */
    public static function markerTtl(?int $familyExpiresAt = null): int
    {
        $refreshTtl = desktop_auth_jwt::ttl('refresh');
        if ($familyExpiresAt !== null && $familyExpiresAt > time()) {
            return max($familyExpiresAt - time(), 1) + 60;
        }
        return $refreshTtl + 60;
    }
}
