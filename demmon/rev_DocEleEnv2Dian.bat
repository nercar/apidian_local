@echo off
set demon=rev_DocEleEnv2Dian
set logfile=%date:~0,3%.log
php %demon%.php n.a localhost >> %demon%_%logfile%
