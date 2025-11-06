<?php
$demonio = $argv[0];
$strrun = trim($demonio . '.php ' . $instan . ' ' . $ipserv);
$output = [];
$retval = null;
exec('tasklist /V | findstr /l "' . $strrun . '"', $output, $retval);
if (count($output) <= 2) {
    try {
        date_default_timezone_set('America/Bogota');
        /* CONEXION CON MYSQL */
        DEFINE("SYS_ENGINEMYSQL", "mysql");
        DEFINE("SYS_HOSTMYSQL", "localhost");
        DEFINE("SYS_BBDDMYSQL", "apidian");
        DEFINE("SYS_USERMYSQL", "apidian");
        DEFINE("SYS_PASSMYSQL", "ApiDIAN2024@@");
        DEFINE("SYS_PORTMYSQL", "3306");
        $options = [
            PDO::ATTR_EMULATE_PREPARES   => false, // Disable emulation mode for "real" prepared statements
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Disable errors in the form of exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Make the default fetch be an associative array
        ];
        $conStr = sprintf("mysql:host=%s;dbname=%s;charset=utf8mb4", SYS_HOSTMYSQL, SYS_BBDDMYSQL);
        $cnx = new PDO($conStr, SYS_USERMYSQL, SYS_PASSMYSQL, $options);
        $sql = "SELECT ti.ip_tienda, COALESCE(ti.instancia, 'n.a') AS instancia, prefix, number
                FROM documents AS doc
                INNER JOIN prefijos_ti pre ON (pre.prefijo_f = doc.prefix OR pre.prefijo_d = doc.prefix)
                INNER JOIN mtienda_ip ti ON ti.id_tienda = pre.id_tienda
                GROUP BY doc.prefix, doc.number
                HAVING SUM(CASE WHEN doc.cufe IS NOT NULL THEN 1 ELSE 0 END) = 0
                ORDER BY 1, 3, 4";
        $docs = $cnx->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        echo 'Documentos pendientes: ', count($docs), "\r\n";
        if (count($docs) > 0) {
            $fichero = '/var/www/html/apidian/demmon/env_DocElePend2API.php';
            $nuevo_fichero = '/var/www/html/apidian/demmon/cpenv_DocElePend2API.php';
            if (!copy($fichero, $nuevo_fichero)) {
                die("Error al copiar $fichero...\n");
            }
        }
        foreach ($docs as $doc) {
            $cmd = 'php cpenv_DocElePend2API.php ' . $doc['instancia'] . ' ' . $doc['ip_tienda'] . ' ' . $doc['prefix'] . ' ' . $doc['number'];
            echo exec($cmd), "\r\n";
        }
        $cnx = null;
    } catch (PDOException $e) {
        echo "[" . __LINE__ . "] Error : ";
        print_r($e);
        die();
    }
}
