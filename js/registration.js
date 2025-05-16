/**
 * /js/registration.js
 * Version: 1.0.0
 * Registration-related functionality
 */

(function() {
    // Auf skyrunApp zugreifen
    const { API_URL, showStatus } = window.skyrunApp || {};
    
    // DOM-Elemente
    const form = document.getElementById('skyrun-form');
    const runDateSelect = document.getElementById('run-date');
    const personCountInput = document.getElementById('person-count');
    const confirmationModal = document.getElementById('confirmation-modal');
    const confirmationMessage = document.getElementById('confirmation-message');
    
    // Anmeldeformular verarbeiten
    async function handleRegistration(e) {
        e.preventDefault();
        const name = document.getElementById('name').value.trim();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const station = document.getElementById('station').value;
        const personCount = parseInt(personCountInput.value);
        const date = runDateSelect.value;
        const acceptWaitlist = document.getElementById('waitlist').checked;

        if (!name || !email || !station || !date || isNaN(personCount) || personCount < 1 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showStatus('Bitte alle Pflichtfelder korrekt ausfüllen.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'register');
        formData.append('name', name);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('station', station);
        formData.append('date', date);
        formData.append('acceptWaitlist', acceptWaitlist);
        formData.append('personCount', personCount);

        const submitButton = document.getElementById('submit-btn');
        submitButton.disabled = true;

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) throw new Error('HTTP Fehler');
            const result = await response.json();

            if (result.success) {
                form.reset();
                personCountInput.value = 1;
                window.skyrunApp.updateStatistics();
                const personText = personCount === 1 ? 'Person' : 'Personen';
                const formattedDate = window.skyrunApp.formatDate(new Date(date + 'T00:00:00'));
                confirmationMessage.textContent = result.isWaitlisted
                    ? `Sie wurden mit ${personCount} ${personText} auf die Warteliste für ${formattedDate} gesetzt.`
                    : `Ihre Anmeldung für ${personCount} ${personText} am ${formattedDate} wurde registriert.`;
                confirmationModal.classList.remove('hidden');
            } else {
                showStatus(result.message || 'Fehler bei der Anmeldung.', 'error');
            }
        } catch (error) {
            showStatus('Netzwerkfehler.', 'error');
            console.error('Anmeldefehler:', error);
        } finally {
            submitButton.disabled = false;
        }
    }
    
    // Event-Listener einrichten
    if (form) form.addEventListener('submit', handleRegistration);
})();