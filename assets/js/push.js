// Push Notification Handler
const publicVapidKey = 'BLj7PCY9rwVlA0l8OKxU-qepoOzcjEV87QndKEwa_vYz3SB66dC931uHhboeZxzht_b1BddM9_shDcWyU2KU5Rw';

// Check if browser supports notifications
if ('serviceWorker' in navigator && 'PushManager' in window) {
    registerPushNotifications();
}

async function registerPushNotifications() {
    try {
        // Request permission from user
        const permission = await Notification.requestPermission();
        
        if (permission !== 'granted') {
            console.log('❌ Notification permission denied');
            return;
        }
        
        console.log('✅ Notification permission granted');
        
        // Wait for service worker
        const sw = await navigator.serviceWorker.ready;
        console.log('✅ Service Worker ready');
        
        // Check if already subscribed
        let subscription = await sw.pushManager.getSubscription();
        
        if (!subscription) {
            // Create new subscription
            subscription = await sw.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicVapidKey)
            });
            console.log('✅ New push subscription created');
        } else {
            console.log('✅ Existing push subscription found');
        }
        
        // Send subscription to server
        const response = await fetch('/foc-connect/api/save-subscription.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(subscription)
        });
        
        if (response.ok) {
            console.log('✅ Subscription saved to server');
            showTestNotification();
        } else {
            console.log('❌ Failed to save subscription');
        }
        
    } catch (error) {
        console.error('❌ Push registration failed:', error);
    }
}

function showTestNotification() {
    if (Notification.permission === 'granted') {
        new Notification('🔔 FoC Connect', {
            body: 'Push notifications are enabled! You will receive alerts for new messages and announcements.',
            icon: '/foc-connect/assets/icons/icon-192x192.png',
            badge: '/foc-connect/assets/icons/icon-72x72.png',
            vibrate: [200, 100, 200]
        });
    }
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
    
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// Test function - call from browser console
function sendTestNotification() {
    if (Notification.permission === 'granted') {
        new Notification('📬 Test Notification', {
            body: 'This is a test notification from FoC Connect!',
            icon: '/foc-connect/assets/icons/icon-192x192.png',
            requireInteraction: true
        });
    } else {
        console.log('Notification permission not granted. Please refresh and allow permissions.');
    }
}

console.log('🚀 Push notification system loaded');