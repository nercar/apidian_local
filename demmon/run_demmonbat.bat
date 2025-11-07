@echo off
set demmon=%1
set logfile=%date:~0,3%.log
php %demmon%.php n.a localhost >> %demmon%_%logfile%
