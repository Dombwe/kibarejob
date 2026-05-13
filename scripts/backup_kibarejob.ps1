param(
    [string]$BackendPath = "C:\wamp64\www\solutions\kibarejob\backend",
    [string]$BackupPath = "C:\kibarejob-backups",
    [string]$DatabaseUrl = $env:DATABASE_URL,
    [string]$MysqlDumpPath = "mysqldump",
    [string]$PgDumpPath = "pg_dump"
)

$ErrorActionPreference = "Stop"

$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$dbDir = Join-Path $BackupPath "db"
$storageDir = Join-Path $BackupPath "storage"
New-Item -ItemType Directory -Force -Path $dbDir, $storageDir | Out-Null

$storageSource = Join-Path $BackendPath "var\storage"
$storageArchive = Join-Path $storageDir "kibarejob_storage_$timestamp.zip"
if (Test-Path $storageSource) {
    Compress-Archive -Path $storageSource -DestinationPath $storageArchive -Force
    Write-Host "Storage backup created: $storageArchive"
} else {
    Write-Warning "Storage directory not found: $storageSource"
}

if ([string]::IsNullOrWhiteSpace($DatabaseUrl)) {
    Write-Warning "DATABASE_URL is empty. Database backup skipped."
    exit 0
}

$dbBackup = Join-Path $dbDir "kibarejob_db_$timestamp.sql"
if ($DatabaseUrl.StartsWith("mysql://")) {
    Write-Host "MySQL backup requires credentials through a secure option file or interactive prompt."
    Write-Host "Run manually if needed: $MysqlDumpPath --defaults-extra-file=C:\secure\kibarejob.cnf kibarejob_prod > `"$dbBackup`""
} elseif ($DatabaseUrl.StartsWith("postgresql://") -or $DatabaseUrl.StartsWith("postgres://")) {
    & $PgDumpPath $DatabaseUrl | Out-File -FilePath $dbBackup -Encoding utf8
    Compress-Archive -Path $dbBackup -DestinationPath "$dbBackup.zip" -Force
    Remove-Item -LiteralPath $dbBackup -Force
    Write-Host "PostgreSQL backup created: $dbBackup.zip"
} else {
    Write-Warning "Unsupported DATABASE_URL scheme. Database backup skipped."
}
