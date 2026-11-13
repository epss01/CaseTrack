@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\open-casetrack.ps1"
if errorlevel 1 pause
