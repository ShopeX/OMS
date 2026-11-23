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
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 
class service implements Iterator{
    
    function __construct($service_define,$filter=null){
        $this->iterator = new ArrayIterator($service_define['list']);
        $this->interface = $service_define['interface'];
        $this->filter = $filter;
        $this->valid();
    }

    function current(){
        return $this->current_object;
    }
    
    public function rewind() {
        $this->iterator->rewind();
    }

    public function key() {
        return $this->iterator->current();
    }

    public function next() {
        return $this->iterator->next();
    }

    public function valid() {
        while($this->iterator->valid()){
            if($this->filter()){
                return true;
            }else{
                $this->iterator->next();
            }
        };
        return false;
    }
    
    private function filter(){
		if ($this->filter){
			$current = $this->iterator->current();
			if (is_array($this->filter) && !in_array($current,$this->filter)) $this->iterator->next();
			if (!is_array($this->filter) && $this->filter != $current) $this->iterator->next();
		}
		$current = $this->iterator->current();
        if($current){
            try{
                $this->current_object = kernel::single($current);
            }catch(Throwable $th){
                kernel::log($current.' service '.$th->getMessage());
                return false;
            }
            if($this->current_object){
                if($this->interface && $this->current_object instanceof $this->interface){
                    return false;
                }
                return true;
            }
        }
        return false;
    }

}


