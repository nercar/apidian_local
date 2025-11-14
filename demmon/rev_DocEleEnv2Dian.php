<?php
if ($argc >= 1) {
    $demonio = $argv[0];
    $instan = strtolower($argv[1]);
    $ipserv = strtolower($argv[2]);
    $output = [];
    $correr = false;
    if (PHP_OS == 'Linux') {
        $strrun = trim($demonio . ' ' . $instan . ' ' . $ipserv);
        $execstring = "ps aux | grep -v grep | grep '$strrun'";
        exec($execstring, $output);
        $correr = (count($output) <= 1);
    } else {
        $strrun = trim($demonio . '.php ' . $instan . ' ' . $ipserv);
        $retval = null;
        exec('tasklist /V | findstr /l "' . $strrun . '"', $output, $retval);
        $correr = (count($output) <= 2);
    }
    if ($correr) {
        /**
         * Permite obtener los datos de la base de datos y retornarlos
         * en modo json o array
         */
        try {
            if ($argc < 3) {
                die('Falta la informacion del servidor IP y/o instancia');
            }
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
            $iptienda = $ipserv;
            $ipserv .= $instan == 'n.a' ? '' : chr(92) . $instan;
            $conSQLLoc = CxSQLSUCURSAL::ConectSQL($ipserv);
            if ($conSQLLoc !== false) {
                $sql = "USE BDES_POS;
                    IF NOT EXISTS(SELECT 1 FROM sys.columns WHERE Name = N'cufe_verificado' AND Object_ID = Object_ID(N'dbo.factura_electronica'))
                    BEGIN
                        EXEC sys.sp_executesql N'ALTER TABLE dbo.factura_electronica ADD cufe_verificado int DEFAULT 0 NOT NULL';
                        EXEC sys.sp_executesql N'UPDATE dbo.factura_electronica SET cufe_verificado = 1 WHERE created_at < ''2025-01-01''';
                    END";
                $res = sqlsrv_query($conSQLLoc, $sql);
                if ($res == false) {
                    echo __LINE__, 'Error creando la columna cufe_verificado en factura electronica', "\r\n";
                    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
                    foreach ($errors as $error) {
                        echo "\r\n", 'ERRORsSQLSucursal: ', __LINE__, ' ', $ipserv, ' ', $error['message'], "\r\n", $sql;
                    }
                    exit;
                }
                $sql = "SELECT TOP 100 PERCENT id, prefijo, folio, cufe, CONVERT(VARCHAR(16), created_at, 121) AS fecha, cufe_verificado
                        FROM BDES_POS.dbo.factura_electronica
                        WHERE CAST(created_at AS DATE) >= CAST(GETDATE() AS DATE) AND (cufe_verificado < 2 OR COALESCE(cufe, '') = '')
                        ORDER BY created_at asc";
                $pend = sqlsrv_query($conSQLLoc, $sql);
                if ($pend === false) {
                    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
                    foreach ($errors as $error) {
                        echo "\r\n", 'ERRORsSQLSucursal: ', __LINE__, ' ', $ipserv, ' ', $error['message'], "\r\n", $sql;
                    }
                } else {
                    while ($row = sqlsrv_fetch_array($pend, SQLSRV_FETCH_ASSOC)) {
                        if (preg_replace("/[^0-9]/", "", $row['folio']) != $row['folio']) {
                            echo 'Procesando ', $row['fecha'], ' ', $row['prefijo'], '-', $row['folio'], ' ';
                            echo 'Cufe Verificado = 3 - Folio con caracteres no permitidos', "\r\n";
                            $sql = "UPDATE dbo.factura_electronica SET cufe_verificado = 3 WHERE id = " . $row['id'];
                            $res = sqlsrv_query($conSQLLoc, $sql);
                            if ($res == false) {
                                echo __LINE__, 'Error actualizando la columna cufe_verificado en factura electronica', "\r\n";
                                $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
                                foreach ($errors as $error) {
                                    echo "\r\n", 'ERRORsSQLSucursal: ', __LINE__, ' ', $ipserv, ' ', $error['message'], "\r\n", $sql;
                                }
                                exit;
                            }
                        } else {
                            if (PHP_OS == 'Linux') {
                                $cmd = "php /var/www/html/apidian/demmon/renv_DocEleRev2API.php $instan $iptienda " . $row['prefijo'] . ' ' . $row['folio'];
                                echo $cmd;
                                echo exec($cmd);
                            } else {
                                $dir = __DIR__ . DIRECTORY_SEPARATOR;
                                $cmd = "php " . $dir . "renv_DocEleRev2API.php $instan $iptienda " . $row['prefijo'] . ' ' . $row['folio'];
                                echo shell_exec($cmd);
                            }
                        }
                    }
                }
            }
            $conSQLLoc = null;
            $cnx = null;
        } catch (PDOException $e) {
            echo "[" . __LINE__ . "] Error : ";
            print_r($e);
            die();
        }
    }
} else {
    echo __LINE__, 'Debe ingresar la ip del servidor';
}
