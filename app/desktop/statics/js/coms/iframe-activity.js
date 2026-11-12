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
 * Legacy iframe ↔ Vue shell session bridge:
 *   1. IFRAME_ACTIVITY — user activity for idle timeout
 *   2. session_expired postMessage — access JWT expired on XHR (same contract as full-page auth redirect)
 */
(function () {
  if (window.parent === window) {
    return
  }

  var lastNotifyAt = 0
  var lastSessionExpiredAt = 0
  var activityThrottleMs = 30 * 1000
  var sessionExpiredThrottleMs = 3 * 1000
  var targetOrigin = window.location.origin

  function notifyActivity() {
    var now = Date.now()
    if (now - lastNotifyAt < activityThrottleMs) {
      return
    }
    lastNotifyAt = now
    try {
      window.parent.postMessage({ type: "IFRAME_ACTIVITY" }, targetOrigin)
    } catch (error) {
      // Ignore postMessage failures in restricted embed contexts.
    }
  }

  function notifySessionExpired() {
    var now = Date.now()
    if (now - lastSessionExpiredAt < sessionExpiredThrottleMs) {
      return
    }
    lastSessionExpiredAt = now
    try {
      window.parent.postMessage({ reason: "session_expired" }, targetOrigin)
    } catch (error) {
      // Ignore postMessage failures in restricted embed contexts.
    }
  }

  ;["click", "mousemove", "keydown", "touchstart", "wheel"].forEach(function (eventName) {
    document.addEventListener(eventName, notifyActivity, { capture: true, passive: true })
  })

  // Legacy MooTools AJAX 401 不会注入 postMessage script，与 Vue service.ts 401→refresh 对齐
  if (typeof XMLHttpRequest !== "undefined") {
    var originalOpen = XMLHttpRequest.prototype.open
    XMLHttpRequest.prototype.open = function () {
      this.addEventListener("load", function onLoad() {
        if (this.status === 401) {
          notifySessionExpired()
        }
        this.removeEventListener("load", onLoad)
      })
      return originalOpen.apply(this, arguments)
    }
  }
})()
