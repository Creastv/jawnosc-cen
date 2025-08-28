<?php
// runner-dane-gov.php – uruchamia eksport bez WP-Cron
require __DIR__ . '/../../../wp-load.php';  // idziemy 2 katalogi wyżej do public_html
do_action('dane_gov_exporter_daily');
echo "[Runner] Export finished\n";
