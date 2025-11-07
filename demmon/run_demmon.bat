:: Script para ejecutar demonios o script php
:: Parametro 1 debe ser el nombre del archivo .php que se va a ejecutar
:: Parametros 2 - 5 son opcionales
::              Parametro          %1         %2     %3
:: Ejemplo run_demmon.bat env_DocElecPend2API n.a localhost
@echo off
set demmon=%1
set logfile=%date:~0,3%.log
:: Eliminar archivos log viejos del archivo que se ejecuta
:: forfiles /p . /s /m %demmon%_%logfile% /d -1 /c "cmd /c del @path"
forfiles /p . /s /m *%demmon%_%logfile% /d -1 /c "cmd /c echo @path" >> log.txt
:: Ejecutar el php indicado con los parametros indicados
php %demmon%.php %2 %3 %4 %5 >> %demmon%_%logfile%
