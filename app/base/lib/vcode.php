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
 
class base_vcode {

    var $use_gd = false;
    private $kvstoreKey = 'base_vcode';

    function __construct() {
        if ($this->use_gd) {
            $this->obj = kernel::single('base_vcode_gd');
        } else {
            $this->obj = kernel::single('base_vcode_gif');
        }
    }

    function length($len) {
        $this->obj->length($len);
        return true;
    }

    function verify_key($key) {
        $code = $this->obj->get_code();
        $kvstore = $this->getKvStore();
        $kvstore->store($this->kvstoreKey . '_' . $key, $code, 300);
    }

    static function verify($key, $value) {
        $kvstore = base_kvstore::instance('base_vcode');
        $storedCode = null;
        $kvstore->fetch('base_vcode_' . $key, $storedCode);

        if ($storedCode && $storedCode == $value) {
            $kvstore->store('base_vcode_' . $key, '', 1);
            return true;
        }
        return false;
    }

    private function getKvStore() {
        return base_kvstore::instance('base_vcode');
    }

    function display() {
        $this->obj->display();
    }
}
