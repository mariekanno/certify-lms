export function initNotificationPopover() {
    const root = document.querySelector('[data-notification-popover-root]');

    if (!root) {
        return;
    }

    const trigger = root.querySelector('[data-notification-popover-trigger]');
    const panel = root.querySelector('[data-notification-popover-panel]');
    const badge = root.querySelector('[data-notification-popover-badge]');
    const unreadCount = root.querySelector('[data-notification-popover-unread-count]');
    const markAllButton = root.querySelector('[data-notification-popover-mark-all]');
    const loading = root.querySelector('[data-notification-popover-loading]');
    const empty = root.querySelector('[data-notification-popover-empty]');
    const items = root.querySelector('[data-notification-popover-items]');
    const rowTemplate = root.querySelector('[data-notification-popover-row-template]');
    const tabs = root.querySelectorAll('[data-notification-popover-tab]');

    if (
        !trigger
        || !panel
        || !badge
        || !unreadCount
        || !markAllButton
        || !loading
        || !empty
        || !items
        || !rowTemplate
    ) {
        return;
    }

    let notifications = [];
    let activeTab = 'all';
    let isOpen = false;

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    function updateUnreadCount(count) {
        unreadCount.textContent = String(count);

        if (count <= 0) {
            badge.classList.add('hidden');
            badge.textContent = '0';
            return;
        }

        badge.classList.remove('hidden');
        badge.textContent = count > 99 ? '99+' : String(count);
    }

    function setLoading(value) {
        loading.classList.toggle('hidden', !value);
    }

    function setEmpty(value) {
        empty.classList.toggle('hidden', !value);
    }

    function getVisibleNotifications() {
        if (activeTab === 'unread') {
            return notifications.filter((notification) => notification.is_unread);
        }

        return notifications;
    }

    function render() {
        items.innerHTML = '';

        const visibleNotifications = getVisibleNotifications();

        setEmpty(visibleNotifications.length === 0);

        visibleNotifications.forEach((notification) => {
            const fragment = rowTemplate.content.cloneNode(true);
            const row = fragment.querySelector('[data-notification-popover-row]');
            const dot = fragment.querySelector('[data-notification-popover-row-dot]');
            const title = fragment.querySelector('[data-notification-popover-row-title]');
            const message = fragment.querySelector('[data-notification-popover-row-message]');
            const time = fragment.querySelector('[data-notification-popover-row-time]');

            row.href = notification.url;
            row.dataset.unread = String(notification.is_unread);

            title.textContent = notification.title;
            message.textContent = notification.message;
            time.textContent = notification.created_relative;

            dot.classList.toggle('invisible', !notification.is_unread);

            row.addEventListener('click', async (event) => {
                event.preventDefault();

                if (notification.is_unread) {
                    await markAsRead(notification);
                }

                window.location.href = notification.url;
            });

            items.appendChild(fragment);
        });
    }

    async function fetchNotifications() {
        setLoading(true);
        setEmpty(false);

        try {
            const response = await fetch('/api/v1/notifications', {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('通知の取得に失敗しました。');
            }

            const json = await response.json();

            notifications = json.data;
            updateUnreadCount(json.unread_count);
            render();
        } finally {
            setLoading(false);
        }
    }

    async function markAsRead(notification) {
        const response = await fetch(
            `/api/v1/notifications/${notification.id}/read`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken ?? '',
                },
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            throw new Error('通知の既読化に失敗しました。');
        }

        const json = await response.json();

        notification.is_unread = false;

        updateUnreadCount(json.unread_count);
        render();
    }

    async function markAllAsRead() {
        const response = await fetch('/api/v1/notifications/read-all', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken ?? '',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('通知の一括既読化に失敗しました。');
        }

        const json = await response.json();

        notifications = notifications.map((notification) => ({
            ...notification,
            is_unread: false,
        }));

        updateUnreadCount(json.unread_count);
        render();
    }

    function open() {
        panel.style.display = 'flex';
        panel.classList.remove('hidden');

        requestAnimationFrame(() => {
            panel.classList.remove('opacity-0', '-translate-y-1');
        });

        trigger.setAttribute('aria-expanded', 'true');
        isOpen = true;

        fetchNotifications();
    }

    function close() {
        panel.classList.add('opacity-0', '-translate-y-1');
        trigger.setAttribute('aria-expanded', 'false');
        isOpen = false;

        window.setTimeout(() => {
            panel.classList.add('hidden');
            panel.style.display = 'none';
        }, 150);
    }

    trigger.addEventListener('click', () => {
        if (isOpen) {
            close();
            return;
        }

        open();
    });

    markAllButton.addEventListener('click', async () => {
        await markAllAsRead();
    });

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            activeTab = tab.dataset.notificationPopoverTab;

            tabs.forEach((item) => {
                item.setAttribute(
                    'aria-selected',
                    String(item === tab),
                );
            });

            render();
        });
    });

    document.addEventListener('click', (event) => {
        if (!isOpen || root.contains(event.target)) {
            return;
        }

        close();
    });
}