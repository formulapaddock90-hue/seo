<?php
/**
 * Shortcode handler per grafici Post Gara
 * Uso: [grafico-postgara title="Titolo" data="{...json config...}"]
 */

function postgara_grafico_shortcode($atts) {
    $atts = shortcode_atts([
        'title' => 'Grafico',
        'data'  => '{}',
    ], $atts);

    $title = sanitize_text_field($atts['title']);
    $dataJson = $atts['data'];

    // Decodifica JSON (è stato escaped con &quot;)
    $dataJson = str_replace('&quot;', '"', $dataJson);

    // Valida JSON
    json_decode($dataJson);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return '<div style="color:red;padding:10px;border:1px solid red;">Errore: dati grafico non validi</div>';
    }

    $chartId = 'postgara-chart-' . uniqid();

    ob_start();
    ?>
    <div style="position:relative;width:100%;margin:20px 0;">
        <canvas id="<?php echo esc_attr($chartId); ?>"></canvas>
    </div>
    <script>
    (function() {
        function renderChart() {
            const canvas = document.getElementById('<?php echo esc_attr($chartId); ?>');
            if (!canvas) return;
            if (typeof Chart === 'undefined') {
                setTimeout(renderChart, 100);
                return;
            }
            const ctx = canvas.getContext('2d');
            const config = <?php echo $dataJson; ?>;
            new Chart(ctx, config);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', renderChart);
        } else {
            renderChart();
        }
    })();
    </script>
    <?php

    // Assicura che Chart.js sia caricato
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);

    return ob_get_clean();
}

add_shortcode('grafico-postgara', 'postgara_grafico_shortcode');
