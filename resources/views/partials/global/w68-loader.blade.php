{{--
    Global W68 page loader.
    UI-only: no business data, requests, routes, or calculations are changed.
    The overlay is hidden from print output and exposes window.W68Loader.show()/hide().
--}}
<div id="w68-global-loader" class="w68-global-loader" aria-hidden="true">
    <div class="w68-loader-stage" role="status" aria-label="Loading">
        <svg id="w68-loader-svg" class="w68-loader-svg" viewBox="0 0 750 260" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <defs>
                <linearGradient id="w68-maroon-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#a31c3f" />
                    <stop offset="40%" stop-color="#800020" />
                    <stop offset="100%" stop-color="#4a0011" />
                </linearGradient>
                <linearGradient id="w68-gold-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#FFF5C0" />
                    <stop offset="30%" stop-color="#FFD700" />
                    <stop offset="70%" stop-color="#D4AF37" />
                    <stop offset="100%" stop-color="#996515" />
                </linearGradient>
            </defs>

            <g opacity="0.10" stroke-linecap="round" stroke-linejoin="round">
                <path d="M 45 95 C 45 60, 65 55, 75 80 L 105 175 C 115 200, 130 200, 140 170 L 165 105 C 175 80, 185 80, 195 105 L 215 170 C 225 200, 240 200, 250 175 L 275 90 C 282 68, 298 75, 285 100 C 275 120, 290 120, 305 100" stroke="#800020" stroke-width="16" fill="none" />
                <path d="M 430 70 C 390 70, 355 105, 345 150 C 338 185, 350 215, 385 218 C 420 220, 445 195, 445 165 C 445 135, 415 125, 380 132 C 352 138, 345 158, 345 165" stroke="#d4af37" stroke-width="16" fill="none" />
                <path d="M 585 135 C 550 120, 530 98, 530 78 C 530 52, 555 42, 580 42 C 610 42, 630 58, 630 80 C 630 102, 605 122, 570 135 C 525 150, 505 172, 505 198 C 505 225, 535 238, 575 238 C 620 238, 645 220, 645 192 C 645 160, 610 142, 570 135" stroke="#d4af37" stroke-width="16" fill="none" />
            </g>

            <g stroke-linecap="round" stroke-linejoin="round">
                <path id="w68-path-w" class="w68-maroon-glow" d="M 45 95 C 45 60, 65 55, 75 80 L 105 175 C 115 200, 130 200, 140 170 L 165 105 C 175 80, 185 80, 195 105 L 215 170 C 225 200, 240 200, 250 175 L 275 90 C 282 68, 298 75, 285 100 C 275 120, 290 120, 305 100" stroke="url(#w68-maroon-grad)" stroke-width="16" fill="none" />
                <path id="w68-path-6" class="w68-gold-glow" d="M 430 70 C 390 70, 355 105, 345 150 C 338 185, 350 215, 385 218 C 420 220, 445 195, 445 165 C 445 135, 415 125, 380 132 C 352 138, 345 158, 345 165" stroke="url(#w68-gold-grad)" stroke-width="16" fill="none" />
                <path id="w68-path-8" class="w68-gold-glow" d="M 585 135 C 550 120, 530 98, 530 78 C 530 52, 555 42, 580 42 C 610 42, 630 58, 630 80 C 630 102, 605 122, 570 135 C 525 150, 505 172, 505 198 C 505 225, 535 238, 575 238 C 620 238, 645 220, 645 192 C 645 160, 610 142, 570 135" stroke="url(#w68-gold-grad)" stroke-width="16" fill="none" />
            </g>

            <g id="w68-pen-tip" opacity="0">
                <circle id="w68-pen-aura" cx="0" cy="0" r="14" fill="#FFD700" opacity="0.35" />
                <circle id="w68-pen-dot" cx="0" cy="0" r="6" fill="#FFFFFF" stroke="#FFD700" stroke-width="2.5" />
                <circle id="w68-sparkle-1" cx="0" cy="0" r="2.5" fill="#FFF2B2" />
                <circle id="w68-sparkle-2" cx="0" cy="0" r="2" fill="#D4AF37" />
            </g>
        </svg>
    </div>
</div>

<style>
    .w68-global-loader {
        position: fixed;
        inset: 0;
        z-index: 2147483647;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        /* Keep the real page visible while W68 animates. */
        background: transparent;
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        opacity: 1;
        visibility: visible;
        transition: opacity 180ms ease, visibility 180ms ease;
        pointer-events: all;
        user-select: none;
    }

    .w68-global-loader.w68-loader-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .w68-loader-stage {
        width: min(680px, 88vw);
        aspect-ratio: 21 / 9;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .w68-loader-svg {
        width: 100%;
        height: 100%;
        overflow: visible;
    }

    .w68-maroon-glow {
        filter: drop-shadow(0 0 16px rgba(128, 0, 32, 0.8));
    }

    .w68-gold-glow {
        filter: drop-shadow(0 0 18px rgba(212, 175, 55, 0.85));
    }

    #w68-pen-aura {
        transform-box: fill-box;
        transform-origin: center;
        animation: w68PenAuraPulse 850ms ease-out infinite;
    }

    @keyframes w68PenAuraPulse {
        0% { transform: scale(0.65); opacity: 0.48; }
        70%, 100% { transform: scale(1.7); opacity: 0; }
    }

    @media (prefers-reduced-motion: reduce) {
        .w68-global-loader,
        #w68-pen-aura {
            transition: none !important;
            animation: none !important;
        }
    }

    @media print {
        #w68-global-loader {
            display: none !important;
        }
    }
</style>

<script>
(function () {
    'use strict';

    if (window.__w68GlobalLoaderInitialized) return;
    window.__w68GlobalLoaderInitialized = true;

    const loader = document.getElementById('w68-global-loader');
    const pathW = document.getElementById('w68-path-w');
    const path6 = document.getElementById('w68-path-6');
    const path8 = document.getElementById('w68-path-8');
    const paths = [pathW, path6, path8].filter(Boolean);
    const penTip = document.getElementById('w68-pen-tip');
    const penDot = document.getElementById('w68-pen-dot');
    const penAura = document.getElementById('w68-pen-aura');
    const sparkle1 = document.getElementById('w68-sparkle-1');
    const sparkle2 = document.getElementById('w68-sparkle-2');

    if (!loader || paths.length !== 3) return;

    const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const totalDuration = 2400;
    const restartDelay = 800;
    const penColors = ['#800020', '#FFD700', '#FFD700'];
    const visibleReasons = new Set();
    let pathLengths = [];
    let animationFrameId = null;
    let restartTimer = null;
    let startTime = null;
    let running = false;

    // Initial-page network tracking. The loader remains visible until the HTML/assets
    // and the first wave of page-data fetch/XHR calls have all settled.
    let bootTracking = true;
    let windowLoaded = document.readyState === 'complete';
    let bootRequests = 0;
    let bootQuietTimer = null;
    let bootSafetyTimer = null;

    function initializePaths() {
        pathLengths = paths.map(function (path) {
            const length = path.getTotalLength();
            path.style.strokeDasharray = length + ' ' + length;
            path.style.strokeDashoffset = reducedMotion ? '0' : String(length);
            return length;
        });
    }

    function stopAnimation() {
        running = false;
        if (animationFrameId) {
            cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
        }
        if (restartTimer) {
            clearTimeout(restartTimer);
            restartTimer = null;
        }
        startTime = null;
        if (penTip) penTip.setAttribute('opacity', '0');
    }

    function animate(timestamp) {
        if (!running) return;
        if (!startTime) startTime = timestamp;

        const elapsed = timestamp - startTime;
        const totalLengthSum = pathLengths.reduce(function (a, b) { return a + b; }, 0);
        const pathRatios = pathLengths.map(function (len) { return len / totalLengthSum; });
        const progress = elapsed / totalDuration;

        if (progress >= 1) {
            paths.forEach(function (path) { path.style.strokeDashoffset = '0'; });
            if (penTip) penTip.setAttribute('opacity', '0');
            restartTimer = setTimeout(function () {
                if (!running) return;
                startTime = null;
                initializePaths();
                animationFrameId = requestAnimationFrame(animate);
            }, restartDelay);
            return;
        }

        let accumulatedRatio = 0;
        for (let i = 0; i < paths.length; i++) {
            const pathStartRatio = accumulatedRatio;
            const pathEndRatio = accumulatedRatio + pathRatios[i];

            if (progress < pathStartRatio) {
                paths[i].style.strokeDashoffset = String(pathLengths[i]);
            } else if (progress >= pathEndRatio) {
                paths[i].style.strokeDashoffset = '0';
            } else {
                const localProgress = (progress - pathStartRatio) / pathRatios[i];
                paths[i].style.strokeDashoffset = String(pathLengths[i] * (1 - localProgress));

                if (penTip) {
                    const point = paths[i].getPointAtLength(pathLengths[i] * localProgress);
                    penTip.setAttribute('opacity', '1');
                    penTip.setAttribute('transform', 'translate(' + point.x + ', ' + point.y + ')');
                    if (penDot) penDot.setAttribute('stroke', penColors[i]);
                    if (penAura) penAura.setAttribute('fill', penColors[i]);
                    if (sparkle1) {
                        sparkle1.setAttribute('cx', String((Math.random() - 0.5) * 10));
                        sparkle1.setAttribute('cy', String((Math.random() - 0.5) * 10));
                    }
                    if (sparkle2) {
                        sparkle2.setAttribute('cx', String((Math.random() - 0.5) * 16));
                        sparkle2.setAttribute('cy', String((Math.random() - 0.5) * 16));
                    }
                }
            }

            accumulatedRatio = pathEndRatio;
        }

        animationFrameId = requestAnimationFrame(animate);
    }

    function startAnimation() {
        if (reducedMotion) {
            initializePaths();
            return;
        }
        stopAnimation();
        running = true;
        initializePaths();
        animationFrameId = requestAnimationFrame(animate);
    }

    function renderVisibility() {
        const shouldShow = visibleReasons.size > 0;
        if (shouldShow) {
            loader.classList.remove('w68-loader-hidden');
            loader.setAttribute('aria-hidden', 'false');
            if (!running) startAnimation();
        } else {
            loader.classList.add('w68-loader-hidden');
            loader.setAttribute('aria-hidden', 'true');
            stopAnimation();
        }
    }

    function show(reason) {
        visibleReasons.add(String(reason || 'manual'));
        renderVisibility();
    }

    function hide(reason) {
        visibleReasons.delete(String(reason || 'manual'));
        renderVisibility();
    }

    function forceHide() {
        visibleReasons.clear();
        renderVisibility();
    }

    function maybeFinishBoot() {
        if (!bootTracking || !windowLoaded || bootRequests > 0) return;
        if (bootQuietTimer) clearTimeout(bootQuietTimer);
        bootQuietTimer = setTimeout(function () {
            if (!bootTracking || !windowLoaded || bootRequests > 0) return;
            bootTracking = false;
            hide('boot');
            if (bootSafetyTimer) {
                clearTimeout(bootSafetyTimer);
                bootSafetyTimer = null;
            }
        }, 180);
    }

    function beginBootRequest() {
        if (!bootTracking) return false;
        bootRequests += 1;
        if (bootQuietTimer) {
            clearTimeout(bootQuietTimer);
            bootQuietTimer = null;
        }
        return true;
    }

    function endBootRequest(wasTracked) {
        if (!wasTracked) return;
        bootRequests = Math.max(0, bootRequests - 1);
        maybeFinishBoot();
    }

    window.W68Loader = {
        show: show,
        hide: hide,
        forceHide: forceHide,
        isVisible: function () {
            return visibleReasons.size > 0;
        },
        reasons: function () {
            return Array.from(visibleReasons);
        }
    };

    // IMPORTANT: install request tracking before the rest of each page's scripts run.
    if (typeof window.fetch === 'function') {
        const nativeFetch = window.fetch.bind(window);
        window.fetch = function () {
            const tracked = beginBootRequest();
            let request;
            try {
                request = nativeFetch.apply(null, arguments);
            } catch (error) {
                endBootRequest(tracked);
                throw error;
            }
            return Promise.resolve(request).finally(function () {
                endBootRequest(tracked);
            });
        };
    }

    if (window.XMLHttpRequest && window.XMLHttpRequest.prototype) {
        const nativeSend = window.XMLHttpRequest.prototype.send;
        window.XMLHttpRequest.prototype.send = function () {
            const xhr = this;
            const tracked = beginBootRequest();
            if (tracked) {
                let completed = false;
                const finish = function () {
                    if (completed) return;
                    completed = true;
                    endBootRequest(true);
                };
                xhr.addEventListener('loadend', finish, { once: true });
                xhr.addEventListener('abort', finish, { once: true });
                xhr.addEventListener('error', finish, { once: true });
                xhr.addEventListener('timeout', finish, { once: true });
            }
            try {
                return nativeSend.apply(xhr, arguments);
            } catch (error) {
                endBootRequest(tracked);
                throw error;
            }
        };
    }

    // Start before the rest of the document and keep the boot reason until page + initial data are ready.
    show('boot');

    if (windowLoaded) {
        maybeFinishBoot();
    } else {
        window.addEventListener('load', function () {
            windowLoaded = true;
            maybeFinishBoot();
        }, { once: true });
    }

    // Safety only: a broken/hanging startup request must never permanently lock the UI.
    bootSafetyTimer = setTimeout(function () {
        if (!bootTracking) return;
        bootTracking = false;
        hide('boot');
    }, 120000);

    // Handle back/forward cache restoration.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            visibleReasons.delete('navigation');
            visibleReasons.delete('boot');
            bootTracking = false;
            renderVisibility();
        }
    });

    // Defer navigation detection until after every click handler had a chance to preventDefault().
    document.addEventListener('click', function (event) {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
        if (!link) return;
        if (link.hasAttribute('download')) return;
        if (link.target && link.target.toLowerCase() === '_blank') return;

        const rawHref = (link.getAttribute('href') || '').trim();
        if (!rawHref || rawHref === '#' || rawHref.startsWith('#') || rawHref.startsWith('javascript:')) return;

        let url;
        try {
            url = new URL(link.href, window.location.href);
        } catch (_) {
            return;
        }

        if (!/^https?:$/.test(url.protocol)) return;
        if (url.origin !== window.location.origin) return;
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;

        setTimeout(function () {
            if (!event.defaultPrevented) show('navigation');
        }, 0);
    });

    document.addEventListener('submit', function (event) {
        setTimeout(function () {
            if (!event.defaultPrevented) show('navigation');
        }, 0);
    });

    // Never allow the overlay into browser print/print-preview.
    window.addEventListener('beforeprint', function () {
        loader.classList.add('w68-loader-hidden');
        loader.setAttribute('aria-hidden', 'true');
        stopAnimation();
    });
    window.addEventListener('afterprint', renderVisibility);

    // Final fallback for direct location assignments/reloads.
    window.addEventListener('beforeunload', function () {
        show('navigation');
    });
})();
</script>

