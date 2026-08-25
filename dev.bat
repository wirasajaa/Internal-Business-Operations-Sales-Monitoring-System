@echo off
rem ERP Project Dev Launcher - double-click entry point.
rem .bat files are not subject to PowerShell's script execution policy,
rem so this always runs regardless of "Run with PowerShell" quirks.
title ERP Dev Launcher
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0dev.ps1"
