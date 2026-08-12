<section id="mod-f" class="tab-content">
    <h2>Modulo C: Dati F1</h2>

    <h3>🌡️ Circuito</h3>
    <div style="display:flex; gap:8px; align-items:flex-end; margin-bottom:8px;">
        <label style="margin:0;">Circuito
            <select id="circuit-select" style="margin-top:4px; width:200px;">
                <option value="">Seleziona circuito</option>
            </select>
        </label>
        <button type="button" id="load-circuit-temperature" style="margin:0; padding:6px 12px; white-space:nowrap;">Carica</button>
    </div>
    <table id="circuit-temp-table">
        <thead><tr><th>Sessione</th><th>Min (°C)</th><th>Avg (°C)</th><th>Max (°C)</th></tr></thead>
        <tbody></tbody>
    </table>
    <canvas id="circuitTempChart"></canvas>

    <h3>🏎️ Pirelli</h3>
    <div style="display:flex; gap:8px; align-items:flex-end; margin-bottom:8px;">
        <label style="margin:0;">Nazione
            <select id="pirelli-country" style="margin-top:4px; width:200px;">
                <option value="">Seleziona nazione</option>
            </select>
        </label>
        <button type="button" id="load-pirelli" style="margin:0; padding:6px 12px; white-space:nowrap;">Carica</button>
        <button type="button" id="load-standing-csv" style="margin:0; padding:6px 12px; white-space:nowrap; background-color:#2ecc71; color:white;">📊 Carica CSV Standing</button>
    </div>
    <div id="standing-status" style="margin-bottom:8px; padding:6px; background-color:#f0f0f0; border-radius:4px; display:none;"></div>
    <table id="pirelli-table">
        <thead><tr><th>Compound</th><th>Best Lap</th><th>Max Stint</th></tr></thead>
        <tbody></tbody>
    </table>
    <canvas id="pirelliCompoundChart"></canvas>

    <h3>📊 Classifica Sessione</h3>
    <div id="final-standings-status" style="margin-bottom:8px; padding:6px; background-color:#f0f0f0; border-radius:4px; display:none;"></div>
    <table id="session-result-table">
        <thead><tr><th>Pos</th><th>N.</th><th>Pilota</th><th>Team</th><th>Best Lap</th><th>Ultimo Giro</th><th>Giri</th><th>Gap</th></tr></thead>
        <tbody></tbody>
    </table>
</section>
