@echo off
setlocal EnableExtensions EnableDelayedExpansion

REM Minimal sqlite3 shim for Windows dev machines without sqlite3.exe.
REM Supports the Laravel schema-load path: sqlite3 <dbPath> < schema.sql
REM (stdin contains the SQL). For other sqlite3 usages (e.g. .schema/.dump),
REM please install the official sqlite3 CLI.

set "DB_PATH=%~1"

if "%DB_PATH%"=="" (
  echo Usage: sqlite3.bat ^<dbPath^> 1>&2
  exit /b 2
)

shift
if not "%~1"=="" (
  echo sqlite3 shim: unsupported arguments. Install sqlite3 CLI for this command. 1>&2
  exit /b 2
)

php tests\e2e\sqlite3-stdin-shim.php "%DB_PATH%"
exit /b %ERRORLEVEL%
