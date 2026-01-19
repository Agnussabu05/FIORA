
Write-Host "--- MySQL FACTORY RESET ---" -ForegroundColor Cyan
$mysqlPath = "C:\xampp\mysql"
$dataPath = "$mysqlPath\data"
$backupPath = "$mysqlPath\backup"
$corruptPath = "$mysqlPath\data_old_corrupt_" + (Get-Date -Format "HHmmss")

# 1. Stop MySQL
Stop-Service mysql -ErrorAction SilentlyContinue
taskkill /F /IM mysqld.exe /T 2>$null

# 2. Move Corrupt Data Aside
Rename-Item -Path $dataPath -NewName $corruptPath

# 3. Create Clean Data Folder from XAMPP Backup
New-Item -ItemType Directory -Path $dataPath | Out-Null
Copy-Item -Path "$backupPath\*" -Destination $dataPath -Recurse -Force

Write-Host "Factory Reset Complete. MySQL is now fresh." -ForegroundColor Green
