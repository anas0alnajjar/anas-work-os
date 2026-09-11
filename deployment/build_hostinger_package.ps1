param([string]$Version = (Get-Date -Format 'yyyyMMdd-HHmm'))

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$releaseRoot = Join-Path $projectRoot 'release'
$stageRoot = Join-Path $releaseRoot 'anas-work-os-hostinger'
$zipPath = Join-Path $releaseRoot ("Anas_Work_OS_Hostinger_{0}.zip" -f $Version)

if (-not $stageRoot.StartsWith($releaseRoot,[StringComparison]::OrdinalIgnoreCase)) {
    throw 'Unsafe staging path.'
}
New-Item -ItemType Directory -Force -Path $releaseRoot | Out-Null
if (Test-Path -LiteralPath $stageRoot) {
    $resolvedStage = (Resolve-Path -LiteralPath $stageRoot).Path
    if (-not $resolvedStage.StartsWith($releaseRoot,[StringComparison]::OrdinalIgnoreCase)) { throw 'Refusing unsafe cleanup.' }
    Remove-Item -LiteralPath $resolvedStage -Recurse -Force
}
New-Item -ItemType Directory -Path $stageRoot | Out-Null

$excludedPhp = @('config.local.php','install.php')
Get-ChildItem -LiteralPath $projectRoot -File -Filter '*.php' | Where-Object { $_.Name -notin $excludedPhp } | ForEach-Object {
    Copy-Item -LiteralPath $_.FullName -Destination $stageRoot
}
foreach ($file in @('.htaccess','README_AR.md','HOSTINGER_DEPLOYMENT_AR.md')) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $file) -Destination $stageRoot
}
Copy-Item -LiteralPath (Join-Path $projectRoot 'config.hostinger.example.php') -Destination (Join-Path $stageRoot 'config.local.php.example')
foreach ($directory in @('assets','cron','includes','sql')) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $directory) -Destination $stageRoot -Recurse
}
New-Item -ItemType Directory -Force -Path (Join-Path $stageRoot 'storage\documents') | Out-Null
Copy-Item -LiteralPath (Join-Path $projectRoot 'storage\documents\.gitkeep') -Destination (Join-Path $stageRoot 'storage\documents\.gitkeep')

$manifest = Get-ChildItem -LiteralPath $stageRoot -Recurse -File | Sort-Object FullName | ForEach-Object {
    $relative = $_.FullName.Substring($stageRoot.Length + 1).Replace('\','/')
    $hash = (Get-FileHash -Algorithm SHA256 -LiteralPath $_.FullName).Hash.ToLowerInvariant()
    "$hash  $relative"
}
$manifest | Set-Content -Encoding utf8 (Join-Path $stageRoot 'SHA256SUMS.txt')

if (Test-Path -LiteralPath $zipPath) { Remove-Item -LiteralPath $zipPath -Force }
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($stageRoot,$zipPath,[System.IO.Compression.CompressionLevel]::Optimal,$false)

$forbidden = Get-ChildItem -LiteralPath $stageRoot -Recurse -File | Where-Object { $_.Name -eq 'config.local.php' -or $_.Name -eq 'install.php' -or $_.FullName -match '\\.git(\\|$)' }
if ($forbidden) { throw 'Forbidden production files were included.' }
Write-Output $zipPath
