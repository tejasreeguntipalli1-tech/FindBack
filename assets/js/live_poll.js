/**
 * Live Alert Polling & Notification Manager
 * Polls backend every 12 seconds for real database notifications.
 */

(function () {
    if (!window.IS_LOGGED_IN) {
        return;
    }

    let lastKnownMaxId = 0;
    let isFirstPoll = true;
    const seenNotificationIds = new Set();
    const pollInterval = 12000; // 12 seconds

    function fetchNotifications() {
        fetch(`${window.APP_BASE_URL}/api/notifications_poll.php?last_id=${lastKnownMaxId}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                const badge = document.getElementById('notif-badge');
                if (badge) {
                    if (data.unread_count > 0) {
                        badge.innerText = data.unread_count;
                        badge.classList.remove('d-none');
                    } else {
                        badge.classList.add('d-none');
                    }
                }

                // If first poll, sync baseline max_id without firing toast storm
                if (isFirstPoll) {
                    isFirstPoll = false;
                    if (data.max_id) {
                        lastKnownMaxId = data.max_id;
                    }
                    return;
                }

                // If genuinely new notifications arrived:
                if (data.new_notifications && data.new_notifications.length > 0) {
                    data.new_notifications.forEach(notif => {
                        if (seenNotificationIds.has(notif.id)) {
                            return; // Avoid duplicate toast for same event
                        }
                        seenNotificationIds.add(notif.id);

                        // Show toast
                        showToast(notif.title, notif.message, notif.type, notif.link);

                        // If lastKnownMaxId is less than notif.id, update
                        if (notif.id > lastKnownMaxId) {
                            lastKnownMaxId = notif.id;
                        }

                        // Prepend into dropdown list
                        const list = document.getElementById('notif-items-list');
                        if (list) {
                            const emptyState = list.querySelector('.fa-bell-slash');
                            if (emptyState) {
                                list.innerHTML = '';
                            }
                            const itemHtml = `
                                <li class="p-3 border-bottom bg-light notif-item" data-id="${notif.id}">
                                    <div class="d-flex align-items-start gap-2">
                                        <div class="mt-1">
                                            <i class="fa-solid fa-wand-magic-sparkles text-warning"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong class="small text-dark">${notif.title}</strong>
                                                <span class="text-muted" style="font-size: 0.7rem;">Just now</span>
                                            </div>
                                            <p class="small text-muted mb-1">${notif.message}</p>
                                            ${notif.link ? `<a href="${notif.link}" class="btn btn-outline-primary btn-sm py-0 px-2 text-decoration-none" style="font-size: 0.75rem;">View Details</a>` : ''}
                                        </div>
                                    </div>
                                </li>
                            `;
                            list.insertAdjacentHTML('afterbegin', itemHtml);
                        }
                    });
                } else if (data.max_id) {
                    lastKnownMaxId = Math.max(lastKnownMaxId, data.max_id);
                }
            })
            .catch(err => {
                console.error("Notification polling error:", err);
            });
    }

    // Attach handler for mark-all-read
    document.addEventListener('DOMContentLoaded', () => {
        const markAllBtn = document.getElementById('mark-all-read-btn');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', (e) => {
                e.preventDefault();
                fetch(`${window.APP_BASE_URL}/api/mark_notification.php?all=1`, { method: 'POST' })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const badge = document.getElementById('notif-badge');
                            if (badge) badge.classList.add('d-none');
                            
                            const items = document.querySelectorAll('.notif-item');
                            items.forEach(el => el.classList.remove('bg-light'));
                        }
                    });
            });
        }

        // Initial check immediately, then setInterval
        fetchNotifications();
        setInterval(fetchNotifications, pollInterval);
    });
})();
