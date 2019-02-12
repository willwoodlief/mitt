ini_set( 'log_errors', 1 );
ini_set( 'error_log', WP_CONTENT_DIR . '/debug.log' );

vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Reader/Csv.php
line 258
replace:
$line = preg_replace('/(' . $enclosure . '.*' . $enclosure . ')/U', '', $line);
with 
// Add 's' to the replace rule in order for '.' to also match newline.
$line = preg_replace('/(' . $enclosure . '.*' . $enclosure . ')/Us', '', $line);

to allow using newlines inside csv fields