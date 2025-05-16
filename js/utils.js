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
    }
    
    // Funktionen global verfügbar machen
    window.skyrunApp = {
        formatDate: formatDate,
        escapeHTML: escapeHTML,
        downloadFile: downloadFile,
        showStatus: showStatus
    };
})();