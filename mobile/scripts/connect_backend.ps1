param(
    [int] $Port = 8000
)

$ErrorActionPreference = 'Stop'

$adb = Join-Path $env:LOCALAPPDATA 'Android\Sdk\platform-tools\adb.exe'
if (-not (Test-Path $adb)) {
    throw "ADB introuvable: $adb"
}

Write-Host "Verification du serveur Symfony sur https://127.0.0.1:$Port ..."
try {
    $curlOutput = & curl.exe -k -s -o NUL -w "%{http_code}" "https://127.0.0.1:$Port/api" --max-time 8
    if ($curlOutput -ne '200') {
        throw "Code HTTP inattendu: $curlOutput"
    }
    Write-Host "Backend OK: 200"
} catch {
    Write-Warning "Le backend ne repond pas sur https://127.0.0.1:$Port/api. Lancez: symfony server:start --port=$Port --daemon"
}

Write-Host "Activation du tunnel Android: adb reverse tcp:$Port tcp:$Port"
$devices = & $adb devices | Select-Object -Skip 1 | Where-Object { $_ -match '\sdevice$' } | ForEach-Object {
    ($_ -split '\s+')[0]
}

if (-not $devices -or $devices.Count -eq 0) {
    throw "Aucun appareil Android actif trouve. Verifiez que le telephone est connecte et autorise le debogage USB."
}

foreach ($device in $devices) {
    Write-Host "Tunnel pour $device ..."
    & $adb -s $device reverse "tcp:$Port" "tcp:$Port"
}

Write-Host "Tunnels actifs:"
foreach ($device in $devices) {
    & $adb -s $device reverse --list
}
