(function () {
    'use strict';

    function setButtonLoading(button, label) {
        button.disabled = true;
        button.dataset.originalLabel = button.textContent;
        button.textContent = label;
        button.classList.add('is-loading');
        button.setAttribute('aria-busy', 'true');
    }

    function disableFormButtons(form) {
        form.querySelectorAll('button').forEach(function (button) {
            button.disabled = true;
        });
    }

    function restoreForm(form) {
        form.dataset.submitting = 'false';
        form.querySelectorAll('button').forEach(function (button) {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.classList.remove('is-loading');
            if (button.dataset.originalLabel) {
                button.textContent = button.dataset.originalLabel;
            }
        });
    }

    function setLinkLoading(link, label) {
        link.dataset.navigating = 'true';
        link.dataset.originalLabel = link.textContent;
        link.textContent = label;
        link.classList.add('is-loading');
        link.setAttribute('aria-busy', 'true');
        link.setAttribute('aria-disabled', 'true');
        window.setTimeout(function () {
            link.dataset.navigating = 'false';
            link.textContent = link.dataset.originalLabel;
            link.classList.remove('is-loading');
            link.removeAttribute('aria-busy');
            link.removeAttribute('aria-disabled');
        }, 10000);
    }

    function showPageLoading() {
        var overlay = document.createElement('div');
        overlay.className = 'page-loading-overlay';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        overlay.innerHTML = '<span class="page-loading-spinner" aria-hidden="true"></span><span>Loading page...</span>';
        document.body.appendChild(overlay);
        document.body.setAttribute('aria-busy', 'true');
        return overlay;
    }

    function getQueueUrl() {
        var queueUrl = document.body.dataset.queueUrl;
        return queueUrl || 'index.php?tab=pending&pending_page=1';
    }

    function getNotificationActionText() {
        return document.body.dataset.page === 'queue' ? 'Refresh Queue' : 'View Queue';
    }

    function updateNotificationCount(count) {
        var notification = document.querySelector('.global-review-notification');
        if (!notification) {
            return;
        }

        var countElement = notification.querySelector('.global-review-notification__count');
        if (!countElement) {
            return;
        }

        countElement.textContent = count === 1 ? '1 review needs attention' : count + ' reviews need attention';
    }

    function showNewReviewNotification(count) {
        var existing = document.querySelector('.global-review-notification');
        var notification;

        if (!existing) {
            notification = document.createElement('div');
            notification.className = 'global-review-notification';
            notification.setAttribute('role', 'status');
            notification.setAttribute('aria-live', 'polite');
            notification.innerHTML = '<div class="global-review-notification__body"><strong class="global-review-notification__count">' + (count === 1 ? '1 review needs attention' : count + ' reviews need attention') + '</strong><span>There are pending reviews in the queue.</span></div><a class="button button-primary" href="' + getQueueUrl() + '">' + getNotificationActionText() + '</a>';

            var content = document.querySelector('main.content');
            if (content && content.parentNode) {
                content.parentNode.insertBefore(notification, content);
            } else {
                document.body.insertBefore(notification, document.body.firstChild);
            }
            return;
        }

        notification = existing;
        updateNotificationCount(count);
    }

    function pollReviewQueue() {
        var pollUrl = document.body.dataset.pollUrl;
        if (!pollUrl) {
            return;
        }

        fetch(pollUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Unable to fetch pending review count.');
                }

                return response.json();
            })
            .then(function (payload) {
                if (!payload || typeof payload.pending_count !== 'number') {
                    return;
                }

                var previousCount = Number(document.body.dataset.pendingCount || 0);
                var nextCount = payload.pending_count;

                if (nextCount > previousCount) {
                    showNewReviewNotification(nextCount);
                } else if (document.querySelector('.global-review-notification')) {
                    updateNotificationCount(nextCount);
                }

                document.body.dataset.pendingCount = String(nextCount);
            })
            .catch(function () {
                // Intentionally silent: background polling should not interrupt the review flow.
            });
    }

    var initialCount = Number(document.body.dataset.pendingCount || 0);
    if (Number.isFinite(initialCount)) {
        document.body.dataset.pendingCount = String(initialCount);
    }

    if (document.body.dataset.pollUrl) {
        window.setInterval(pollReviewQueue, 10000);
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement) || form.dataset.submitting === 'true') {
            return;
        }

        if (form.classList.contains('loading-form')) {
            form.dataset.submitting = 'true';
            var button = form.querySelector('button[type="submit"]');
            if (button) {
                setButtonLoading(button, form.dataset.loadingLabel || 'Submitting...');
            }
            showPageLoading();
            window.setTimeout(function () { restoreForm(form); }, 10000);
            return;
        }

        if (!form.classList.contains('decision-form')) {
            return;
        }

        var submitter = event.submitter;
        var decision = submitter ? submitter.value : '';
        var reason = form.querySelector('[name="review_reason"]');

        if (decision === 'UNMATCH' && reason && reason.value.trim() === '') {
            event.preventDefault();
            reason.focus();
            reason.setCustomValidity('A review reason is required for UNMATCH.');
            reason.reportValidity();
            window.setTimeout(function () { reason.setCustomValidity(''); }, 100);
            return;
        }

        form.dataset.submitting = 'true';
        var decisionInput = document.createElement('input');
        decisionInput.type = 'hidden';
        decisionInput.name = 'decision';
        decisionInput.value = decision;
        form.appendChild(decisionInput);
        disableFormButtons(form);
        if (submitter) {
            setButtonLoading(submitter, decision === 'MATCH' ? 'Matching...' : 'Unmatching...');
        }
        showPageLoading();
        window.setTimeout(function () { restoreForm(form); }, 10000);
    });

    document.addEventListener('click', function (event) {
        var tab = event.target.closest('.review-tab');

        if (!tab || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        document.body.classList.add('is-leaving');
        showPageLoading();
        window.setTimeout(function () {
            window.location.href = tab.href;
        }, 120);
    });

    document.addEventListener('click', function (event) {
        var link = event.target.closest('.loading-link');

        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        if (link.dataset.navigating === 'true') {
            event.preventDefault();
            return;
        }

        setLinkLoading(link, link.dataset.loadingLabel || 'Opening...');

        showPageLoading();
    });
}());