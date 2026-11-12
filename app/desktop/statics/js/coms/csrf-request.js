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
 * See the License for the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Inject X-CSRF-Token into legacy MooTools Request (shopex-ui AJAX).
 * Reads non-HttpOnly oms-csrf-token cookie set on login/refresh.
 * Only same-origin requests get the header — cross-origin calls (e.g. matrix
 * decrypt at jst-matrix.shopex.cn) must not carry it or CORS preflight fails.
 */
(function () {
  function readCsrfToken() {
    var match = document.cookie.match(/(?:^|;\s*)oms-csrf-token=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : "";
  }

  function resolveRequestUrl(request, options) {
    if (options && typeof options === "object" && options.url) {
      return String(options.url);
    }
    if (request && request.options && request.options.url) {
      return String(request.options.url);
    }
    return "";
  }

  function isSameOriginUrl(url) {
    if (!url) {
      return true;
    }
    // Relative / protocol-relative-ish OMS paths are same-origin.
    if (url.indexOf("://") === -1) {
      return true;
    }
    try {
      var parsed;
      if (typeof URL === "function") {
        parsed = new URL(url, window.location.href);
        return parsed.protocol === window.location.protocol && parsed.host === window.location.host;
      }
      var anchor = document.createElement("a");
      anchor.href = url;
      return anchor.protocol === window.location.protocol && anchor.host === window.location.host;
    } catch (e) {
      return true;
    }
  }

  if (typeof Request === "undefined" || !Request.prototype || !Request.prototype.send) {
    return;
  }

  var originalSend = Request.prototype.send;
  Request.prototype.send = function (options) {
    var csrf = readCsrfToken();
    if (csrf && isSameOriginUrl(resolveRequestUrl(this, options))) {
      this.setHeader("X-CSRF-Token", csrf);
    }
    return originalSend.call(this, options);
  };
})();
