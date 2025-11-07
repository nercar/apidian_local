:: Script para ejecutar demonios o script php
:: Parametro 1 debe ser el nombre del archivo .php que se va a ejecutar
:: Parametros 2 - 5 son opcionales se indican si son necesarios
:: Ejemplo run_demmon.bat env_DocElecPend2API n.a localhost
@echo off
set demmon=%1
set logfile=%date:~0,3%.log
php %demmon%.php %2 %3 %4 %5 >> %demmon%_%logfile%
