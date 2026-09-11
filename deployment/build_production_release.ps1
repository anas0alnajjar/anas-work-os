param([string]$Version = (Get-Date -Format 'yyyyMMdd-HHmm'))

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$releaseRoot = Join-Path $projectRoot 'release'
$stageRoot = Join-Path $releaseRoot ("anas-production-{0}" -f $Version)
$zipPath = "$stageRoot.zip"

if (-not (Test-Path (Join-Path $projectRoot '.git'))) { throw 'Git repository is required.' }
if ((git -C $projectRoot status --porcelain).Count -gt 0) { throw 'Build only from a clean Git worktree.' }
if (-not $stageRoot.StartsWith($releaseRoot,[StringComparison]::OrdinalIgnoreCase)) { throw 'Unsafe release path.' }

New-Item -ItemType Directory -Force -Path $releaseRoot | Out-Null
if (Test-Path -LiteralPath $stageRoot) {
    $resolvedStage = (Resolve-Path -LiteralPath $stageRoot).Path
    if (-not $resolvedStage.StartsWith($releaseRoot,[StringComparison]::OrdinalIgnoreCase)) { throw 'Unsafe staging cleanup.' }
    Remove-Item -LiteralPath $resolvedStage -Recurse -Force
}
if (Test-Path -LiteralPath $zipPath) { Remove-Item -LiteralPath $zipPath -Force }

$publicRoot = Join-Path $stageRoot 'source\public_html'
$osRoot = Join-Path $publicRoot 'os'
New-Item -ItemType Directory -Force -Path $osRoot | Out-Null

Copy-Item -Path (Join-Path $projectRoot 'portfolio\*') -Destination $publicRoot -Recurse
foreach ($directory in @('assets','cron','includes','sql')) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $directory) -Destination $osRoot -Recurse
}
foreach ($file in Get-ChildItem -LiteralPath $projectRoot -File -Filter '*.php') {
    if ($file.Name -in @('config.php','config.local.php','install.php','config.hostinger.example.php')) { continue }
    Copy-Item -LiteralPath $file.FullName -Destination $osRoot
}
Copy-Item -LiteralPath (Join-Path $projectRoot '.htaccess') -Destination $osRoot
Copy-Item -LiteralPath (Join-Path $projectRoot 'README_AR.md') -Destination $osRoot
Copy-Item -LiteralPath (Join-Path $projectRoot 'config.example.php') -Destination (Join-Path $osRoot 'config.php.example')
New-Item -ItemType Directory -Force -Path (Join-Path $osRoot 'storage\documents') | Out-Null
Copy-Item -LiteralPath (Join-Path $projectRoot 'storage\.htaccess') -Destination (Join-Path $osRoot 'storage\.htaccess')
Copy-Item -LiteralPath (Join-Path $projectRoot 'storage\documents\.gitkeep') -Destination (Join-Path $osRoot 'storage\documents\.gitkeep')

foreach ($file in @('Dockerfile','docker-compose.yml','backup.sh','.env.example','cron.example')) {
    Copy-Item -LiteralPath (Join-Path $projectRoot "deploy\production\$file") -Destination $stageRoot
}
Copy-Item -LiteralPath (Join-Path $projectRoot 'docs\DEPLOYMENT.md') -Destination $stageRoot

$sha = (git -C $projectRoot rev-parse HEAD).Trim()
$sha | Set-Content -Encoding ascii (Join-Path $stageRoot 'RELEASE_SHA')
$manifest = Get-ChildItem -LiteralPath $stageRoot -Recurse -File | Sort-Object FullName | ForEach-Object {
    $relative = $_.FullName.Substring($stageRoot.Length + 1).Replace('\','/')
    $hash = (Get-FileHash -Algorithm SHA256 -LiteralPath $_.FullName).Hash.ToLowerInvariant()
    "$hash  $relative"
}
$manifest | Set-Content -Encoding utf8 (Join-Path $stageRoot 'SHA256SUMS.txt')

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($stageRoot,$zipPath,[System.IO.Compression.CompressionLevel]::Optimal,$false)

$forbidden = Get-ChildItem -LiteralPath $stageRoot -Recurse -File | Where-Object {
    $_.Name -in @('.env','config.php','config.local.php') -or
    $_.FullName -match '\\(db|backups|uploads|sessions|cache|caches)\\'
}
if ($forbidden) { throw 'Release contains persistent or secret files.' }

Write-Output $zipPath
