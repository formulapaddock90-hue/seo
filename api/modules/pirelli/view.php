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
    </div>
    <table id="pirelli-table">
        <thead><tr><th>Compound</th><th>Best Lap</th><th>Max Stint</th></tr></thead>
        <tbody></tbody>
    </table>
    <canvas id="pirelliCompoundChart"></canvas>
</section>
