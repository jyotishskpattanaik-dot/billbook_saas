@echo off
echo ===========================================
echo   🚀 Updating GitHub Repository - BillBook
echo ===========================================
cd /d "%~dp0"

:: Add all changed files
git add .

:: Commit with timestamp
set dt=%date% %time%
git commit -m "Auto update: %dt%"

:: Pull latest updates to avoid conflicts
git pull origin main

:: Push to your GitHub repo
git push https://github.com/jyotishskpattanaik-dot/billbook_saas.git main

echo -------------------------------------------
echo ✅ GitHub Updated Successfully!
pause
