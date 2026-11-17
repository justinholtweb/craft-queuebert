/**
 * Queuebert — a live control-panel bar that surfaces running and queued jobs
 * across Craft's tracked queues and any other Laravel queue.
 *
 * Craft 6's control panel is an Inertia app, so this script:
 * - has no dependencies (no jQuery, no legacy `Craft.*` helpers) and reads
 *   only `window.Craft.baseCpUrl`, which the CP layout always sets;
 * - mounts the bar as the first child of `.page-screen` (a flex column above
 *   the header bar) and re-mounts it after client-side navigations;
 * - takes its labels and poll interval from the endpoint's JSON.
 *
 * The bar stays hidden until a poll finds activity, and the poller backs off
 * while idle or while the tab is hidden.
 */
(function () {
    'use strict';

    if (window.Queuebert && window.Queuebert.booted) {
        return;
    }

    var IDLE_INTERVAL = 15000;
    var HIDDEN_INTERVAL = 30000;

    var state = {
        booted: true,
        bar: null,
        timer: null,
        active: false,
        inFlight: false,
        stopped: false,
        pollInterval: 3000,
        lastData: null,
    };
    window.Queuebert = state;

    function cpUrl(path) {
        var base = (window.Craft && window.Craft.baseCpUrl) || '/admin';
        return base.replace(/\/+$/, '') + '/' + path;
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text) node.textContent = text;
        return node;
    }

    function buildBar() {
        var bar = el('div', 'queuebert-bar');
        bar.id = 'queuebert-bar';
        bar.setAttribute('role', 'status');
        bar.setAttribute('aria-live', 'polite');
        bar.hidden = true;

        var inner = el('div', 'queuebert-bar__inner');
        inner.appendChild(el('span', 'queuebert-bar__spinner'));
        inner.appendChild(el('span', 'queuebert-bar__summary'));

        var progress = el('span', 'queuebert-bar__progress');
        var track = el('span', 'queuebert-bar__progress-bar');
        track.appendChild(el('span', 'queuebert-bar__progress-fill'));
        progress.appendChild(track);
        progress.appendChild(el('span', 'queuebert-bar__progress-label'));
        inner.appendChild(progress);

        var link = el('a', 'queuebert-bar__link', 'Manage');
        link.href = cpUrl('utilities/queue-manager');

        bar.appendChild(inner);
        bar.appendChild(link);

        return bar;
    }

    /** Places the bar above the CP header; returns false when there's no CP chrome (e.g. the login screen). */
    function mount() {
        var screen = document.querySelector('.page-screen');
        if (!screen) {
            return false;
        }
        if (!state.bar) {
            state.bar = buildBar();
        }
        if (state.bar.parentNode !== screen || screen.firstElementChild !== state.bar) {
            screen.insertBefore(state.bar, screen.firstChild);
        }
        return true;
    }

    /**
     * Craft sizes the docked sidebar to the viewport minus its own header
     * (`--cp-top-bar-visible-height`, set inline by the CP). Publish how much of
     * our bar is on screen so the stylesheet can subtract it too; otherwise the
     * bar would add its height as a page scroll.
     */
    function syncVisibleHeight() {
        var screen = state.bar && state.bar.parentNode;
        if (!screen || !screen.style) {
            return;
        }
        var visible = 0;
        if (state.active) {
            var rect = state.bar.getBoundingClientRect();
            visible = Math.max(0, Math.min(rect.height, rect.bottom));
        }
        screen.style.setProperty('--queuebert-bar-visible-height', visible + 'px');
    }

    var syncQueued = false;
    function queueSync() {
        if (syncQueued) return;
        syncQueued = true;
        requestAnimationFrame(function () {
            syncQueued = false;
            syncVisibleHeight();
        });
    }

    function summarize(data) {
        var labels = data.labels || {};
        var parts = [];
        [['totalRunning', 'running'], ['totalWaiting', 'waiting'], ['totalScheduled', 'scheduled'], ['totalFailed', 'failed']]
            .forEach(function (pair) {
                if (data[pair[0]] > 0) {
                    parts.push(data[pair[0]] + ' ' + (labels[pair[1]] || pair[1]));
                }
            });

        var label = parts.join(' · ');

        if (data.current && data.current.description) {
            return data.current.description + (label ? '  —  ' + label : '');
        }

        return (labels.title || 'Queue activity') + (label ? ': ' + label : '');
    }

    function render(data) {
        state.lastData = data;

        if (!mount()) {
            return;
        }

        var bar = state.bar;

        if (!data || !data.active) {
            if (state.active) {
                bar.classList.remove('is-visible');
                bar.hidden = true;
                state.active = false;
                syncVisibleHeight();
            }
            return;
        }

        if (data.labels && data.labels.manage) {
            bar.querySelector('.queuebert-bar__link').textContent = data.labels.manage;
        }
        if (data.manageUrl) {
            bar.querySelector('.queuebert-bar__link').href = data.manageUrl;
        }

        bar.querySelector('.queuebert-bar__summary').textContent = summarize(data);

        var progress = bar.querySelector('.queuebert-bar__progress');
        if (data.current && data.totalRunning > 0) {
            var pct = Math.max(0, Math.min(100, data.current.progress || 0));
            bar.querySelector('.queuebert-bar__progress-fill').style.width = pct + '%';
            bar.querySelector('.queuebert-bar__progress-label').textContent = data.current.progressLabel || pct + '%';
            progress.style.display = '';
        } else {
            progress.style.display = 'none';
        }

        bar.classList.toggle(
            'queuebert-bar--failed',
            data.totalFailed > 0 && data.totalRunning === 0 && data.totalWaiting === 0
        );

        if (!state.active) {
            bar.hidden = false;
            void bar.offsetHeight; // reflow so the reveal transition runs
            bar.classList.add('is-visible');
            state.active = true;
            // Track the reveal transition, then settle on the final height.
            bar.addEventListener('transitionend', queueSync, { once: true });
        }
        queueSync();
    }

    function schedule() {
        if (state.stopped) {
            return;
        }
        clearTimeout(state.timer);
        var delay = document.hidden ? HIDDEN_INTERVAL : state.active ? state.pollInterval : IDLE_INTERVAL;
        state.timer = setTimeout(poll, delay);
    }

    function poll() {
        if (state.stopped) {
            return;
        }
        if (state.inFlight) {
            schedule();
            return;
        }
        if (!document.querySelector('.page-screen')) {
            // Plugin scripts load in <head>, before the Inertia app renders the
            // CP chrome; check again shortly rather than waiting a full idle cycle.
            // (On the login screen this keeps ticking cheaply without requests.)
            clearTimeout(state.timer);
            state.timer = setTimeout(poll, 500);
            return;
        }

        state.inFlight = true;

        fetch(cpUrl('queuebert/activity'), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (response.status === 401 || response.status === 403) {
                    // Signed out, or no CP access: stop until the next full page load.
                    state.stopped = true;
                    render(null);
                    return null;
                }
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (!data) return;
                if (data.pollInterval) {
                    state.pollInterval = Math.max(1, Math.min(60, data.pollInterval)) * 1000;
                }
                render(data);
            })
            .catch(function () {
                // Network hiccup: keep the bar as it is and back off.
            })
            .then(function () {
                state.inFlight = false;
                schedule();
            });
    }

    function boot() {
        window.addEventListener('scroll', queueSync, { passive: true });
        window.addEventListener('resize', queueSync, { passive: true });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                poll();
            }
        });

        // Inertia re-renders the page on client-side visits; put the bar back.
        document.addEventListener('inertia:navigate', function () {
            if (!mount()) {
                return;
            }
            if (state.lastData) {
                render(state.lastData);
            } else {
                poll();
            }
        });

        poll();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
