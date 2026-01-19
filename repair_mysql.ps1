$ErrorActionPreference = "Stop"

Write-Host "--- XAMPP MySQL Auto-Repair Tool ---" -ForegroundColor Cyan
Write-Host "This script will attempt to repair your corrupted MySQL data folder."

$mysqlPath = "C:\xampp\mysql"
$dataPath = "$mysqlPath\data"
$backupPath = "$mysqlPath\backup"
$corruptPath = "$mysqlPath\data_corrupt_" + (Get-Date -Format "yyyyMMdd_HHmmss")

if (!(Test-Path $dataPath)) {
    Write-Error "Data folder not found at $dataPath"
    exit
}

# 1. Stop MySQL (just in case)
Write-Host "1. Ensuring MySQL is stopped..."
Stop-Service mysql -ErrorAction SilentlyContinue
taskkill /F /IM mysqld.exe /T 2>$null

# 2. Rename current data folder
Write-Host "2. Backing up current corrupted data to: $corruptPath"
Rename-Item -Path $dataPath -NewName $corruptPath

# 3. Create new data folder and copy from backup
Write-Host "3. Restoring default files from XAMPP backup..."
New-Item -ItemType Directory -Path $dataPath | Out-Null
Copy-Item -Path "$backupPath\*" -Destination $dataPath -Recurse -Force

# 4. Restore User Database "fiora_db"
Write-Host "4. Restoring 'fiora_db'..."
if (Test-Path "$corruptPath\fiora_db") {
    Copy-Item -Path "$corruptPath\fiora_db" -Destination $dataPath -Recurse -Force
} else {
    Write-Warning "Could not find 'fiora_db' in the corrupted folder!"
}

# 5. Restore System Databases (mysql, performance_schema, phpmyadmin)
Write-Host "5. Restoring system databases (mysql, phpmyadmin)..."
Copy-Item -Path "$corruptPath\mysql" -Destination $dataPath -Recurse -Force
Copy-Item -Path "$corruptPath\performance_schema" -Destination $dataPath -Recurse -Force
Copy-Item -Path "$corruptPath\phpmyadmin" -Destination $dataPath -Recurse -Force

# 6. Restore ibdata1 (CRITICAL)
Write-Host "6. Restoring ibdata1 (main database file)..."
Copy-Item -Path "$corruptPath\ibdata1" -Destination $dataPath -Force

Write-Host "--- REPAIR COMPLETE ---" -ForegroundColor Green
Write-Host "Please open XAMPP Control Panel and click START on MySQL."
