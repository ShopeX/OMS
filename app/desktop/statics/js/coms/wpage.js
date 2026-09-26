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

(function() {


    this.$globalEval = Browser.exec;


    var processLinks = function() {



        $(document.body).addEvent('click',
        function(e) {



            var clickElement = $(e.target);
            /*if(clickElement){

                if(v=clickElement.get('disabled')){

                    if(v=='disabled'||v=='true'){return e.stop();}

                }

             }*/
            for (var i = 0; i < 3; i++) {
                if (!clickElement || $chk(clickElement.get('href'))) continue;
                clickElement = clickElement.getParent();
            }


            if (!clickElement || !clickElement.get('href')) {
                return null;
            }

            var ceTarget = clickElement.get('target') || '';
            var ceHref = clickElement.href || '';
            var ceLabel = clickElement.get('text') || '';
            var _matchFail = !((!$chk(ceTarget) || ceTarget.match(/({|:)/)) && !ceHref.match(/^javascript.+/i) && !clickElement.onclick);

            var regexp = new RegExp('' + SHOPADMINDIR + '');

            if (ceTarget.match(/blank/) && ceHref.match(regexp)) {
                e.stop();
                return _open(ceHref);
            }
            if (_matchFail) {
                return null;
            }





            e.stop();


            if (ceTarget.match(/::/)) {

                var clickOpt = ceTarget.split('::');

                switch (clickOpt[0]) {
                case 'dialog':
                    return new Dialog(ceHref, JSON.decode(clickOpt[1] || {}));
                case 'open':
                    return _open(ceHref, JSON.decode(clickOpt[1] || {}));
                case 'command':

                    return Ex_Loader('cmdrunner',
                    function() {
                        new cmdrunner(ceHref, JSON.decode(clickOpt[1] || {})).run();
                    });
                }


            }


            if (e.shift) return open(ceHref.replace('?', '#'));

            W.page(ceHref, $extend({

                method: 'get'
                /*,
                 data:$H({x_navlabel:ceLabel})*/


            },
            JSON.decode(ceTarget)), clickElement);


        });


    };









    var Wpage = this.Wpage = new Class({
        Extends: Request,
        exoptions: {
            evalScripts: true,
            link: 'cancel',
            message: false,
            render: true,
            sponsor: false,
            clearUpdateMap: true,
            updateMap: {},
            url: '',
            data: '',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/javascript, text/html, application/xml, text/xml, */*'
            },
            async: true,
            format: false,
            method: 'post',
            emulation: true,
            urlEncoded: true,
            encoding: 'utf-8',
            evalResponse: false,
            noCache: false,
            update: false
        },

        initialize: function(options, defaultAction) {
            options = $merge(this.exoptions, options);
            this.parent(options);

            processLinks();

            if (options.singlepage) return;

            if (typeof history.pushState != 'undefined') {
                /*html5 history plus*/
                var defaultGo = location.hash.slice(1) ? location.hash.slice(1) : defaultAction;
                this.page('index.php?' + defaultGo);
                window.onpopstate = function(event) {
                    this.popstate = true;
                    if (event.state) this.page('index.php?' + event.state.go);
                }.bind(this);

            } else {
                Ex_Loader('historyMan',
                function() {
                    var historyMan = new HistoryManager({
                        iframeSrc: SHOPBASE + '/app/desktop/view/blank.html'
                    });
                    this.hstMan = historyMan.register('page', [defaultAction],
                    function(values, dft) {
                        var url = 'index.php?' + (values.input ? values.input: dft[0]);
                        return this.page(url);
                    }.bind(this),
                    function(values) {
                        return values[0];
                    },
                    '.*');
                    historyMan.start();
                }.bind(this));

            }




        },
        success: function(html, xml) {

            //var requestTime = this.getHeader('Request_time');
            if ((/text\/jcmd/).test(this.getHeader('Content-type'))) {
                return this.doCommand.apply(this, $splat(arguments));
            }



            html = html.stripScripts(function(s) {
                this.response.javascript = s;
            }.bind(this));


            var match = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
            if (match) {
                html = match[1];
            }


            var update = this.options.update;

            var updateMap = this.options.updateMap;


            if (this.options.clearUpdateMap) {

                $H(updateMap).each(function(v, k) {
                    if (! (/side-/.test(k)) && v) {
                        v.empty().fixEmpty();
                    }
                });

            }

            html = html.replace(/<\!-{5}(.*?)-{5}([\s\S]*?)-{5}(.*?)-{5}>/g,
            function() {

                var $k = arguments[1];
                $k = updateMap[$k] || $($k);
                var $v = arguments[2] || null;

                if ($v && $k) {
                    $k.empty().set('html', $v).fixEmpty();
                }
                return '';
            });

            update.empty().set('html', html);

            if (update == LAYOUT.content_main) {
                var _v = this.options.url.match('index\\.php\\?(.*)');

                if (_v && _v[1]) {

                    if (typeof history.pushState != 'undefined') {
                        /*html5 history plus*/
                        history[this.popstate ? 'replaceState': 'pushState']({
                            go: _v[1]
                        },
                        '', '#' + _v[1]);
                        this.popstate = false;
                    } else if (this.hstMan) {
                        this.hstMan.setValue(0, _v[1]);
                    }
                }
            }



            this.render(update);

            if (this.options.evalScripts) {
                Browser.exec(this.response.javascript);
            }


            this.onSuccess(html, xml, this.response.javascript);
            this.onComplete();
        },

        onFailure: function() {

            switch (this.status) {

            case 404:
                new MessageBox(LANG_Wpage['failure']['status_404'], {
                    type: 'error',
                    autohide: true
                });
                break;
            case 401:
                new MessageBox(LANG_Wpage['failure']['status_401'], {
                    type: 'error'
                });
                break;
            case 403:
                new MessageBox(LANG_Wpage['failure']['status_401'], {
                    type: 'notice',
                    autohide: true
                });
                break;
            default:
                new MessageBox(LANG_Wpage['failure']['status'] + this.status + ']', {
                    type: 'error',
                    autohide: true
                });
            }

            this.parent();


        },
        doCommand: function(cmdstr, arg2) {

            var _this = this,
            cmd;

            if ($type(cmdstr) == 'string') {
                try {
                    cmd = JSON.decode(cmdstr);
                } catch(e) {}
            } else {
                cmd = cmdstr;
            }

            if (cmd && $type(cmd) != 'string') {

                cmd = $H(cmd);

                var _v1 = cmd.getKeys(),
                _v2 = cmd.getValues();

                return (new MessageBox(_v2[0], {
                    type: _v1[0] == 'success' ? 'default': _v1[0],
                    onHide: function() {
                        if (_v1[0] == 'error') {
                            return _this.onSuccess(cmdstr, arg2);
                        }
                    },
                    onShow: function() {
                        if (_v1[0] == 'error') {
                            return;
                        }
                        if (!$chk(_v2[1])) {
                            return _this.onSuccess(cmdstr, arg2);
                        }


                        var options = {},
                        _m;

                        if (_v1[1] == 'forward') {

                            $extend(options, {
                                method: _this.options.method,
                                data: _this.options.data
                            });

                        }
                        _this.onSuccess(cmdstr, arg2);


                        if (_m = _v2[1].match(/javascript:([\s\S]+)/)) {

                            $globalEval(_m[1]);
                            return;

                        }

                        // 弹窗内 isCloseDialog 表单 + multipart：splash 仍会走 doCommand，若再 page(redirect) 会把列表嵌进当前弹窗。
                        // 由表单 onRequest 置位 window.__skipDesktopSplashInnerRedirect，关闭弹窗与刷新 finder 交给表单 onComplete。
                        if (window.__skipDesktopSplashInnerRedirect) {
                            window.__skipDesktopSplashInnerRedirect = false;
                            return;
                        }

                        _this.page(_v2[1], options);

                    },
                    autohide: (cmd.autohide || 1500)
                }));

            } else {

                new MessageBox(cmdstr, {
                    type: 'notice',
                    onHide: function() {

                        _this.onSuccess(cmdstr, arg2);

                    },
                    autohide: 4000
                });

            }

        },
        eventClear: function() {

            for (e in this.$events) {
                var fns = this.$events[e].clean();
                for (var i = fns.length; i--;) {
                    this.removeEvent(e, fns[i]);
                }
            }

            return this;

        },
        page: function() {

            var params = Array.flatten(arguments).link({
                'url': String.type,
                /*请求地址*/
                'options': Object.type,
                /*请求配置*/
                'sponsor': Element.type
                /*发起请求事件的元素*/
            });

            this.eventClear();

            var update = (params.options && params.options.update) ? $(params.options.update) : '';

            if ( !! update) {
                for (e in update.retrieve('events', {})) {
                    update.removeEvents(e);
                }
            }

            delete(this.options.updateMap);


            params.options = params.options || {};


            this.setOptions(this.exoptions);

            if (params.options) {
                this.setOptions(params.options);
            }
            if (params.sponsor) {
                this.options.sponsor = params.sponsor;
            }
            if (params.url) {
                this.options.url = params.url;
            }

            if (!this.options.update && this.options.sponsor && $type(this.options.sponsor) == 'element') {
                this.options.update = this.options.sponsor.getContainer();
            }

            var update = this.options.update = $(this.options.update || LAYOUT.content_main);


            var exUpdateMap = {
                '.side-r-content': LAYOUT.side_r_content,
                '.mainHead': update.getPrevious(),
                '.mainFoot': update.getNext(),
                '.side-content': LAYOUT.side.getElement('.side-content')
            };


            this.options.updateMap = $merge(exUpdateMap, this.options.updateMap);


            this.setHeader('WORKGROUND', top['currentWorkground'] || 'NULL');

            this.send(this.options);
            this.msgBox = new MessageBox(this.options.message || LANG_Wpage['loading'], {
                type: 'notice'
            });
        },
        render: function(scope) {
            
            if (!this.options.render) return this;


            scope = (scope || this.options.update);

            var _this = this;

            $(scope).getElements('form').each(function(f) {

                f.addEvent('submit',
                function(e) {
                    var _form = this;
                    if (_form.retrieve('submiting')) {
                        return e.stop();
                    }

                    if (!validate(_form)) {
                        e.stop();
                        return (new MessageBox(LANG_Wpage['form']['error'], {
                            type: 'error',
                            autohide: true
                        }));
                    }

                    $ES('textarea[ishtml=true]', _form).getValue();
                    /* html */


                    var _formtarget = _form.get('target');
                    if (_formtarget == '_blank') {
                        return true;
                    }

                    if (_form.get('enctype') == 'multipart/form-data' || _form.get('encoding') == 'multipart/form-data') {
                        _form.target = 'upload';
                        var targetobj = $(_form).retrieve('target', {});
                        if (('onRequest' in targetobj) && 'function' == $type(targetobj.onRequest)) {
                            try {
                                targetobj.onRequest();
                            } catch(e) {}
                        }
                        $('uploadframe').addEvent('load',
                        function() {
                            if (_form && _form.uid) _form.eliminate('submiting');
                            var doc = this.contentWindow.document;
                            var response = doc.body[(doc.body.innerText ? 'innerText': 'textContent')];
                            var cmd = null;
                            try {
                                if ((cmd = JSON.decode(response)) && cmd.splash) {
                                    _this.eventClear().doCommand.call(_this, cmd);
                                }
                            } catch(e) {}
                            if (('onComplete' in targetobj) && 'function' == $type(targetobj.onComplete)) {
                                try {
                                    targetobj.onComplete(response);
                                } catch(e) {}
                            }
                            if (!cmd) {
                                new MessageBox(LANG_Wpage['form']['complete'], {
                                    autohide: 2000
                                });
                            }
                            $('uploadframe').removeEvent('load', arguments.callee).set('src', $('uploadframe').retrieve('default:src'));

                        }).store('default:src', $('uploadframe').src);

                        _form.store('submiting', true).submit();
                        new MessageBox(LANG_Wpage['form']['loading'], {
                            type: 'notice'
                        });
                        return true;
                    }

                    e.stop();
                    var _options = $merge(_form.retrieve('target', {}), JSON.decode(_formtarget)),
                    _onComplete = _options.onComplete || $empty;
                    _options.onComplete = function() {
                        _onComplete.apply(_this, $splat(arguments));
                        if (_form && _form.uid) _form.eliminate('submiting');
                    };
                    _this.page(_form.store('submiting', true).action, $merge({
                        method: _form.method,
                        data: _form,
                        message: LANG_Wpage['form']['loading']
                    },
                    _options), _form);


                });

            });

            // console.log('W.render');

        },
        onComplete: function(re) {

            new MessageBox(LANG_Wpage['complete'], {
                autohide: 1000
            });

            var scope = this.options.update;

            /*DatePicker*/
            var dpInputs = $(scope).getElements('input[date]');
            if (dpInputs&&dpInputs.length)
            Ex_Loader("picker",
            function() {
                dpInputs.each(function(dpi) {
                    dpi.makeCalable();
                });
            });


            /*BREADCRUMBS*/
       
                // BREADCRUMBS =window.BREADCRUMBS;
                if (BREADCRUMBS){
                        var _BREADCRUMBS = BREADCRUMBS.split(':');
                        [LAYOUT.head, LAYOUT.side].clean().each(function(layout) {
                            layout.getElements('.current').removeClass('current');
                            _BREADCRUMBS.each(function(item) {
                                var i = layout.getElement('a[mid=' + item + ']');
                                if ( !! i) {
                                    i.addClass('current');
                                }
                            });

                        });
    
                    }
   

            /*autocompleter*/

                var autocpt = $(scope).getElements('input[autocompleter], textarea[autocompleter]');
                // var autocpt = $$(autocpt, $(scope).getElements('textarea[autocompleter]'));
                if (autocpt&&autocpt.length)
                Ex_Loader('autocompleter',
                function() {
                    autocpt.each(function(item) {
                        //item.setAttribute('autocomplete','off');

                        var params = item.get('autocompleter');
                        var callUrl = '?app=desktop&ctl=autocomplete&params=' + params;

                        var _getVar = params.match(/:([^,]*)/)[1];


                        item.addEvent('keydown',
                        function(e) {
                            if (e.code == 13) e.stop();
                        });


                        var _base_options = {
                            getVar: _getVar,
                            fxOptions: false,
                            delay: 300,
                            callJSON: function() {
                                return window.autocompleter_json;
                            },
                            injectChoice: function(json) {

                                var token = json[this.options.getVar];
                                var choice = new Element('li', {
                                    'html': this.markQueryValue(token)
                                });
                                choice.inputValue = token;
                                this.addChoiceEvents(choice).inject(this.choices);
                            }

                        };
                        var _options = $merge(_base_options, JSON.decode(item.get('ac_options')));
                        new Autocompleter.script(item, callUrl, _options);


                    });
                });
        


                            // console.log('W.complete');


        }


    });



})();
