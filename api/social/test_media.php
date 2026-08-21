<?php
// Test locale: usa il generatore immagini centrale di social/ e genera il reel senza chiamare Claude/Google.
require_once __DIR__ . '/../../social/includes/image_generator.php';
require_once __DIR__ . '/includes/video_generator.php';
$config = require __DIR__ . '/config.php';

$content = [
    'infografica_titolo'     => 'Verstappen domina a Monza',
    'infografica_sottotitolo' => 'Pole e vittoria con 12 secondi di vantaggio su Norris',
    'categoria'              => 'Gara',
];

$slug = 'test_' . date('His');
$images = generateAllInfographics($content, $slug, $config);
echo "FB:  {$images['fb_image']} (" . filesize($images['fb_image']) . " byte)\n";
echo "IG:  {$images['ig_image']} (" . filesize($images['ig_image']) . " byte)\n";

$reelPath = $config['output_reels_dir'] . "/{$slug}_reel.mp4";
generateReelVideo($images['ig_image'], $reelPath, 'Verstappen inarrestabile! Pole e vittoria a Monza.', $config, 8);
echo "REEL: {$reelPath} (" . filesize($reelPath) . " byte)\n";
