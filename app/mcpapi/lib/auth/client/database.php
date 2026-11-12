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

declare(strict_types=1);

final class mcpapi_auth_client_database implements mcpapi_auth_client_registry
{
    private $rowLoader;
    private $principalExists;
    private int $now;

    public function __construct(?callable $rowLoader = null, ?callable $principalExists = null, ?int $now = null)
    {
        $this->rowLoader = $rowLoader ?? static function (string $clientId): array {
            return app::get('mcpapi')->model('client')->getList('*', ['client_id' => $clientId], 0, 2);
        };
        $this->principalExists = $principalExists ?? static function (int $subjectId): bool {
            return (bool) app::get('pam')->model('account')->db_dump(['account_id' => $subjectId], 'account_id');
        };
        $this->now = $now ?? time();
    }

    public function find(string $clientId): ?mcpapi_auth_client_record
    {
        if ($clientId === '') {
            return null;
        }

        $rows = ($this->rowLoader)($clientId);
        if (!is_array($rows) || count($rows) !== 1 || !is_array($rows[0])) {
            return null;
        }

        $row = $rows[0];
        if (($row['client_id'] ?? null) !== $clientId) {
            return null;
        }

        $baseUrl = $this->canonicalBaseUrl($row['oms_base_url'] ?? null);
        $ownerUserId = $this->integer($row['owner_user_id'] ?? null) ?? 0;
        $subjectId = $this->integer($row['oms_subject_id'] ?? null);
        if ($ownerUserId > 0 && $subjectId === null) {
            $subjectId = $ownerUserId;
        }
        $expiresAt = $this->integer($row['expires_at'] ?? null);
        $revokedAt = $row['revoked_at'] === null ? null : $this->integer($row['revoked_at']);
        $roleIds = $this->arrayValue($row['oms_role_ids'] ?? null);
        $permissions = $this->arrayValue($row['oms_permissions'] ?? null);
        $scopes = $this->arrayValue($row['scopes'] ?? null);

        if ($baseUrl === null || $subjectId === null || $subjectId < 1 || $expiresAt === null
            || $expiresAt <= $this->now || ($revokedAt !== null && $revokedAt <= $this->now)
            || !is_string($row['tenant_id'] ?? null) || $row['tenant_id'] === ''
            || !$this->isEnabled($row['enabled'] ?? null)
            || !is_array($roleIds) || (!$ownerUserId && !$this->validRoleIds($roleIds))
            || !is_array($permissions) || (!$ownerUserId && !$this->validStrings($permissions))
            || !is_array($scopes) || !$this->validScopes($scopes)
            || !($this->principalExists)($subjectId)) {
            return null;
        }

        return new mcpapi_auth_client_record(
            $clientId,
            $row['tenant_id'],
            $baseUrl,
            $subjectId,
            $roleIds,
            $permissions,
            $scopes,
            (bool) $row['enabled'],
            $revokedAt,
            $expiresAt,
            $this->stringValue($row['created_at'] ?? null),
            $this->stringValue($row['updated_at'] ?? null),
            $this->stringValue($row['created_by'] ?? null),
            $this->stringValue($row['updated_by'] ?? null),
            $row['revoked_reason'] === null ? null : $this->stringValue($row['revoked_reason']),
            $ownerUserId
        );
    }

    public function verify(string $clientId, string $clientSecret): ?mcpapi_auth_client_record
    {
        if ($clientId === '' || $clientSecret === '') {
            return null;
        }
        $rows = ($this->rowLoader)($clientId);
        if (!is_array($rows) || count($rows) !== 1 || !is_array($rows[0])) {
            return null;
        }
        $hash = $rows[0]['client_secret_hash'] ?? null;
        if (!is_string($hash) || !password_verify($clientSecret, $hash)) {
            return null;
        }
        return $this->find($clientId);
    }

    private function canonicalBaseUrl($value): ?string
    {
        if (!is_string($value) || $value === '' || preg_match('/\s/', $value)) {
            return null;
        }

        $parts = parse_url($value);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || (isset($parts['user'], $parts['pass'])
                || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/'))
            || isset($parts['query'], $parts['fragment'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? null;
        if ($port !== null && ($port < 1 || $port > 65535)) {
            return null;
        }

        return strtolower($parts['scheme']) . '://' . $host . ($port === null ? '' : ':' . $port);
    }

    private function arrayValue($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return null;
        }

        $decoded = @unserialize($value, ['allowed_classes' => false]);
        return is_array($decoded) ? $decoded : null;
    }

    private function integer($value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?[0-9]+$/', $value)) {
            return (int) $value;
        }
        return null;
    }

    private function stringValue($value): string
    {
        return is_string($value) ? $value : '';
    }

    private function validRoleIds(array $roleIds): bool
    {
        foreach ($roleIds as $roleId) {
            if ($this->integer($roleId) === null || (int) $roleId < 1) {
                return false;
            }
        }
        return true;
    }

    private function validScopes(array $scopes): bool
    {
        return $this->validStrings($scopes);
    }

    private function validStrings(array $values): bool
    {
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                return false;
            }
        }
        return true;
    }

    private function isEnabled($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array($value, [1, '1', 'true'], true);
    }
}
