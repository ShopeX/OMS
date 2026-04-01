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
class eccommon_task
{

    public function post_install()
    {
        kernel::log('Initial eccommon');
        //kernel::single('base_initial', 'eccommon')->init();

        kernel::log('Initial Regions');
        kernel::single('eccommon_regions_mainland')->install();
    }//End Function
}//End Class
