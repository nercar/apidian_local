<?php
try {
    date_default_timezone_set('America/Bogota');
    // Conexion con la sucursal
    DEFINE("SYS_BBDD__SQL", "BDES_POS");
    DEFINE("SYS_PORT__SQL", "1433");
    DEFINE("SYS_USER__SQL", "sa");
    DEFINE("SYS_PASS__SQL", "");
    /* CONEXION CON MYSQL */
    DEFINE("SYS_ENGINEMYSQL", "mysql");
    DEFINE("SYS_HOSTMYSQL", "localhost");
    DEFINE("SYS_BBDDMYSQL", "apidian");
    DEFINE("SYS_USERMYSQL", "apidian");
    DEFINE("SYS_PASSMYSQL", "ApiDIAN2024@@");
    DEFINE("SYS_PORTMYSQL", "3306");
    class CxSQLSUCURSAL
    {
        function __construct() {}
        static function ConectSQL($servidor)
        {
            $conStrSqlLOC = array("Database" => SYS_BBDD__SQL, "UID" => SYS_USER__SQL, "PWD" => SYS_PASS__SQL, "ConnectRetryCount" => 5);
            $baseSqlLOC = sqlsrv_connect($servidor, $conStrSqlLOC);
            if ($baseSqlLOC === false) {
                $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
                foreach ($errors as $error) {
                    echo __LINE__, " ERRORsSQLSucursal: [", date("d-m-Y h:i:s A"), "]  ", json_encode($error['message']), "\r\n";
                }
            }
            return $baseSqlLOC;
        }
    }
    $options = [
        PDO::ATTR_EMULATE_PREPARES   => false, // Disable emulation mode for "real" prepared statements
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Disable errors in the form of exceptions
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Make the default fetch be an associative array
    ];
    $conStr = sprintf("mysql:host=%s;dbname=%s;charset=utf8mb4", SYS_HOSTMYSQL, SYS_BBDDMYSQL);
    $cnx = new PDO($conStr, SYS_USERMYSQL, SYS_PASSMYSQL, $options);
    $sql = "SELECT codigo, descripcion, id_tienda, ip_tienda, COALESCE(instancia, 'n.a') AS instancia FROM mtienda_ip WHERE activa = 1 ORDER BY codigo";
    $tiendas = $cnx->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tiendas as $tienda) {
        echo getData($tienda, $cnx);
    }
    // getData($ipserv, $cnx, $number, $prefix);
    $cnx = null;
} catch (PDOException $e) {
    echo "[" . __LINE__ . "] Error : ";
    print_r($e);
    die();
}

function getData($tienda, $cnx)
{
    $ipserv = $tienda['ip_tienda'] . ($tienda['instancia'] == 'n.a' ? '' : chr(92) . $tienda['instancia']);
    $conSQLLoc = CxSQLSUCURSAL::ConectSQL($ipserv);
    // Se ejecuta el query en la tienda para actualizar el precio o insertar el articulo es ESARTICULOS
    if ($conSQLLoc !== false) {
        // connect to the postgresql database
        $sql   = "SELECT PREFIJO_E, PREFIJOD_E FROM BDES_POS.dbo.ESCAJAS_DIAN WHERE PREFIJO_E LIKE 'F%' AND COALESCE(PREFIJO_E, '') != '' AND COALESCE(PREFIJOD_E, '') != ''";
        $datos = array();
        $cajas = sqlsrv_query($conSQLLoc, $sql, $datos);
        if ($cajas === false) {
            $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
            foreach ($errors as $error) {
                echo "\r\n", 'ERRORsSQLSucursal: ', __LINE__, ' ', $ipserv, ' ', $error['message'], "\r\n", $sql;
            }
        } else {
            while ($row = sqlsrv_fetch_array($cajas, SQLSRV_FETCH_ASSOC)) {
                $prefijo_f = trim($row['PREFIJO_E']);
                $prefijo_d = trim($row['PREFIJOD_E']);
                $id_tienda = $tienda['id_tienda'];
                $updated   = date('Y-m-d H:i:s');
                $cnx->query("INSERT INTO prefijos_ti(id_tienda, prefijo_f, prefijo_d, updated_at)
                            VALUES ($id_tienda, '$prefijo_f', '$prefijo_d', '$updated')
                            ON DUPLICATE KEY UPDATE prefijo_f = '$prefijo_f', prefijo_d = '$prefijo_d', updated_at = '$updated'");
                echo $tienda['codigo'], ' - ', $ipserv, ' - ', $tienda['descripcion'], ' - ', $prefijo_f, ' - ', $prefijo_d, "\r\n";
            }
        }
    } else {
        echo '#', $ipserv, '->', $tienda->descripcion;
    }
    // Se cierra la conexion
    $conSQLLoc = null;
}
