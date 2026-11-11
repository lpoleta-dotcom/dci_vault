<?php
$notificationRole = isset($notificationRole) && $notificationRole === 'admin' ? 'admin' : 'customer';
?>
<div class="notification-widget" data-notification-role="<?php echo $notificationRole; ?>">
    <button type="button" class="notification-bell" aria-label="Notifications" aria-expanded="false">
        <i class="bi bi-bell" aria-hidden="true"></i>
        <span class="notification-count d-none" aria-live="polite"></span>
    </button>
    <section class="notification-panel d-none" aria-label="Notifications">
        <div class="notification-panel-header">
            <strong>Notifications</strong>
            <button type="button" class="notification-mark-all" disabled>Mark all read</button>
        </div>
        <div class="notification-list" role="list">
            <p class="notification-empty">Loading notifications…</p>
        </div>
    </section>
</div>
<script src="backend/notifications.js?v=notifications-2" defer></script>
