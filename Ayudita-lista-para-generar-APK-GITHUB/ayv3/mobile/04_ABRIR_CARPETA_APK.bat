@echo off
cd /d "%~dp0"
if not exist dist mkdir dist
explorer dist
