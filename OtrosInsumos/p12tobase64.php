<?php
$archivoP12 = 'COMERCIALIZADORA MONTES DE COLOMBIA SAS.p12';

// 1. Leer el archivo .p12 en modo binario
$contenidoBinario = file_get_contents($archivoP12);

if ($contenidoBinario !== false) {
    // 2. Convertir el contenido binario a Base64
    $base64P12 = base64_encode($contenidoBinario);

    // Imprimir o guardar la cadena
    echo $base64P12;
} else {
    echo "Error al leer el archivo .p12";
}
