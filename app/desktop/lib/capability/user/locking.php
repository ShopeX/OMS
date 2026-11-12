<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
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
