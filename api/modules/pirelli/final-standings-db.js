/**
 * Modulo Classifica Finale - Accesso Diretto a MySQL
 * NO dipendenza da UndercutF1 API
 * Legge direttamente dal database
 */

const FinalStandingsDB = {
    standings: [],
    pollingActive: false,
    pollingCount: 0,
    pollingMaxCount: 4,
    pollingInterval: null,

    init() {
        this.attachButtonListener();
        this.startChequeredFlagMonitor();
    },

    attachButtonListener() {
        const btn = document.getElementById('load-final-standings-db');
        if (btn) btn.addEventListener('click', () => this.loadStandings());

        const importBtn = document.getElementById('import-local-standings');
        if (importBtn) importBtn.addEventListener('click', () => this.importFromLocalFile());
    },

    /**
     * Carica classifica DIRETTAMENTE dal database MySQL
     */
    async loadStandings() {
        const btn = document.getElementById('load-final-standings-db');
        const status = document.getElementById('final-standings-status');
        const table = document.getElementById('session-result-table');

        if (!btn || !table) return;

        btn.disabled = true;
        btn.textContent = '⏳ Caricamento...';
        if (status) status.textContent = '⏳ Caricamento dal database MySQL...';

        try {
            // Leggi DIRETTAMENTE dal database (NO API UndercutF1)
            const response = await fetch('api/standings-direct-db.php?action=get_latest');
            const result = await response.json();

            if (!result.ok) {
                throw new Error(result.error || result.message || 'Errore caricamento dal DB');
            }

            this.standings = result.data || [];

            if (this.standings.length === 0) {
                throw new Error('Nessun dato nel database. Salva una classifica prima.');
            }

            this.renderTable(table);

            btn.textContent = '✅ Caricati ' + this.standings.length + ' piloti';
            if (status) status.textContent = `✅ Classifica caricata (${this.standings.length} piloti - Gara #${result.race_number})`;

            setTimeout(() => {
                btn.disabled = false;
                btn.textContent = '🏁 Da Database';
            }, 2000);

        } catch (error) {
            console.error('❌ Errore:', error);
            btn.textContent = '❌ Errore';
            if (status) status.textContent = `❌ ${error.message}`;
            btn.disabled = false;
            setTimeout(() => {
                btn.textContent = '🏁 Da Database';
            }, 3000);
        }
    },

    /**
     * Importa dal file locale a MySQL
     */
    async importFromLocalFile() {
        const btn = document.getElementById('import-local-standings');
        const status = document.getElementById('final-standings-status');
        const table = document.getElementById('session-result-table');

        if (!btn || !table) return;

        btn.disabled = true;
        btn.textContent = '⏳ Importazione...';
        if (status) status.textContent = '⏳ Importazione dal file locale a MySQL...';

        try {
            // Sincronizza file → MySQL
            const syncResponse = await fetch('api/standings-direct-db.php?action=sync_from_file');
            const syncResult = await syncResponse.json();

            if (!syncResult.ok) {
                throw new Error(syncResult.error || 'Errore importazione');
            }

            // Leggi dal DB
            const getResponse = await fetch('api/standings-direct-db.php?action=get_latest');
            const getData = await getResponse.json();

            if (!getData.ok) {
                throw new Error(getData.error || 'Nessun dato dopo importazione');
            }

            this.standings = getData.data || [];

            if (this.standings.length === 0) {
                throw new Error('Nessun dato per l\'ultima gara');
            }

            this.renderTable(table);

            btn.textContent = '✅ Importati ' + this.standings.length + ' piloti';
            if (status) status.textContent = `✅ Importazione completata (${this.standings.length} piloti nel DB)`;

            setTimeout(() => {
                btn.disabled = false;
                btn.textContent = '📁 Da File Local';
            }, 2000);

        } catch (error) {
            console.error('❌ Errore importazione:', error);
            btn.textContent = '❌ Errore';
            if (status) status.textContent = `❌ ${error.message}`;
            btn.disabled = false;
            setTimeout(() => {
                btn.textContent = '📁 Da File Local';
            }, 3000);
        }
    },

    /**
     * Monitora bandiera a scacchi (🏁) per polling automatico
     */
    startChequeredFlagMonitor() {
        setInterval(() => {
            const raceControl = document.getElementById('race-control');
            if (!raceControl) return;

            const hasChequered =
                raceControl.textContent?.includes('🏁') ||
                raceControl.textContent?.includes('chequered') ||
                raceControl.dataset.status === 'finished' ||
                raceControl.classList.contains('finished');

            if (hasChequered && !this.pollingActive) {
                console.log('🏁 [ModD] Bandiera a scacchi! Inizio polling...');
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

        console.log('🏁 [ModD] Polling iniziato');
        this.importFromLocalFile();

        this.pollingInterval = setInterval(() => {
            this.pollingCount++;
            if (this.pollingCount >= this.pollingMaxCount) {
                this.stopAutoPolling();
                return;
            }

            console.log(`🏁 [ModD] Polling: ${this.pollingCount + 1}/${this.pollingMaxCount}`);
            this.importFromLocalFile();
        }, 60000);
    },

    /**
     * Ferma polling automatico
     */
    stopAutoPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
        this.pollingActive = false;
        this.pollingCount = 0;
        console.log('✅ [ModD] Polling completato');
    },

    renderTable(table) {
        if (!table) return;
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        tbody.innerHTML = '';
        this.standings.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="font-weight:bold; text-align:center;">${row.position}</td>
                <td style="text-align:center;">${row.driver_number || '-'}</td>
                <td>${row.driver_name || '-'}</td>
                <td>${row.team_name || '-'}</td>
                <td>${row.best_lap || '-'}</td>
                <td>${row.last_lap || '-'}</td>
                <td style="text-align:center;">${row.total_laps || '-'}</td>
                <td>${row.gap || '-'}</td>
            `;
            tbody.appendChild(tr);
        });
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => FinalStandingsDB.init());
} else {
    FinalStandingsDB.init();
}
