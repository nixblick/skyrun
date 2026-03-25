/**
 * /js/utils.js
 * Version: 1.0.0
 * Utility functions
 */

(function() {
    /**
     * Formatiert ein Datum für die Anzeige
     * 
     * @param {Date} date Datumsobjekt
     * @return {string} Formatiertes Datum
     */
    function formatDate(date) {
        return date.toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
    }
    
    /**
     * Escape HTML-Sonderzeichen für sichere Ausgabe
     * 
     * @param {string} str Die zu bereinigende Zeichenkette
     * @return {string} Bereinigte Zeichenkette
     */
    function escapeHTML(str) {
        if (typeof str !== 'string') return str;
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    /**
     * Datei als Download anbieten
     * 
     * @param {Blob} blob Die zu downloadende Datei als Blob
     * @param {string} filename Dateiname
     */
    function downloadFile(blob, filename) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }
    
    /**
     * Status-Nachricht anzeigen
     * 
     * @param {string} message Nachrichtentext
     * @param {string} type Typ (success, error, warning)
     */
    function showStatus(message, type) {
        const registrationStatus = document.getElementById('registration-status');
        if (!registrationStatus) return;
        
        registrationStatus.textContent = message;
        registrationStatus.className = `registration-status ${type}`;
        registrationStatus.classList.remove('hidden');
        registrationStatus.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    /**
     * Theme Toggle (Light/Dark)
     */
    function initTheme() {
        var saved = localStorage.getItem('skyrun-theme');
        if (saved === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        updateToggleIcon();

        var btn = document.getElementById('theme-toggle');
        if (btn) {
            btn.addEventListener('click', function() {
                var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                if (isDark) {
                    document.documentElement.removeAttribute('data-theme');
                    localStorage.setItem('skyrun-theme', 'light');
                } else {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('skyrun-theme', 'dark');
                }
                updateToggleIcon();
            });
        }
    }

    function updateToggleIcon() {
        var btn = document.getElementById('theme-toggle');
        if (!btn) return;
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        btn.innerHTML = isDark ? '&#9788;' : '&#9790;';
        btn.title = isDark ? 'Helles Theme' : 'Dunkles Theme';
    }

    document.addEventListener('DOMContentLoaded', initTheme);

    /**
     * Event an nixblick Analytics senden (als virtueller Pageview)
     * Events erscheinen im Dashboard als Pfade wie /evt/admin-login
     *
     * @param {string} eventName Event-Name (z.B. 'admin-login', 'registration')
     */
    function trackEvent(eventName) {
        var endpoint = 'https://static.nixblick.de/analytics/collect.php';
        var data = {
            s: 'mein-computerfreund.de',
            p: '/evt/' + eventName,
            r: '',
            w: window.innerWidth,
            h: window.innerHeight,
            t: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
            ts: Date.now()
        };
        var payload = JSON.stringify(data);
        if (navigator.sendBeacon) {
            navigator.sendBeacon(endpoint, new Blob([payload], { type: 'application/json' }));
        } else {
            fetch(endpoint, { method: 'POST', body: payload, keepalive: true, headers: { 'Content-Type': 'application/json' } }).catch(function() {});
        }
    }

    // Funktionen global verfügbar machen
    window.skyrunApp = {
        formatDate: formatDate,
        escapeHTML: escapeHTML,
        downloadFile: downloadFile,
        showStatus: showStatus,
        trackEvent: trackEvent
    };
})();