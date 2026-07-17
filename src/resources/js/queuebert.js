/**
 * Queuebert — a live control-panel bar that surfaces running and queued jobs
 * across Craft's default queue and any custom queue runners.
 *
 * The bar is injected once, kept hidden, and only revealed while there is
 * queue activity. It polls a lightweight JSON endpoint and slows its own
 * cadence when the browser tab is hidden.
 */
(function (Craft, $) {
    'use strict';

    if (window.Queuebert && window.Queuebert.booted) {
        return;
    }

    var settings = window.QueuebertSettings || {};
    var ACTIVE_INTERVAL = Math.max(1, settings.pollInterval || 3) * 1000;
    var IDLE_INTERVAL = 15000; // when nothing is happening, back off
    var HIDDEN_INTERVAL = 30000; // when the tab isn't visible

    var Queuebert = {
        booted: true,
        $bar: null,
        timer: null,
        lastActive: false,
        requestInFlight: false,
    };
    window.Queuebert = Queuebert;

    function url() {
        return Craft.getActionUrl('queuebert/queue/info');
    }

    function buildBar() {
        var $bar = $(
            '<div id="queuebert-bar" class="queuebert-bar" role="status" aria-live="polite" hidden>' +
                '<div class="queuebert-bar__inner">' +
                    '<span class="queuebert-bar__spinner spinner"></span>' +
                    '<span class="queuebert-bar__summary"></span>' +
                    '<span class="queuebert-bar__progress">' +
                        '<span class="queuebert-bar__progress-bar"><span class="queuebert-bar__progress-fill"></span></span>' +
                        '<span class="queuebert-bar__progress-label"></span>' +
                    '</span>' +
                '</div>' +
                '<a class="queuebert-bar__link" href="' + Craft.getCpUrl('utilities/queue-manager') + '">' +
                    Craft.t('queuebert', 'Manage') +
                '</a>' +
            '</div>'
        );

        // Insert the bar as the first element inside #page-container, above
        // #global-header. On desktop this keeps it within the content column
        // (clear of the sidebar); on mobile, where #page-container is full
        // width and the sidebar is off-canvas, it becomes the topmost element.
        var $pageContainer = $('#page-container');
        if ($pageContainer.length) {
            $pageContainer.prepend($bar);
        } else {
            // Fallback for any CP layout without #page-container.
            $('body').prepend($bar);
        }

        return $bar;
    }

    function pluralize(count, singular, plural) {
        return count + ' ' + (count === 1 ? singular : plural);
    }

    function summarize(data) {
        var parts = [];
        if (data.totalRunning > 0) {
            parts.push(pluralize(data.totalRunning, Craft.t('queuebert', 'running'), Craft.t('queuebert', 'running')));
        }
        if (data.totalWaiting > 0) {
            parts.push(pluralize(data.totalWaiting, Craft.t('queuebert', 'waiting'), Craft.t('queuebert', 'waiting')));
        }
        if (data.totalFailed > 0) {
            parts.push(pluralize(data.totalFailed, Craft.t('queuebert', 'failed'), Craft.t('queuebert', 'failed')));
        }

        var label = parts.join(' · ');

        // Prefix with the current job description when one is running.
        if (data.current && data.current.description) {
            return data.current.description + (label ? '  —  ' + label : '');
        }

        return Craft.t('queuebert', 'Queue activity') + (label ? ': ' + label : '');
    }

    function render(data) {
        if (!Queuebert.$bar) {
            Queuebert.$bar = buildBar();
        }

        var $bar = Queuebert.$bar;

        if (!data || !data.active) {
            if (Queuebert.lastActive) {
                $bar.attr('hidden', true).removeClass('is-visible');
                $('body').removeClass('has-queuebert-bar');
                Queuebert.lastActive = false;
            }
            return;
        }

        $bar.find('.queuebert-bar__summary').text(summarize(data));

        var $progress = $bar.find('.queuebert-bar__progress');
        if (data.current && data.totalRunning > 0) {
            var pct = Math.max(0, Math.min(100, data.current.progress || 0));
            $bar.find('.queuebert-bar__progress-fill').css('width', pct + '%');
            $bar.find('.queuebert-bar__progress-label').text(data.current.progressLabel || (pct + '%'));
            $progress.css('display', '');
        } else {
            $progress.css('display', 'none');
        }

        $bar.toggleClass('queuebert-bar--failed', data.totalFailed > 0 && data.totalRunning === 0 && data.totalWaiting === 0);

        if (!Queuebert.lastActive) {
            $bar.removeAttr('hidden');
            // Force reflow so the transition runs on reveal.
            $bar[0].offsetHeight; // eslint-disable-line no-unused-expressions
            $bar.addClass('is-visible');
            $('body').addClass('has-queuebert-bar');
            Queuebert.lastActive = true;
        }
    }

    function nextInterval(active) {
        if (document.hidden) {
            return HIDDEN_INTERVAL;
        }
        return active ? ACTIVE_INTERVAL : IDLE_INTERVAL;
    }

    function schedule(active) {
        if (Queuebert.timer) {
            clearTimeout(Queuebert.timer);
        }
        Queuebert.timer = setTimeout(poll, nextInterval(active));
    }

    function poll() {
        if (Queuebert.requestInFlight) {
            schedule(Queuebert.lastActive);
            return;
        }

        Queuebert.requestInFlight = true;

        $.ajax({
            url: url(),
            method: 'GET',
            dataType: 'json',
            headers: { Accept: 'application/json' },
        })
            .done(function (data) {
                render(data);
                schedule(data && data.active);
            })
            .fail(function () {
                // Network hiccup or logged out — back off and keep the bar as-is.
                schedule(false);
            })
            .always(function () {
                Queuebert.requestInFlight = false;
            });
    }

    function boot() {
        // Re-poll promptly when the tab becomes visible again.
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                schedule(Queuebert.lastActive);
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
})(window.Craft, window.jQuery);
