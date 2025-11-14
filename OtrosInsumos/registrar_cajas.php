<?php
date_default_timezone_set('America/Bogota');
// Primeros pasos para registrar datos para la ApiDian
echo str_repeat('=', 30), "\r\n";
echo "Registrar resoluciones de cajas\r\n";
echo "Debe existir el archivo cajas.json\r\n";
echo "con la información de las resoluciones\r\n";
echo "======================================\r\n";
echo "- Ingrese Opcion del sitio -\r\n";
echo "1. localhost\r\n";
echo "2. localhost/apidian/public\r\n";
$site = readline("Ingrese la opción: ");
switch ($site) {
    case 1:
        $site = "localhost";
        break;
    case 2:
        $site = "localhost/apidian/public";
        break;
    default:
        $site = "localhost";
        break;
}
$url = "http://$site/api/ubl2.1/config/resolution";
// Se establece la conexion con la BBDD
$json = file_get_contents('cajas.json');
if ($json === false) {
    throw new \Exception("Error leyendo archivo .json");
}
$datos = json_decode($json, true);
if (count($datos) == 0) {
    echo "Debe agrgar la información de las\r\nresoluciones al archivo cajas.json";
} else {
    foreach ($datos as $row) {
        echo "Registrando " . $row['Prefix'];
        $jsObj = new stdClass();
        $jsObj->type_document_id = 1;
        $jsObj->prefix = $row['Prefix'];
        $jsObj->resolution = $row['ResolutionNumber'];
        $jsObj->resolution_date = $row['ResolutionDate'];
        $jsObj->technical_key = $row['TechnicalKey'];
        $jsObj->from = $row['FromNumber'];
        $jsObj->to = $row['ToNumber'];
        $jsObj->generated_to_date = "0";
        $jsObj->date_from = $row['ValidDateFrom'];
        $jsObj->date_to = $row['ValidDateTo'];
        envJson2Api($jsObj, $url);
        $prenc = substr_replace($row['Prefix'], "D", 0, 1);
        echo " - " . $prenc, "\r\n";
        $jsObj = new stdClass();
        $jsObj->type_document_id = 4;
        $jsObj->from = $row['FromNumber'];
        $jsObj->to = 99999999;
        $jsObj->prefix = $prenc;
        $jsObj->resolution = $row['ResolutionNumber'];
        envJson2Api($jsObj, $url);
        echo str_repeat('=', 30), "\r\n";
    }
}

// Funcion de enviar la informacion a la APIDian
function envJson2Api($jsObj, $url)
{
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "PUT",
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
    if ($err) echo __LINE__, " cURL Error #: ", $err, "\r\n";
    else echo __LINE__, json_encode($response), "\r\n";
    echo "\r\n";
}
