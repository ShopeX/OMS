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

final class mcpapi_auth_client_record
{
    private string $clientId;
    private string $tenantId;
    private string $omsBaseUrl;
    private int $omsSubjectId;
    private array $omsRoleIds;
    private array $omsPermissions;
    private array $scopes;
    private bool $enabled;
    private ?int $revokedAt;
    private int $expiresAt;
    private string $createdAt;
    private string $updatedAt;
    private string $createdBy;
    private string $updatedBy;
    private ?string $revokedReason;
    private int $ownerUserId;

    public function __construct(string $clientId, string $tenantId, ...$arguments)
    {
        if (count($arguments) === 4 && is_array($arguments[0])) {
            [$scopes, $enabled, $revokedAt, $expiresAt] = $arguments;
            $omsBaseUrl = '';
            $omsSubjectId = 0;
            $omsRoleIds = [];
            $omsPermissions = [];
            $createdAt = '';
            $updatedAt = '';
            $createdBy = '';
            $updatedBy = '';
            $revokedReason = null;
            $ownerUserId = 0;
        } else {
            $values = array_pad($arguments, 14, null);
            [
                $omsBaseUrl,
                $omsSubjectId,
                $omsRoleIds,
                $omsPermissions,
                $scopes,
                $enabled,
                $revokedAt,
                $expiresAt,
                $createdAt,
                $updatedAt,
                $createdBy,
                $updatedBy,
                $revokedReason,
                $ownerUserId,
            ] = $values;
        }
        $this->clientId = $clientId;
        $this->tenantId = $tenantId;
        $this->omsBaseUrl = (string) $omsBaseUrl;
        $this->omsSubjectId = (int) $omsSubjectId;
        $this->omsRoleIds = array_values($omsRoleIds);
        $this->omsPermissions = array_values($omsPermissions);
        $this->scopes = array_values($scopes);
        $this->enabled = (bool) $enabled;
        $this->revokedAt = $revokedAt;
        $this->expiresAt = (int) $expiresAt;
        $this->createdAt = (string) $createdAt;
        $this->updatedAt = (string) $updatedAt;
        $this->createdBy = (string) $createdBy;
        $this->updatedBy = (string) $updatedBy;
        $this->revokedReason = $revokedReason;
        $this->ownerUserId = (int) $ownerUserId;
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function omsBaseUrl(): string
    {
        return $this->omsBaseUrl;
    }

    public function omsSubjectId(): int
    {
        return $this->omsSubjectId;
    }

    public function omsRoleIds(): array
    {
        return $this->omsRoleIds;
    }

    public function omsPermissions(): array
    {
        return $this->omsPermissions;
    }

    public function scopes(): array
    {
        return $this->scopes;
    }

    public function revokedAt(): ?int
    {
        return $this->revokedAt;
    }

    public function expiresAt(): int
    {
        return $this->expiresAt;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    public function updatedAt(): string
    {
        return $this->updatedAt;
    }

    public function createdBy(): string
    {
        return $this->createdBy;
    }

    public function updatedBy(): string
    {
        return $this->updatedBy;
    }

    public function revokedReason(): ?string
    {
        return $this->revokedReason;
    }

    public function ownerUserId(): int
    {
        return $this->ownerUserId;
    }

    public function isUsableAt(int $now): bool
    {
        return $this->enabled
            && ($this->revokedAt === null || $this->revokedAt > $now)
            && $this->expiresAt > $now;
    }
}
