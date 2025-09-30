/**
 * Notifications Module
 * Handles fetching, displaying, and managing user notifications.
 * v1.6 - Shortened polling interval for more 'live' updates.
 * - Added detailed logging for notification dot visibility.
 * - Changed to mark notifications as read only on individual click.
 */
import { API_BASE_URL } from '../utils.js';

// DOM elements that will be accessed by this module
let notificationListElement = null;
let notificationDotElement = null;
let notificationDropdownElement = null;

let isDropdownOpen = false;
// MODIFICATION: Shortened polling interval from 60000ms (60s) to 20000ms (20s)
// Adjust this value based on desired responsiveness vs. server load.
// Lower values are more "live" but increase server requests.
const NOTIFICATION_FETCH_INTERVAL = 20000; // Fetch new notifications every 20 seconds
let notificationIntervalId = null;

/**
 * Initializes the notification system.
 */
export function initializeNotificationSystem() {
    console.log("[Notifications] Initializing system...");
    notificationListElement = document.getElementById('notification-list');
    notificationDotElement = document.getElementById('notification-dot');
    notificationDropdownElement = document.getElementById('notification-dropdown');

    if (!notificationListElement || !notificationDotElement || !notificationDropdownElement) {
        console.error("[Notifications] Critical UI elements for notifications are missing. IDs: 'notification-list', 'notification-dot', 'notification-dropdown'.");
        return;
    }
    console.log("[Notifications] UI elements found:", {
        list: !!notificationListElement,
        dot: !!notificationDotElement,
        dropdown: !!notificationDropdownElement
    });

    fetchAndRenderNotifications();

    if (notificationIntervalId) {
        clearInterval(notificationIntervalId);
    }
    notificationIntervalId = setInterval(fetchAndRenderNotifications, NOTIFICATION_FETCH_INTERVAL);
    console.log(`[Notifications] Periodic fetching started (Interval: ${NOTIFICATION_FETCH_INTERVAL}ms, ID: ${notificationIntervalId}).`);

    if (notificationListElement) {
        notificationListElement.addEventListener('click', handleNotificationItemClick);
    }
}

/**
 * Stops the periodic fetching of notifications.
 */
export function stopNotificationFetching() {
    if (notificationIntervalId) {
        clearInterval(notificationIntervalId);
        notificationIntervalId = null;
        console.log("[Notifications] Stopped periodic fetching.");
    }
}

/**
 * Fetches notifications from the API and triggers rendering.
 */
export async function fetchAndRenderNotifications() {
    if (!window.currentUser || !window.currentUser.user_id) {
        console.log("[Notifications] No user logged in. Skipping fetch.");
        updateNotificationDot(0);
        if(notificationListElement) notificationListElement.innerHTML = '<p class="p-4 text-sm text-gray-500 text-center">Please log in to see notifications.</p>';
        return;
    }
    // console.log("[Notifications] Fetching notifications..."); // Less frequent logging for shorter intervals

    try {
        const response = await fetch(`${API_BASE_URL}get_notifications.php`);
        if (!response.ok) {
            const errorText = await response.text();
            console.error(`[Notifications] HTTP error! Status: ${response.status}, Response: ${errorText.substring(0, 200)}`);
            const errorData = JSON.parse(errorText);
            throw new Error(errorData.error || `HTTP error! Status: ${response.status}`);
        }
        const data = await response.json();
        // console.log("[Notifications] Data received from server:", data); // Can be noisy with short intervals


        if (data.error) {
            throw new Error(data.error);
        }

        renderNotificationList(data.notifications || []);
        // console.log('[Notifications] Calling updateNotificationDot with unread_count from server:', data.unread_count); // Can be noisy
        updateNotificationDot(data.unread_count || 0);

    } catch (error) {
        console.error("[Notifications] Error fetching/rendering notifications:", error.message);
    }
}

/**
 * Renders the list of notifications into the dropdown panel.
 * @param {Array} notifications - Array of notification objects from the API.
 */
function renderNotificationList(notifications) {
    if (!notificationListElement) {
        // console.error("[Notifications] notificationListElement is null, cannot render."); // Less frequent logging
        return;
    }

    if (!notifications || notifications.length === 0) {
        notificationListElement.innerHTML = '<p class="p-4 text-sm text-gray-500 text-center">No new notifications.</p>';
        return;
    }

    let html = notifications.map(notif => `
        <a href="${notif.Link || '#'}"
           class="block px-4 py-3 hover:bg-gray-100 notification-item ${!notif.IsRead ? 'bg-blue-50' : ''}"
           data-notification-id="${notif.NotificationID}"
           data-is-read="${notif.IsRead}"
           data-link="${notif.Link || '#'}">
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0 pt-0.5">
                    ${getNotificationIcon(notif.NotificationType)}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-800 ${!notif.IsRead ? 'font-semibold' : ''} truncate">
                        ${notif.Message || 'No message content.'}
                    </p>
                    <p class="text-xs text-gray-500">
                        ${notif.SenderName ? `From: ${notif.SenderName} - ` : ''}
                        ${notif.TimeAgo || notif.CreatedAtFormatted || ''}
                    </p>
                </div>
                ${!notif.IsRead ? '<span class="ml-2 mt-1 h-2 w-2 rounded-full bg-blue-500 flex-shrink-0" title="Unread"></span>' : ''}
            </div>
        </a>
    `).join('');

    // Add "View All Notifications" link
    html += `<a href="#notifications" class="block px-4 py-3 text-center text-blue-600 hover:bg-gray-100 view-all-notifications">View All Notifications</a>`;

    notificationListElement.innerHTML = html;
}

/**
 * Returns an icon based on the notification type.
 * @param {string} notificationType - The type of the notification.
 * @returns {string} - HTML string for the icon.
 */
function getNotificationIcon(notificationType) {
    let iconClass = 'fa-solid fa-info-circle text-gray-400'; // Default icon
    switch (notificationType) {
        case 'NEW_LEAVE_REQUEST':
            iconClass = 'fa-solid fa-calendar-plus text-blue-500';
            break;
        case 'LEAVE_APPROVED':
            iconClass = 'fa-solid fa-calendar-check text-green-500';
            break;
        case 'LEAVE_REJECTED':
            iconClass = 'fa-solid fa-calendar-times text-red-500';
            break;
        case 'LEAVE_CANCELLED':
            iconClass = 'fa-solid fa-calendar-times text-red-500';
            break;
        case 'NEW_CLAIM_SUBMITTED':
            iconClass = 'fa-solid fa-receipt text-purple-500';
            break;
        case 'CLAIM_APPROVED':
        case 'CLAIM_PAID':
            iconClass = 'fa-solid fa-check-double text-green-500';
            break;
        case 'CLAIM_REJECTED':
        case 'CLAIM_QUERIED':
            iconClass = 'fa-solid fa-file-circle-xmark text-red-500';
            break;
        case 'TIMESHEET_SUBMITTED':
            iconClass = 'fa-solid fa-clock text-yellow-500';
            break;
        case 'TIMESHEET_APPROVED':
            iconClass = 'fa-solid fa-user-clock text-green-500';
            break;
        case 'TIMESHEET_REJECTED':
            iconClass = 'fa-solid fa-hourglass-end text-red-500';
            break;
        case 'PAYROLL_COMPLETED':
            iconClass = 'fa-solid fa-money-check-dollar text-green-500';
            break;
    }
    return `<i class="${iconClass} fa-fw text-base"></i>`;
}


/**
 * Updates the visibility of the notification dot based on unread count.
 * @param {number} unreadCount - The number of unread notifications.
 */
function updateNotificationDot(unreadCount) {
    // console.log('[Notifications Debug] updateNotificationDot called with unreadCount:', unreadCount); // Can be noisy
    if (!notificationDotElement) {
        // console.error('[Notifications Debug] notificationDotElement is null in updateNotificationDot!'); // Less frequent logging
        return;
    }
    // console.log('[Notifications Debug] notificationDotElement found:', notificationDotElement); // Less frequent logging

    if (unreadCount > 0) {
        // console.log('[Notifications Debug] Showing notification dot.'); // Can be noisy
        notificationDotElement.classList.remove('hidden');
        notificationDotElement.innerHTML = unreadCount > 99 ? '99+' : unreadCount.toString();
    } else {
        // console.log('[Notifications Debug] Hiding notification dot (unreadCount is 0 or less).'); // Can be noisy
        notificationDotElement.classList.add('hidden');
        notificationDotElement.innerHTML = '';
    }
    const isHidden = notificationDotElement.classList.contains('hidden');
    const displayStyle = window.getComputedStyle(notificationDotElement).display;
    // console.log(`[Notifications Debug] Dot classList: ${notificationDotElement.className}, isHidden: ${isHidden}, displayStyle: ${displayStyle}`); // Can be noisy
}

/**
 * Handles clicks on individual notification items in the dropdown.
 */
async function handleNotificationItemClick(event) {
    const notificationItem = event.target.closest('.notification-item');
    const viewAllLink = event.target.closest('.view-all-notifications');
    if (!notificationItem && !viewAllLink) return;

    event.preventDefault();

    if (viewAllLink) {
        // Handle "View All Notifications" click
        if (typeof window.navigateToSectionById === 'function') {
            window.navigateToSectionById('notifications');
        } else {
            console.warn('[Notifications] Global function "window.navigateToSectionById" not found. Cannot navigate to notifications section.');
        }
        if (notificationDropdownElement) {
            notificationDropdownElement.classList.add('hidden');
            onNotificationDropdownClose();
        }
        return;
    }

    const notificationId = notificationItem.dataset.notificationId;
    const link = notificationItem.dataset.link;
    const isRead = notificationItem.dataset.isRead === '1' || notificationItem.dataset.isRead === true;

    // console.log(`[Notifications] Clicked item ID: ${notificationId}, IsRead: ${isRead}, Link: ${link}`); // Can be noisy

    if (notificationId && !isRead) {
        await markSingleNotificationAsRead(notificationId, notificationItem);
    }

    if (notificationDropdownElement) {
        notificationDropdownElement.classList.add('hidden');
        onNotificationDropdownClose();
    }

    if (link && link !== '#') {
        // console.log(`[Notifications] Navigating to: ${link}`); // Can be noisy
        if (link.startsWith('#')) {
            const sectionId = link.substring(1);
            if (typeof window.navigateToSectionById === 'function') {
                window.navigateToSectionById(sectionId);
            } else {
                console.warn(`[Notifications] Global function 'window.navigateToSectionById' not found. Cannot navigate to section: ${sectionId}.`);
            }
        } else {
            window.location.href = link;
        }
    }
}

/**
 * Marks a single notification as read on the backend and updates its UI.
 */
async function markSingleNotificationAsRead(notificationId, itemElement) {
    // console.log(`[Notifications] Marking single notification as read: ${notificationId}`); // Can be noisy
    try {
        const response = await fetch(`${API_BASE_URL}mark_notification_read.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_id: parseInt(notificationId) })
        });
        const result = await response.json();
        if (!response.ok || result.error) {
            throw new Error(result.error || `Failed to mark notification ${notificationId} as read.`);
        }
        // console.log(`[Notifications] Successfully marked ${notificationId} as read on server.`); // Can be noisy

        if (itemElement) {
            itemElement.classList.remove('bg-blue-50');
            const messageElement = itemElement.querySelector('p.text-sm.text-gray-800');
            if(messageElement) messageElement.classList.remove('font-semibold');
            itemElement.dataset.isRead = '1';
            const unreadIndicator = itemElement.querySelector('.ml-2.mt-1.h-2.w-2');
            if (unreadIndicator) unreadIndicator.remove();
            // console.log(`[Notifications] UI updated for item ${notificationId}.`); // Can be noisy
        }
        await fetchAndRenderNotifications();
    } catch (error) {
        console.error("[Notifications] Error marking single notification as read:", error.message);
    }
}

/**
 * Marks multiple notifications as read on the backend. (Currently not auto-called)
 */
async function markMultipleNotificationsAsRead(notificationIds) {
    if (!notificationIds || notificationIds.length === 0) return;
    // console.log(`[Notifications] (Manual Call) Marking multiple notifications as read:`, notificationIds); // Can be noisy
    try {
        const response = await fetch(`${API_BASE_URL}mark_notification_read.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_ids: notificationIds })
        });
        const result = await response.json();
        if (!response.ok || result.error) {
            throw new Error(result.error || `Failed to mark multiple notifications as read.`);
        }
        // console.log(`[Notifications] Successfully marked ${result.message || 'notifications'} as read.`); // Can be noisy

        notificationIds.forEach(id => {
            if (notificationListElement) {
                const itemElement = notificationListElement.querySelector(`.notification-item[data-notification-id="${id}"]`);
                if (itemElement) {
                    itemElement.classList.remove('bg-blue-50');
                    const messageElement = itemElement.querySelector('p.text-sm.text-gray-800');
                    if(messageElement) messageElement.classList.remove('font-semibold');
                    itemElement.dataset.isRead = '1';
                    const unreadIndicator = itemElement.querySelector('.ml-2.mt-1.h-2.w-2');
                    if (unreadIndicator) unreadIndicator.remove();
                }
            }
        });
        await fetchAndRenderNotifications();
    } catch (error) {
        console.error("[Notifications] Error marking multiple notifications as read:", error.message);
    }
}

/**
 * Called by main.js when the notification dropdown is opened.
 */
export function onNotificationDropdownOpen() {
    isDropdownOpen = true;
    console.log("[Notifications] Dropdown opened by user.");
    fetchAndRenderNotifications();
}

/**
 * Called by main.js when the notification dropdown is closed.
 */
export function onNotificationDropdownClose() {
    isDropdownOpen = false;
    console.log("[Notifications] Dropdown closed by user.");
}
