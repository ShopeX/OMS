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
 
class base_misc_man{

    function show($target){
        list($class,$func) = explode(':',$target);
        $ReflectionClass = new ReflectionClass($class);

        echo "\nClass ".$class."\n";
        $this->show_class_file($ReflectionClass);
        $this->show_doc($ReflectionClass);

        echo "\n";

        foreach($ReflectionClass->getProperties() as $property){
            $this->show_property($property);
        }

        echo "\n";

        foreach($ReflectionClass->getMethods() as $method){
            $this->show_method($method);
        }
    }

    function show_class_file($ReflectionClass){
        $file = $ReflectionClass->getFileName();
//        $sline = $ReflectionClass->getStartLine();
//        $eline = $ReflectionClass->getEndLine();
        echo "<$file>\n\n";
    }

    function show_property($ReflectionProperty){
        if($ReflectionProperty->isPublic()){
            echo $this->show_doc($ReflectionProperty);
            echo "  ",
                $ReflectionProperty->getName(),
                ' = ',
                str_replace("\n","\n           ",var_export($ReflectionProperty->getValue(),1)),
                ";\n\n";
        }
    }

    function show_method($ReflectionMethod){
        if($ReflectionMethod->isPublic()){
            echo $this->show_doc($ReflectionMethod);
            echo "  function ",
                $ReflectionMethod->getName(),
                ' (';
            foreach($ReflectionMethod->getParameters() as $ReflectionParameter){
                $this->show_parameter($ReflectionParameter);
            }
            echo ")\n\n";
        }
    }

    function show_parameter($ReflectionParameter){
    }

    function show_doc($object){
        $document = $object->getDocComment();
        if($document){
            echo $document."\n";
        }
    }
}
