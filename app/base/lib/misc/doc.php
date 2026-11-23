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
 
class base_misc_doc{

    function display($path){

        list(,,$app,$file) = explode('/',$path);
        $app = basename($app);
        $file = realpath(app::get($app)->app_dir.'/docs/'.basename($file));

        if(!$file){
            //404
        }

        echo <<<EOF
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<HTML>
<HEAD>
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=utf-8">
<style>
html{background:#E1ECFE;font-family: Arial, sans-serif;font-size:0.8em}
body{width:680px;margin:auto;background:#fff;padding:20px}
h1{padding:20px 0;}
h1,h2,h3,h4,h5{text-shadow: 1px 1px 1px #aaa;}
pre{padding:10px;border:1px solid #ccc;background:#f0f0f0;}
pre,code{font-family: 'Andale Mono', 'Lucida Console', Monaco, fixed, monospace;font-size:11px}
code{color:#009}
</style>
EOF;

        $t2t_parser = kernel::single('base_misc_t2t');
        $t2t_parser->res_path = app::get($app)->res_url.'/../docs/';
        $t2t_parser->load($file);
        echo '<h1>'.$t2t_parser->title.'</h1>';
        $t2t_parser->display();

        echo <<<EOF
</body>
</html>
EOF;

    }

}
