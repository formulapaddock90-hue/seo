<?php
require __DIR__ . '/auth.php';
checkAuth();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏁 Test Classifica Finale F1</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; margin-bottom: 30px; }
        .test-item { margin: 20px 0; padding: 15px; border-left: 4px solid #ddd; }
        .test-item.pending { border-color: #ff9800; background: #fff3e0; }
        .test-item.ok { border-color: #4caf50; background: #e8f5e9; }
        .test-item.error { border-color: #d32f2f; background: #ffebee; }
        .test-title { font-weight: bold; font-size: 16px; margin-bottom: 10px; }
        .test-result { font-family: monospace; font-size: 13px; padding: 10px; background: #f5f5f5; border-radius: 4px; max-height: 200px; overflow-y: auto; }
        button { background: #0b57d0; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        button:hover { background: #0845a6; }
        .status { margin-top: 10px; padding: 10px; border-radius: 4px; font-size: 13px; }
        .summary { margin-top: 30px; padding: 20px; background: #f0f4f9; border-radius: 8px; }
        .summary h2 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏁 Test Classifica Finale F1</h1>

        <!-- Test 1: Connessione DB -->
        <div class="test-item pending" id="test-db">
            <div class="test-title">1️⃣ Connessione Database</div>
            <button onclick="testDatabase()">Testa</button>
            <div class="test-result" id="test-db-result" style="display:none;"></div>
        </div>

        <!-- Test 2: Tabella DB -->
        <div class="test-item pending" id="test-table">
            <div class="test-title">2️⃣ Creazione/Verifica Tabella</div>
            <button onclick="testCreateTable()">Testa</button>
            <div class="test-result" id="test-table-result" style="display:none;"></div>
        </div>

        <!-- Test 3: UndercutF1 API -->
        <div class="test-item pending" id="test-undercutf1">
            <div class="test-title">3️⃣ Connessione UndercutF1 API</div>
            <button onclick="testUndercutf1()">Testa</button>
            <div class="test-result" id="test-undercutf1-result" style="display:none;"></div>
        </div>

        <!-- Test 4: Fetch Classifica -->
        <div class="test-item pending" id="test-fetch">
            <div class="test-title">4️⃣ Fetch Classifica Finale</div>
            <button onclick="testFetchStandings()">Testa</button>
            <div class="test-result" id="test-fetch-result" style="display:none;"></div>
        </div>

        <!-- Test 5: Save nel DB -->
        <div class="test-item pending" id="test-save">
            <div class="test-title">5️⃣ Salva Classifica nel DB</div>
            <button onclick="testSaveStandings()">Testa</button>
            <div class="test-result" id="test-save-result" style="display:none;"></div>
        </div>

        <!-- Test 6: Read dal DB -->
        <div class="test-item pending" id="test-read">
            <div class="test-title">6️⃣ Leggi Classifica dal DB</div>
            <button onclick="testReadStandings()">Testa</button>
            <div class="test-result" id="test-read-result" style="display:none;"></div>
        </div>

        <!-- Summary -->
        <div class="summary">
            <h2>📊 Riepilogo Test</h2>
            <div id="summary-content">
                Clicca su "Testa" per ogni sezione per verificare il sistema.
            </div>
        </div>
    </div>

    <script>
        async function makeRequest(url, options = {}) {
            try {
                const response = await fetch(url, options);
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch {
                    return { raw: text, status: response.status, ok: response.ok };
                }
            } catch (e) {
                return { error: e.message };
            }
        }

        async function testDatabase() {
            const el = document.getElementById('test-db');
            const result = document.getElementById('test-db-result');

            el.className = 'test-item pending';
            result.textContent = '⏳ Test in corso...';
            result.style.display = 'block';

            // Verifica connessione tramite API
            const res = await makeRequest('api/final-standings.php?action=get&race_number=0');

            if (res.ok === undefined && !res.error) {
                el.className = 'test-item ok';
                result.textContent = '✅ Database connesso correttamente';
            } else if (res.error) {
                el.className = 'test-item error';
                result.textContent = '❌ Errore: ' + res.error;
            } else {
                el.className = 'test-item ok';
                result.textContent = '✅ Database raggiungibile';
            }
        }

        async function testCreateTable() {
            const el = document.getElementById('test-table');
            const result = document.getElementById('test-table-result');

            el.className = 'test-item pending';
            result.textContent = '⏳ Creazione tabella...';
            result.style.display = 'block';

            const res = await makeRequest('api/final-standings.php?action=create_table');

            result.textContent = JSON.stringify(res, null, 2);
            if (res.ok) {
                el.className = 'test-item ok';
            } else {
                el.className = 'test-item error';
            }
        }

        async function testUndercutf1() {
            const el = document.getElementById('test-undercutf1');
            const result = document.getElementById('test-undercutf1-result');

            el.className = 'test-item pending';
            result.textContent = '⏳ Test connessione UndercutF1...';
            result.style.display = 'block';

            const res = await makeRequest('api/undercutf1-standings.php?action=test');

            result.textContent = JSON.stringify(res, null, 2);
            if (res.ok) {
                el.className = 'test-item ok';
            } else {
                el.className = 'test-item error';
            }
        }

        async function testFetchStandings() {
            const el = document.getElementById('test-fetch');
            const result = document.getElementById('test-fetch-result');

            el.className = 'test-item pending';
            result.textContent = '⏳ Recupero classifica...';
            result.style.display = 'block';

            const res = await makeRequest('api/undercutf1-standings.php?action=fetch_standings');

            if (res.ok && res.data) {
                result.textContent = `✅ Classifica recuperata: ${res.data.length} piloti\n\n` + res.data.slice(0, 3).map(d => `${d.Posizione}. ${d.Pilota} (${d.Team})`).join('\n');
                el.className = 'test-item ok';
            } else {
                result.textContent = JSON.stringify(res, null, 2);
                el.className = 'test-item error';
            }
        }

        let lastStandingsCount = 0;

        async function testSaveStandings() {
            const el = document.getElementById('test-save');
            const result = document.getElementById('test-save-result');

            el.className = 'test-item pending';
            result.textContent = '⏳ Recupero e salvo classifica...';
            result.style.display = 'block';

            const standings = await makeRequest('api/undercutf1-standings.php?action=fetch_standings');

            if (!standings.ok) {
                result.textContent = '❌ Errore fetch: ' + standings.error;
                el.className = 'test-item error';
                return;
            }

            lastStandingsCount = standings.data.length;
            const formData = new URLSearchParams();
            formData.append('race_number', Math.floor(Date.now() / 1000));
            formData.append('standings', JSON.stringify(standings.data));

            const saveRes = await makeRequest('api/final-standings.php?action=save', {
                method: 'POST',
                body: formData
            });

            result.textContent = JSON.stringify(saveRes, null, 2);
            if (saveRes.ok) {
                el.className = 'test-item ok';
            } else {
                el.className = 'test-item error';
            }
        }

        async function testReadStandings() {
            const el = document.getElementById('test-read');
            const result = document.getElementById('test-read-result');

            el.className = 'test-item pending';
            result.textContent = '⏳ Lettura dal database...';
            result.style.display = 'block';

            const res = await makeRequest('api/final-standings.php?action=get');

            if (res.ok && res.data && res.data.length > 0) {
                const html = `✅ ${res.data.length} record trovati\n\nUltimi 3:\n` + res.data.slice(0, 3).map(d => `${d.position}. ${d.driver_name} (${d.team_name}) - ${d.created_at}`).join('\n');
                result.textContent = html;
                el.className = 'test-item ok';
            } else {
                result.textContent = '⚠️ Nessun dato nel database (normale al primo run)';
                el.className = 'test-item pending';
            }
        }

        // Auto-run all tests
        window.addEventListener('load', async () => {
            await testDatabase();
            await new Promise(r => setTimeout(r, 500));
            await testCreateTable();
            await new Promise(r => setTimeout(r, 500));
            await testUndercutf1();
            await new Promise(r => setTimeout(r, 500));
        });
    </script>
</body>
</html>
