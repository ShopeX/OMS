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

final class mcpapi_migration_owner_backfill
{
    public const UNMAPPED_REASON = 'owner_unmapped_migration';

    public function transform(array $rows, callable $userExists): array
    {
        foreach ($rows as &$row) {
            if (!is_array($row) || (int) ($row['owner_user_id'] ?? 0) > 0
                || (($row['revoked_reason'] ?? null) === self::UNMAPPED_REASON && !(bool) ($row['enabled'] ?? false))) {
                continue;
            }
            $legacyOwnerId = (int) ($row['oms_subject_id'] ?? 0);
            if ($legacyOwnerId > 0 && $userExists($legacyOwnerId)) {
                $row['owner_user_id'] = $legacyOwnerId;
                continue;
            }
            $row['owner_user_id'] = 0;
            $row['enabled'] = 0;
            $row['revoked_at'] = time();
            $row['revoked_reason'] = self::UNMAPPED_REASON;
        }
        unset($row);
        return $rows;
    }
}
