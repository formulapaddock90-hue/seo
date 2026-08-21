<section id="mod-d" class="tab-content">
    <h2>Modulo C: Seleziona circuito e andamento temperature</h2>
    <label>Circuito
        <select id="circuit-select">
            <option value="">Seleziona circuito</option>
        </select>
    </label>
    <button type="button" id="load-circuit-temperature">Estrai temperature e crea grafico</button>
    <table id="circuit-temp-table">
        <thead><tr><th>Sessione</th><th>Min track (°C)</th><th>Avg track (°C)</th><th>Max track (°C)</th></tr></thead>
        <tbody></tbody>
    </table>
    <canvas id="circuitTempChart"></canvas>
</section>
