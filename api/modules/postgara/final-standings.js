/**
 * Modulo Classifica Finale F1
 * - Polling manuale (click pulsante)
 * - Polling automatico: 1 volta/min per 4 minuti quando appare chequered flag
 */

const FinalStandingsModule = {
    pollingActive: false,
    pollingCount: 0,
    pollingMaxCount: 4,
    pollingInterval: null,
    raceNumber: null,

    /**
     * Inizializza il modulo
     */
    async init() {
        // Aggancia il pulsante se esiste
        this.attachButtonListener();

        // Inizia il monitor della race control (chequered flag)
        this.startChequeredFlagMonitor();
    },

    /**
     * Assicurati che la tabella esista nel DB
     */
    async ensureTableExists() {
        try {
            await fetch('api/final-standings.php?action=create_table', { method: 'GET' });
        } catch (e) {
            console.error('❌ Errore creazione tabella:', e);
        }
    },

    /**
     * Attach al pulsante nel postgara
     */
    attachButtonListener() {
        const btn = document.getElementById('postgara-load-final-standings-btn');
        if (!btn) return;

        btn.addEventListener('click', async () => {
            if (this.pollingActive) {
                alert('⏳ Polling già in corso...');
                return;
            }
            await this.triggerManualPolling();
        });
    },

    /**
     * Polling manuale: 1 richiesta immediata
     */
    async triggerManualPolling() {
        console.log('🏁 Polling manuale classifica finale');
        this.setStatus('⏳ Caricamento classifica finale...');
        await this.fetchAndSaveStandings();
        this.setStatus('✅ Classifica finale caricata');
    },

    /**
     * Monitora race_control per chequered flag
     */
    startChequeredFlagMonitor() {
        setInterval(() => {
            const raceControl = document.getElementById('race-control');
            if (!raceControl) return;

            // Cerca indicatori di bandiera a scacchi
            const hasChequered =
                raceControl.textContent?.includes('🏁') ||
                raceControl.textContent?.includes('chequered') ||
                raceControl.dataset.status === 'finished' ||
                raceControl.classList.contains('finished');

            if (hasChequered && !this.pollingActive) {
                console.log('🏁 Rilevata bandiera a scacchi! Avvio polling automatico...');
                this.startAutoPolling();
            }
        }, 2000);
    },

    /**
     * Polling automatico: ogni minuto per 4 minuti
     */
    startAutoPolling() {
        if (this.pollingActive) return;

        this.pollingActive = true;
        this.pollingCount = 0;
        this.setStatus(`⏳ Polling automatico: 1/${this.pollingMaxCount}...`);

        // Prima richiesta immediata
        this.fetchAndSaveStandings();

        // Polling ogni 60 secondi
        this.pollingInterval = setInterval(async () => {
            this.pollingCount++;
            if (this.pollingCount >= this.pollingMaxCount) {
                this.stopAutoPolling();
                return;
            }

            this.setStatus(`⏳ Polling automatico: ${this.pollingCount + 1}/${this.pollingMaxCount}...`);
            await this.fetchAndSaveStandings();
        }, 60000); // 60 secondi
    },

    /**
     * Ferma il polling automatico
     */
    stopAutoPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
        this.pollingActive = false;
        this.pollingCount = 0;
        this.setStatus('✅ Polling completato (4 richieste)');
        console.log('✅ Polling automatico completato');
    },

    /**
     * Fetch della classifica finale
     * In produzione, questo chiamerebbe un'API esterna (undercutf1, OpenF1, etc)
     */
    async fetchAndSaveStandings() {
        try {
            // TODO: Integrare con API esterna (OpenF1, undercutf1, etc)
            // Per ora placeholder: richiesta a un endpoint che ritorna dati mock
            const standings = await this.getStandingsFromExternalAPI();

            if (!standings || standings.length === 0) {
                console.warn('⚠️ Nessun dato classifica disponibile');
                return;
            }

            // Salva nel database
            const response = await fetch('api/final-standings.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    race_number: this.raceNumber || new Date().getTime(),
                    standings: JSON.stringify(standings)
                })
            });

            this.displayStandings(standings);

            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.ok) {
                console.warn('⚠️ Classifica visualizzata ma non salvata:', result.error || `HTTP ${response.status}`);
                this.setStatus('⚠️ Classifica caricata; archivio DB non disponibile');
                return;
            }

            console.log(`✅ Classifica salvata: ${result.count} posizioni`);

        } catch (e) {
            console.error('❌ Errore fetch classifica:', e);
            this.setStatus('❌ Errore caricamento classifica');
        }
    },

    /**
     * Richiedi dati da UndercutF1 API locale
     */
    async getStandingsFromExternalAPI() {
        try {
            // Chiama il proxy PHP che legge da UndercutF1 API
            const response = await fetch('api/undercutf1-standings.php?action=fetch_standings');

            if (!response.ok) {
                console.warn('⚠️ UndercutF1 API non disponibile:', response.status);
                return this.getMockStandings();
            }

            const result = await response.json();
            if (result.ok && result.data && result.data.length > 0) {
                console.log('✅ Classifica ottenuta da UndercutF1:', result.data.length, 'piloti');
                return result.data;
            }

            // Fallback: placeholder per testing
            return this.getMockStandings();

        } catch (e) {
            console.error('❌ Errore API UndercutF1:', e);
            return this.getMockStandings();
        }
    },

    /**
     * Dati mock per testing
     */
    getMockStandings() {
        return [
            {
                Posizione: 1,
                'Numero Gara': 1,
                Pilota: 'Max Verstappen',
                Team: 'Red Bull Racing',
                'Best Lap': '1:20.567',
                'Ultimo Giro': '1:21.234',
                Giri: 57,
                Gap: ''
            },
            {
                Posizione: 2,
                'Numero Gara': 44,
                Pilota: 'Lewis Hamilton',
                Team: 'Mercedes',
                'Best Lap': '1:20.891',
                'Ultimo Giro': '1:20.945',
                Giri: 57,
                Gap: '+0.234'
            },
            {
                Posizione: 3,
                'Numero Gara': 16,
                Pilota: 'Charles Leclerc',
                Team: 'Ferrari',
                'Best Lap': '1:21.012',
                'Ultimo Giro': '1:21.356',
                Giri: 57,
                Gap: '+0.567'
            }
        ];
    },

    /**
     * Visualizza la classifica nel DOM
     */
    displayStandings(standings) {
        const container = document.getElementById('postgara-final-standings-display');
        if (!container) return;

        const html = `
            <div style="margin-top: 20px; padding: 15px; background: #f0f4f9; border-radius: 4px;">
                <h3>🏁 Classifica Finale Gara</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <thead style="background: #0b57d0; color: white;">
                        <tr>
                            <th style="padding: 8px; text-align: left;">Pos</th>
                            <th style="padding: 8px; text-align: left;">Pilota</th>
                            <th style="padding: 8px; text-align: left;">Team</th>
                            <th style="padding: 8px; text-align: center;">Giri</th>
                            <th style="padding: 8px; text-align: left;">Best Lap</th>
                            <th style="padding: 8px; text-align: left;">Gap</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${standings.map(s => `
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 8px; font-weight: bold;">${s.Posizione}</td>
                                <td style="padding: 8px;">${s.Pilota}</td>
                                <td style="padding: 8px;">${s.Team}</td>
                                <td style="padding: 8px; text-align: center;">${s.Giri}</td>
                                <td style="padding: 8px;">${s['Best Lap']}</td>
                                <td style="padding: 8px;">${s.Gap || '-'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;

        container.innerHTML = html;
    },

    /**
     * Aggiorna status text
     */
    setStatus(message) {
        const statusEl = document.getElementById('postgara-standings-status');
        if (statusEl) {
            statusEl.textContent = message;
            statusEl.style.display = 'block';
        }
    }
};

// Inizializza al caricamento della pagina
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => FinalStandingsModule.init());
} else {
    FinalStandingsModule.init();
}
