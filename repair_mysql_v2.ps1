
Write-Host "--- XAMPP MySQL Auto-Repair Tool V2 ---" -ForegroundColor Cyan

$mysqlPath = "C:\xampp\mysql"
$dataPath = "$mysqlPath\data"
$backupPath = "$mysqlPath\backup"
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$corruptPath = "$mysqlPath\data_corrupt_$timestamp"

if (!(Test-Path $dataPath)) {
    Write-Error "Data folder not found at $dataPath"
    exit
}

# 1. Stop MySQL
Write-Host "1. Ensuring MySQL is stopped..."
try {
    Stop-Service mysql -ErrorAction SilentlyContinue
    Stop-Process -Name "mysqld" -Force -ErrorAction SilentlyContinue
} catch {
    Write-Warning "Could not stop MySQL/mysqld (it might already be stopped)."
}

# 2. Rename current data folder
Write-Host "2. Backing up corrupted data to: $corruptPath"
try {
    Rename-Item -Path $dataPath -NewName $corruptPath -ErrorAction Stop
} catch {
    Write-Error "FAILED to rename data folder. Make sure MySQL is completely stopped and no files are open."
    exit
}

# 3. Create new data folder and copy from backup
Write-Host "3. Restoring default files from backup..."
try {
    New-Item -ItemType Directory -Path $dataPath | Out-Null
    Copy-Item -Path "$backupPath\*" -Destination $dataPath -Recurse -Force
} catch {
    Write-Error "FAILED to restore backup files."
    exit
}

# 4. Restore User Database "fiora_db"
Write-Host "4. Restoring 'fiora_db'..."
if (Test-Path "$corruptPath\fiora_db") {
    Copy-Item -Path "$corruptPath\fiora_db" -Destination $dataPath -Recurse -Force
} else {
    Write-Warning "Could not find 'fiora_db' in the corrupted folder!"
}

# 5. Restore System Databases
Write-Host "5. Restoring system databases (mysql)..."
Copy-Item -Path "$corruptPath\mysql" -Destination $dataPath -Recurse -Force

# 6. Restore ibdata1 (CRITICAL)
Write-Host "6. Restoring ibdata1 (main database file)..."
Copy-Item -Path "$corruptPath\ibdata1" -Destination $dataPath -Force

Write-Host "--- REPAIR COMPLETE ---" -ForegroundColor Green
Write-Host "Please open XAMPP Control Panel and click START on MySQL."
