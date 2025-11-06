<?php
if ($argc >= 1) {
    $demonio = $argv[0];
    $instan = strtolower($argv[1]);
    $ipserv = strtolower($argv[2]);
    $strrun = trim($demonio . '.php ' . $instan . ' ' . $ipserv);
    $output = [];
    $retval = null;
    exec('tasklist /V | findstr /l "' . $strrun . '"', $output, $retval);
    if (count($output) <= 2) {
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
            DEFINE("SYS_HOSTMYSQL", $ipserv);
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
                        echo 'Procesando ', $row['fecha'], '-', $row['prefijo'], '-', $row['folio'], ' ';
                        if (preg_replace("/[^0-9]/", "", $row['folio']) != $row['folio']) {
                            echo 'Cufe Verificado = 3 ';
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
                        } else if (valCufeDian($row['cufe'], $row['prefijo'], $row['folio'])) {
                            echo 'Cufe Verificado = 2 ';
                            $sql = "UPDATE dbo.factura_electronica SET cufe_verificado = 2 WHERE id = " . $row['id'];
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
                            echo 'Renviando ';
                            $dir = __DIR__ . DIRECTORY_SEPARATOR;
                            $cmd = "php " . $dir . "renv_DocEleRev2API.php $instan $iptienda " . $row['prefijo'] . ' ' . $row['folio'];
                            echo shell_exec($cmd);
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

function valCufeDian($cufe, $prefijo, $folio)
{
    if ($cufe == '') return false;
    echo 'Validando cufe ', $cufe;
    $curl = curl_init();
    $url  = "http://" . SYS_HOSTMYSQL . "/apidian/public/api/ubl2.1/xml/document/$cufe";
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "Authorization: Bearer 04cde66691dad4b7aea1729558a0c6f6a1f281ad687c6c92e7d5e32f7f445c0d",
            "Content-Type: application/json"
        ],
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
        echo __LINE__, " cURL Error #: " . $err, "\r\n";
        return false;
    } else {
        $response = json_decode($response);
        $xmlqrc = base64_decode($response->ResponseDian->Envelope->Body->GetXmlByDocumentKeyResponse->GetXmlByDocumentKeyResult->XmlBytesBase64);
        $xmlqrc = str_replace("<ext:", "<ext_", $xmlqrc);
        $xmlqrc = str_replace("</ext:", "</ext_", $xmlqrc);
        $xmlqrc = str_replace("<sts:", "<sts_", $xmlqrc);
        $xmlqrc = str_replace("</sts:", "</sts_", $xmlqrc);
        $xmlqrc = simplexml_load_string($xmlqrc);
        $xmlqrc = (array) $xmlqrc;
        $qrcode = $xmlqrc['ext_UBLExtensions']->ext_UBLExtension[0]->ext_ExtensionContent->sts_DianExtensions->sts_QRCode[0];
        $qrcode = $qrcode[0];
        $ini = strpos($qrcode, 'NumFac: ') + 8;
        $fin = strpos($qrcode, 'FecFac: ') - 1;
        $len = $fin - $ini;
        $qrcode = substr($qrcode, $ini, $len);
        if ($qrcode != ($prefijo . $folio)) return false;
        else return $response->success;
    }
}
