:: Script para ejecutar demonios o script php
:: Parametro 1 debe ser el nombre del archivo .php que se va a ejecutar
:: Parametros 2 - 5 son opcionales
::              Parametro          %1         %2     %3
:: Ejemplo run_demmon.bat env_DocElecPend2API n.a localhost
@echo off
setlocal 
set demmon=%1
for /f "skip=8 tokens=2,3,4,5,6,7,8 delims=: " %%D in ('robocopy /l * \ \ /ns /nc /ndl /nfl /np /njh /XF * /XD *') do (
 set "dow=%%D"
)
set logfile=%dow:~0,3%.log
:: Eliminar archivos log viejos 7 días antes del archivo que se ejecuta
forfiles /m *%demmon%*.log /d -7 /c "cmd /c del /q @path"
:: Ejecutar el php indicado con los parametros indicados
php %demmon%.php %2 %3 %4 %5 >> %demmon%_%logfile%
endlocal