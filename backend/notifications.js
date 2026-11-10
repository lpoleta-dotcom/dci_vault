document.addEventListener('DOMContentLoaded', () => {
    const highlightParams = new URLSearchParams(window.location.search);
    const highlightOrderId = highlightParams.get('highlight-order');
    const highlightUserId = highlightParams.get('highlight-user');
    const highlightProductId = highlightParams.get('highlight-product');
    const highlightSize = highlightParams.get('highlight-size');
    const highlightTarget = () => {
        let target = null;
        if (highlightOrderId) {
            target = Array.from(document.querySelectorAll('[data-order-id]'))
                .find(element => element.dataset.orderId === highlightOrderId) || null;
            if (target) {
                document.querySelectorAll('#adminOrderFilterTab button').forEach(button => {
                    button.classList.toggle('active', button.id === 'admin-all-tab');
                });
                const search = document.getElementById('adminOrderSearch');
                if (search) {
                    search.value = '';
                    search.dispatchEvent(new Event('input', { bubbles: true }));
                }
                document.querySelectorAll('#orderFilterTab button').forEach(button => {
                    button.classList.toggle('active', button.id === 'all-tab');
                });
                document.querySelectorAll('#orderListContainer > [data-status]').forEach(card => {
                    card.classList.remove('d-none');
                });
            }
        } else if (highlightUserId) {
            target = Array.from(document.querySelectorAll('[data-user-id]'))
                .find(element => element.dataset.userId === highlightUserId) || null;
        } else if (highlightProductId) {
            target = Array.from(document.querySelectorAll('[data-product-id]'))
                .find(element => element.dataset.productId === highlightProductId) || null;
            if (target && highlightSize) {
                const sizeTarget = Array.from(target.querySelectorAll('[data-size-stock]'))
                    .find(element => element.dataset.sizeStock === highlightSize);
                if (sizeTarget) target = sizeTarget;
            }
        }
        if (!target) return false;
        target.classList.add('notification-highlight');
        target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
        return true;
    };

    if (highlightOrderId || highlightUserId || highlightProductId) {
        const observer = new MutationObserver(() => {
            if (highlightTarget()) {
                observer.disconnect();
                window.clearTimeout(highlightTimeout);
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
        const highlightTimeout = window.setTimeout(() => observer.disconnect(), 10000);
        highlightTarget();
    }

    document.querySelectorAll('.notification-widget').forEach(widget => {
        const role = widget.dataset.notificationRole;
        const endpoint = `notifications-api.php?role=${encodeURIComponent(role)}`;
        const bell = widget.querySelector('.notification-bell');
        const count = widget.querySelector('.notification-count');
        const panel = widget.querySelector('.notification-panel');
        const list = widget.querySelector('.notification-list');
        const markAll = widget.querySelector('.notification-mark-all');
        let csrfToken = '';
        let loading = false;

        const render = result => {
            count.textContent = result.unread_count > 99 ? '99+' : String(result.unread_count);
            count.classList.toggle('d-none', result.unread_count < 1);
            bell.setAttribute(
                'aria-label',
                result.unread_count > 0 ? `Notifications, ${result.unread_count} unread` : 'Notifications'
            );
            markAll.disabled = result.unread_count < 1;
            list.replaceChildren();
            if (!result.notifications.length) {
                const empty = document.createElement('p');
                empty.className = 'notification-empty';
                empty.textContent = 'You’re all caught up.';
                list.append(empty);
                return;
            }
            result.notifications.forEach(notification => {
                const link = document.createElement('a');
                link.className = `notification-item${notification.is_read ? '' : ' is-unread'}`;
                link.href = notification.target_url;
                link.setAttribute('role', 'listitem');
                link.dataset.notificationId = String(notification.notification_id);
                const title = document.createElement('strong');
                title.textContent = notification.title;
                const message = document.createElement('span');
                message.textContent = notification.message;
                const date = document.createElement('small');
                date.textContent = notification.created_at;
                link.append(title, message, date);
                list.append(link);
            });
        };

        const load = async () => {
            if (loading) return;
            loading = true;
            try {
                const response = await fetch(endpoint, { cache: 'no-store' });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load notifications.');
                csrfToken = result.csrf_token;
                render(result);
            } catch (error) {
                console.error('Unable to load notifications.', error);
                if (!list.childElementCount) {
                    const failed = document.createElement('p');
                    failed.className = 'notification-empty';
                    failed.textContent = 'Notifications could not be loaded.';
                    list.replaceChildren(failed);
                }
            } finally {
                loading = false;
            }
        };

        const markRead = async notificationId => {
            if (!csrfToken) return;
            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify(notificationId ? { notification_id: notificationId } : { mark_all: true })
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to update notifications.');
                render(result);
                return true;
            } catch (error) {
                console.error('Unable to mark notifications as read.', error);
                return false;
            }
        };

        bell.addEventListener('click', () => {
            const opening = panel.classList.contains('d-none');
            panel.classList.toggle('d-none', !opening);
            bell.setAttribute('aria-expanded', String(opening));
            if (opening) load();
        });
        markAll.addEventListener('click', () => markRead(null));
        list.addEventListener('click', event => {
            const link = event.target.closest('a[data-notification-id]');
            if (!link) return;
            event.preventDefault();
            const destination = link.href;
            const navigate = () => window.location.assign(destination);
            if (link.classList.contains('is-unread')) markRead(link.dataset.notificationId).then(navigate);
            else navigate();
        });
        document.addEventListener('click', event => {
            if (panel.classList.contains('d-none') || widget.contains(event.target)) return;
            panel.classList.add('d-none');
            bell.setAttribute('aria-expanded', 'false');
        });
        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape') return;
            panel.classList.add('d-none');
            bell.setAttribute('aria-expanded', 'false');
            bell.focus();
        });

        load();
        window.setInterval(load, 20000);
    });
});
