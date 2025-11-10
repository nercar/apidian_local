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
            if ($argc > 1) {
                if ($argc == 5) {
                    $prefix = strtoupper($argv[3]);
                    $number = $argv[4];
                } else {
                    die('Falta la informacion del prefijo y folio');
                }
            } else {
                die('Falta la informacion del servidor y|o prefijo y folio');
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
            $ipserv .= $instan == 'n.a' ? '' : chr(92) . $instan;
            $conSQLLoc = CxSQLSUCURSAL::ConectSQL($ipserv);
            // Se ejecuta el query en la tienda para actualizar el precio o insertar el articulo es ESARTICULOS
            if ($conSQLLoc !== false) getData($conSQLLoc, $ipserv, $cnx, $number, $prefix);
            $conSQLLoc = null;
            $cnx = null;
        } catch (PDOException $e) {
            echo "[" . __LINE__ . "] Error : ";
            print_r($e);
            die();
        }
    }
} else {
    echo 'Debe ingresar la ip del servidor';
}

function getData($conSQLLoc, $tienda, $cnx, $number, $prefix, $newtype = 0)
{
    echo date('Y.m.d H:i:s'), " Procesando [$prefix-$number] ";
    $sql   = "SELECT * FROM BDES_POS.dbo.fn_header_fe($number, '$prefix')";
    $heads = sqlsrv_query($conSQLLoc, $sql);
    if ($heads === false) {
        $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
        foreach ($errors as $error) {
            echo "\r\n", 'ERRORsSQLSucursal: ', __LINE__, ' ', $tienda, ' ', $error['message'], "\r\n", $sql;
        }
    } else {
        while ($row = sqlsrv_fetch_array($heads, SQLSRV_FETCH_ASSOC)) {
            echo " ... ";
            $jsObj = new stdClass();
            if ($row['type_document_id'] == 4 && $newtype == 0) {
                $jsObj->billing_reference = new stdClass();
                $jsObj->billing_reference->number = $row['br_number'];
                $jsObj->billing_reference->uuid = $row['br_uuid'];
                $jsObj->billing_reference->issue_date = $row['br_issue_date'];
                $jsObj->discrepancyresponsecode = 1;
                $jsObj->discrepancyresponsedescription = "Devolucion";
            }
            $jsObj->number = $number;
            $jsObj->type_document_id = $row['type_document_id'];
            if ($newtype != 0) {
                $jsObj->type_operation_id = 8;
                $jsObj->invoice_period = new stdClass();
                $jsObj->invoice_period->start_date = date('Y-m-01', strtotime($row['br_issue_date']));
                $jsObj->invoice_period->end_date = date('Y-m-t', strtotime($row['br_issue_date']));
            }
            $todayDate = date('Y-m-d');
            $dueDate = date("Y-m-d", strtotime($todayDate . "+ 30 days"));
            $sdf = $todayDate;
            $jsObj->date = $sdf;
            $jsObj->time = $row['time'];
            $jsObj->resolution_number = $row['resolution_number'];
            $jsObj->prefix = trim($row['prefix']);
            $jsObj->disable_confirmation_text = true;
            $jsObj->establishment_name = $row['establishment_name'];
            $jsObj->establishment_address = $row['establishment_address'];
            $jsObj->establishment_phone = $row['establishment_phone'];
            $jsObj->establishment_municipality = $row['establishment_municipality'];
            $emailclte = $cnx->query("SELECT email FROM users WHERE id = 1")->fetchAll(PDO::FETCH_ASSOC);
            $jsObj->establishment_email = $emailclte[0]['email'];
            $fnote = $cnx->query("SELECT fnote_pdf FROM companies WHERE user_id = 1")->fetchAll(PDO::FETCH_ASSOC);
            $jsObj->email_cc_list = array();
            $emailcclist = new stdClass();
            if (filter_var(trim($row['email']), FILTER_VALIDATE_EMAIL)) {
                $emailcclist->email = trim($row['email']);
            } else {
                $emailcclist->email = 'consumidorfinalsuperlosmontes@gmail.com';
            }
            array_push($jsObj->email_cc_list, $emailcclist);
            echo 'H ';
            $jsObj->customer = new stdClass();
            if ($row['identification_number'] == "222222222222") {
                $jsObj->customer->identification_number = "222222222222";
                $jsObj->customer->dv = 7;
                $jsObj->customer->name = "CONSUMIDOR FINAL";
                $jsObj->customer->merchant_registration = "0000000-00";
                $jsObj->customer->type_document_identification_id = 3;
                $jsObj->customer->type_organization_id = 2;
                $jsObj->customer->municipality_id = $row['establishment_municipality'];
                $jsObj->customer->type_regime_id = 2;
                $jsObj->customer->type_liability_id = 117;
            } else {
                if (filter_var(trim($row['email']), FILTER_VALIDATE_EMAIL)) {
                    $jsObj->customer->email = trim($row['email']);
                } else {
                    $jsObj->customer->email = 'consumidorfinalsuperlosmontes@gmail.com';
                }
                $jsObj->customer->identification_number = $row['identification_number'];
                $jsObj->customer->dv = digitoVer(trim($row['identification_number']));
                $jsObj->customer->name = mb_convert_encoding($row['name'], mb_detect_encoding($row['name']), 'UTF-8');
                $jsObj->customer->phone = mb_convert_encoding($row['phone'], mb_detect_encoding($row['phone']), 'UTF-8');
                $jsObj->customer->address = mb_convert_encoding($row['addess'], mb_detect_encoding($row['addess']), 'UTF-8');
                $jsObj->customer->merchant_registration = "0000000-00";
                $jsObj->customer->type_organization_id = $row['type_organization_id'];
                $jsObj->customer->type_liability_id = $row['type_liability_id'];
                $jsObj->customer->municipality_id = $row['type_document_id'] == 1 ? $row['municipality_id'] : $row['establishment_municipality'];
                $jsObj->customer->type_regime_id = $row['type_regime_id'];
                $jsObj->customer->type_document_identification_id = $row['type_document_identification_id'];
            }
            echo 'C ';
            $jsObj->payment_form = new stdClass();
            $sql   = "SELECT * FROM BDES_POS.dbo.fn_payment_fe($number, '$prefix')";
            $form_pay = sqlsrv_query($conSQLLoc, $sql);
            $fpnote = '';
            if ($form_pay === false) {
                $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
                foreach ($errors as $error) {
                    echo "\r\n", 'ERRORsSQLSucursal: ', __LINE__, ' ', $tienda, ' ', $error['message'], "\r\n", $sql;
                }
            } else {
                $fpnote = '[ Medios de Pago: ';
                $mntpay = 0;
                while ($rfp = sqlsrv_fetch_array($form_pay, SQLSRV_FETCH_ASSOC)) {
                    $fpnote .= '(' . $rfp['forma_pago'] . ')';
                    if ($rfp['payment_form_id'] == 2) {
                        $jsObj->payment_form->payment_form_id = $rfp['payment_form_id'];
                        $jsObj->payment_form->payment_method_id = $rfp['payment_means_code'];
                        $jsObj->payment_form->payment_due_date = $dueDate;
                        $jsObj->payment_form->duration_measure = 30;
                    } else {
                        if ($mntpay < ($rfp['payment_amount'])) {
                            $mntpay = $rfp['payment_amount'];
                            $jsObj->payment_form->payment_form_id = $rfp['payment_form_id'];
                            $jsObj->payment_form->payment_method_id = $rfp['payment_means_code'];
                        }
                        $jsObj->payment_form->payment_due_date = $sdf;
                        $jsObj->payment_form->duration_measure = 0;
                    }
                }
                $fpnote .= ' ]';
            }
            $jsObj->notes  = $fnote[0]['fnote_pdf'];
            $jsObj->notes .= str_repeat('~', 5) . '[ ';
            $jsObj->notes .= mb_convert_encoding($row['foot_note'], mb_detect_encoding($row['foot_note']), 'UTF-8');
            $jsObj->notes .= ' ]' . str_repeat('~', 5);
            $jsObj->notes .= $fpnote;
            echo 'P ';
            $sql     = "SELECT * FROM BDES_POS.dbo.fn_detail_fe(?, ?)";
            $datos   = array($number, $prefix);
            $details = sqlsrv_query($conSQLLoc, $sql, $datos);
            if ($details === false) {
                $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
                foreach ($errors as $error) {
                    echo "\r\n", 'ERRORsSQLSucursal: ', __LINE__, ' ', $tienda, ' ', $error['message'], "\r\n", $sql;
                }
            } else {
                $lin = 0;
                if ($row['type_document_id'] == 1) {
                    $jsObj->invoice_lines = array();
                } else {
                    $jsObj->credit_note_lines = array();
                }
                echo 'D ';
                while ($rowDet = sqlsrv_fetch_array($details, SQLSRV_FETCH_ASSOC)) {
                    $detalleFactura = new stdClass();
                    $detalleFactura->unit_measure_id = $rowDet['unit_measure_id'];
                    $detalleFactura->invoiced_quantity = $rowDet['invoiced_quantity'];
                    $detalleFactura->line_extension_amount = $rowDet['line_extension_amount'];
                    $detalleFactura->free_of_charge_indicator = $rowDet['price_amount'] == 0;
                    $detalleFactura->description = mb_convert_encoding($rowDet['description'], mb_detect_encoding($rowDet['description']), 'UTF-8');
                    $detalleFactura->notes = "";
                    $detalleFactura->code = $rowDet['code'];
                    $detalleFactura->type_item_identification_id = 4;
                    $detalleFactura->price_amount = $rowDet['price_amount'];
                    $detalleFactura->base_quantity = $rowDet['base_quantity'];
                    if ($rowDet['price_amount'] == 0) {
                        $detalleFactura->reference_price_id = 1;
                    }
                    $sql   = "SELECT * FROM BDES_POS.dbo.fn_taxes_fe(?, ?) WHERE code = ?";
                    $datos = array($number, $prefix, $rowDet['code']);
                    $taxes = sqlsrv_query($conSQLLoc, $sql, $datos);
                    if ($taxes === false) {
                        $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
                        foreach ($errors as $error) {
                            echo "\r\n", 'ERRORsSQLSucursal: ', __LINE__, ' ', $tienda, ' ', $error['message'], "\r\n", $sql;
                        }
                    } else {
                        $linimp = 0;
                        $detalleFactura->tax_totals = array();
                        while ($rowImp = sqlsrv_fetch_array($taxes, SQLSRV_FETCH_ASSOC)) {
                            if (!in_array($rowImp['tax_id'], [22, 21, 19])) {
                                $impuesto = new stdClass();
                                $impuesto->tax_id = $rowImp['tax_id'];
                                if ($rowImp['tax_id'] == 10) {
                                    $impuesto->unit_measure_id = "70";
                                    $impuesto->base_unit_measure = $rowDet['invoiced_quantity'];
                                }
                                $impuesto->tax_amount = round($rowImp['tax_amount'], 2);
                                $impuesto->taxable_amount = round($rowImp['taxable_amount'], 2);
                                $impuesto->percent = $rowImp['porcentaje'];
                                $impuesto->per_unit_amount = $rowImp['per_unit_amount'];
                                array_push($detalleFactura->tax_totals, $impuesto);
                                $linimp++;
                            }
                        }
                    }
                    if ($row['type_document_id'] == 1) {
                        array_push($jsObj->invoice_lines, $detalleFactura);
                    } else {
                        array_push($jsObj->credit_note_lines, $detalleFactura);
                    }
                    $lin++;
                }
                echo 'L:', $lin, ' ';
            }
            $sql = "SELECT tax_id, porcentaje, per_unit_amount, SUM(tax_amount) AS tax_amount,
                        SUM(taxable_amount) AS taxable_amount, SUM(base_unit_measure) AS base_unit_measure
                    FROM BDES_POS.dbo.fn_taxes_fe(?, ?) GROUP BY tax_id, porcentaje, per_unit_amount";
            $datos = array($number, $prefix);
            $taxes = sqlsrv_query($conSQLLoc, $sql, $datos);
            if ($taxes === false) {
                $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
                foreach ($errors as $error) {
                    echo "\r\n", 'ERRORsSQLSucursal: ', __LINE__, ' ', $tienda, ' ', $error['message'], "\r\n", $sql;
                }
            } else {
                echo 'T ';
                $lin = 0;
                $descontar = 0;
                $recargo = 0;
                $jsObj->tax_totals = array();
                $jsObj->allowance_charges = array();
                while ($rowGrpImp = sqlsrv_fetch_array($taxes, SQLSRV_FETCH_ASSOC)) {
                    if (!in_array($rowGrpImp['tax_id'], [22, 21, 19])) {
                        $impuestoTotal = new stdClass();
                        $impuestoTotal->tax_id = $rowGrpImp['tax_id'];
                        $impuestoTotal->base_unit_measure = $rowGrpImp['base_unit_measure'];
                        if ($rowGrpImp['tax_id'] == 10) {
                            $impuestoTotal->unit_measure_id = "70";
                            $impuestoTotal->base_unit_measure = round($rowGrpImp['tax_amount'] / $rowGrpImp['per_unit_amount']);
                            $descontar += $rowGrpImp['tax_amount'];
                        }
                        $impuestoTotal->tax_amount = round($rowGrpImp['tax_amount'], 2);
                        $impuestoTotal->percent = $rowGrpImp['porcentaje'];
                        $impuestoTotal->taxable_amount = round($rowGrpImp['taxable_amount'], 2);
                        $impuestoTotal->per_unit_amount = round($rowGrpImp['per_unit_amount']);
                        array_push($jsObj->tax_totals, $impuestoTotal);
                        $lin++;
                    }
                    if (in_array($rowGrpImp['tax_id'], [22, 21, 19])) {
                        $recargo += $rowGrpImp['tax_amount'];
                    }
                }
                echo ' L:', $lin, ' R ';
                if ($recargo > 0) {
                    $jsrec = new stdClass();
                    $jsrec->charge_indicator = true;
                    $jsrec->allowance_charge_reason = "INGR";
                    $jsrec->amount = $recargo;
                    if ($recargo > ($row['line_extension_amount'] - $descontar - $recargo)) {
                        $jsrec->base_amount = round($row['line_extension_amount'] - $descontar, 2);
                    } else {
                        $jsrec->base_amount = round($row['line_extension_amount'] - $descontar - $recargo, 2);
                    }
                    array_push($jsObj->allowance_charges, $jsrec);
                }
                $recargo = round($recargo, 2);
                echo ' T ';
                $jsObj->legal_monetary_totals = new stdClass();
                $jsObj->legal_monetary_totals->line_extension_amount = round($row['line_extension_amount'] - $descontar - $recargo, 2);
                $jsObj->legal_monetary_totals->tax_exclusive_amount = round($row['tax_exclusive_amount'] - $descontar - $recargo, 2);
                $jsObj->legal_monetary_totals->tax_inclusive_amount = round($row['tax_inclusive_amount'] - $recargo, 2);
                if ($recargo > 0) {
                    $jsObj->legal_monetary_totals->charge_total_amount = round($recargo, 2);
                }
                $jsObj->legal_monetary_totals->payable_amount = round($jsObj->legal_monetary_totals->tax_inclusive_amount + $recargo, 2);
            }
            envJson2Api($conSQLLoc, $tienda, $cnx, $number, $prefix, $jsObj, $row['type_document_id'], $row['identification_number']);
            echo "\r\n";
        }
    }
}

function envJson2Api($conSQLLoc, $tienda, $cnx, $number, $prefix, $jsObj, $type_document_id, $identification_number)
{
    $curl = curl_init();
    $url  = "http://" . SYS_HOSTMYSQL . "/apidian/public/api/ubl2.1/";
    $url .= ($type_document_id == 1) ? 'invoice' : 'credit-note';
    echo " Enviando [$prefix-$number] -> ";
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($jsObj),
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
        echo __LINE__, " cURL Error #: " . $err;
    } else {
        $response = json_decode($response);
        $valido = false;
        $xmldocumentkey = '';
        if ($response->message == 'Este documento ya fue enviado anteriormente, se registra en la base de datos.') {
            $valido = true;
        } else if ($response->message == 'The given data was invalid.') {
            $valido = false;
        } else {
            if (isset($response->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->IsValid)) {
                $valido = $response->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->IsValid == 'true';
            }
            if (!$valido) {
                $regla90 = json_encode($response->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->ErrorMessage);
                if (stripos($regla90, 'Regla: 90, Rechazo: Documento procesado anteriormente.')) {
                    $xmldocumentkey = $response->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlDocumentKey;
                    $valido = true;
                }
            }
            if (!$valido) {
                $regla89 = json_encode($response->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->StatusMessage);
                if (stripos($regla89, 'El m\u00e9todo s\u00edncrono solo puede recibir un documento.')) {
                    $valido = true;
                }
            }
        }
        if ($valido) {
            echo ' Valido -> ';
            $cufe = ($type_document_id == 1) ? $response->cufe : $response->cude;
            if ($xmldocumentkey != $cufe && $xmldocumentkey != '') {
                $cufe = $xmldocumentkey;
                echo 'CD -> ';
            }
            $sql = "MERGE BDES_POS.dbo.factura_electronica AS fe USING (VALUES ('$prefix', '$number', '$cufe')) AS vn(prefijo, folio, cufe)
                    ON fe.prefijo = vn.prefijo AND fe.folio = vn.folio
                    WHEN MATCHED THEN UPDATE SET cufe = vn.cufe, cufe_verificado = 2
                    WHEN NOT MATCHED THEN INSERT (prefijo, folio, cufe, cufe_verificado) VALUES (vn.prefijo, vn.folio, vn.cufe, 2);";
            $rows = sqlsrv_query($conSQLLoc, $sql);
            if ($rows === false) {
                $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
                foreach ($errors as $error) {
                    echo "\r\n", 'ERRORsSQLSucursal: ', __LINE__, ' ', $tienda, ' ', $error['message'], "\r\n", $sql;
                }
            }
            $idfac = $cnx->query("SELECT MAX(ID) idfac FROM documents WHERE prefix = '$prefix' AND number = '$number'")->fetchAll(PDO::FETCH_ASSOC);
            $idfac = $idfac[0]['idfac'];
            $cnx->query("UPDATE documents SET state_document_id = 1, cufe = '$cufe', updated_at = CURRENT_TIMESTAMP
                        WHERE prefix = '$prefix' AND number = '$number' AND id = $idfac");
            if ($identification_number != "222222222222") {
                $url  = "http://" . SYS_HOSTMYSQL . "/apidian/public/api/ubl2.1/send-email";
                echo " Mail [$prefix-$number] -> ";
                $jsObj = array(
                    "prefix" => "$prefix",
                    "number" => "$number",
                    "showacceptrejectbuttons" => false,
                    "email_cc_list" => [],
                    "base64graphicrepresentation" => ""
                );
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => json_encode($jsObj),
                    CURLOPT_HTTPHEADER => [
                        "Authorization: Bearer 04cde66691dad4b7aea1729558a0c6f6a1f281ad687c6c92e7d5e32f7f445c0d",
                        "Content-Type: application/json",
                        "accept: application/json"
                    ],
                ]);
                $response = curl_exec($curl);
                $err = curl_error($curl);
                curl_close($curl);
                if ($err) echo "cURL Error #:" . $err, "\r\n";
            }
            echo $cufe;
        } else {
            if (
                !isset($response->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->ErrorMessage) &&
                !isset($response->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->StatusMessage)
            ) {
                echo __LINE__, ' ', substr(json_encode($response), 0, 300), "\r\n";
                echo json_encode($jsObj), "\r\n";
            } else if (!isset($response->ResponseDian)) {
                echo __LINE__, ' ', substr(json_encode($response), 0, 300), "\r\n";
            } else {
                $reglaLGC15 = json_encode($response->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->ErrorMessage);
                if (stripos($reglaLGC15, 'Regla: LGC15, Rechazo:')) {
                    getData($conSQLLoc, $tienda, $cnx, $number, $prefix, 4);
                } else {
                    echo __LINE__, ' ', json_encode($response->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->ErrorMessage), "\r\n";
                    echo __LINE__, ' ', json_encode($response->ResponseDian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->StatusMessage);
                }
            }
        }
    }
}

function digitoVer($nitParam)
{
    if ($nitParam == null || trim($nitParam) == '') return -1;
    $nitParam = trim($nitParam);
    $indiceRaya = strpos($nitParam, '-');
    $nitInterno = $indiceRaya > 0 ? substr($nitParam, 0, $indiceRaya) : $nitParam;
    if (!is_numeric($nitInterno)) return -1;
    $nitVector = str_split($nitInterno);
    $valorCalculado = 0;
    $aux = count($nitVector) - 1;
    for ($i = 0; $i < count($nitVector); $i++) {
        switch ($i) {
            case 0:
                $valorCalculado += 3 * intval($nitVector[$aux - 0]);
                break;
            case 1:
                $valorCalculado += 7 * intval($nitVector[$aux - 1]);
                break;
            case 2:
                $valorCalculado += 13 * intval($nitVector[$aux - 2]);
                break;
            case 3:
                $valorCalculado += 17 * intval($nitVector[$aux - 3]);
                break;
            case 4:
                $valorCalculado += 19 * intval($nitVector[$aux - 4]);
                break;
            case 5:
                $valorCalculado += 23 * intval($nitVector[$aux - 5]);
                break;
            case 6:
                $valorCalculado += 29 * intval($nitVector[$aux - 6]);
                break;
            case 7:
                $valorCalculado += 37 * intval($nitVector[$aux - 7]);
                break;
            case 8:
                $valorCalculado += 41 * intval($nitVector[$aux - 8]);
                break;
            case 9:
                $valorCalculado += 43 * intval($nitVector[$aux - 9]);
                break;
            case 10:
                $valorCalculado += 47 * intval($nitVector[$aux - 10]);
                break;
            case 11:
                $valorCalculado += 53 * intval($nitVector[$aux - 11]);
                break;
            case 12:
                $valorCalculado += 59 * intval($nitVector[$aux - 12]);
                break;
            case 13:
                $valorCalculado += 67 * intval($nitVector[$aux - 13]);
                break;
            case 14:
                $valorCalculado += 71 * intval($nitVector[$aux - 14]);
                break;
        }
    }
    $modulo = $valorCalculado % 11;
    if ($modulo >= 2) $modulo = 11 - $modulo;
    return $modulo;
}
