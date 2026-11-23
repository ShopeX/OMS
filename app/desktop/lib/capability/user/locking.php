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

final class desktop_capability_user_locking
{
    private object $model;
    private object $database;

    public function __construct(?object $model = null, ?object $database = null)
    {
        $this->model = $model ?? app::get('desktop')->model('users');
        $this->database = $database ?? kernel::database();
    }

    public function unlock(int $userId): array
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('用户ID不能为空');
        }

        $transaction = $this->database->beginTransaction();
        try {
            if (!$this->model->update(['is_lock' => '0', 'lock_reason' => ''], ['user_id' => $userId])) {
                throw new RuntimeException('解锁失败');
            }

            $this->database->commit($transaction);
            return ['success' => true];
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }
    }
}
