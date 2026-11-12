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

final class mcpapi_task
{
    public function pre_update($options = null): void
    {
        unset($options);
        $this->ensureClientKeyAutoIncrement();
        $this->backfillOwners();
    }

    private function ensureClientKeyAutoIncrement(): void
    {
        $db = kernel::database();
        $table = $db->prefix . 'mcpapi_client_key';
        $exists = $db->select('SHOW TABLES LIKE "' . addslashes($table) . '"');
        if (!$exists) {
            return;
        }

        $db->exec(
            'ALTER TABLE `' . addslashes($table) . '` MODIFY `client_key_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT',
            true
        );
    }

    private function backfillOwners(): void
    {
        $model = app::get('mcpapi')->model('client');
        $rows = $model->getList('*', ['owner_user_id' => 0], 0, -1);
        if (!$rows) {
            return;
        }

        $migration = new mcpapi_migration_owner_backfill();
        $rows = $migration->transform($rows, static function (int $userId): bool {
            return (bool) app::get('desktop')->model('users')->dump($userId, 'user_id');
        });

        foreach ($rows as $row) {
            $model->update(
                [
                    'owner_user_id' => (int) ($row['owner_user_id'] ?? 0),
                    'enabled' => (int) ($row['enabled'] ?? 0),
                    'revoked_at' => $row['revoked_at'] ?? null,
                    'revoked_reason' => $row['revoked_reason'] ?? null,
                ],
                ['client_id' => $row['client_id']]
            );
        }
    }
}
