<?php
$dias = array("domingo", "lunes", "martes", "miércoles", "jueves", "viernes", "sábado");
$execstring = "cd /var/www/html/apidian/demmon; find log_*_*_" . $dias[date('w') + 1] . ".log -type f -exec rm -f {} \;";
$output = [];
echo exec($execstring, $output);
