:: Script para ejecutar demonios o script php
:: Parametro 1 debe ser el nombre del archivo .php que se va a ejecutar
:: Parametros 2 - 5 son opcionales
::              Parametro          %1         %2     %3
:: Ejemplo run_demmon.bat env_DocElecPend2API n.a localhost
@echo off
set demmon=%1
set logfile=%date:~0,3%.log
:: Eliminar archivos log viejos 7 días antes del archivo que se ejecuta
forfiles /m *%demmon%*.log /d -7 /c "cmd /c del /q @path" 2>nul
:: Ejecutar el php indicado con los parametros indicados
php %demmon%.php %2 %3 %4 %5 >> %demmon%_%logfile%
