[CmdletBinding()]
param([string]$Blender = "", [int]$Samples = 16)
$ErrorActionPreference = "Stop"
$Repo = (Resolve-Path (Join-Path $PSScriptRoot "../..")).Path
if (-not $Blender) {
    $Found = Get-Command blender -ErrorAction SilentlyContinue
    if ($Found) { $Blender = $Found.Source }
    else {
        $Candidate = Get-ChildItem "${env:ProgramFiles}\Blender Foundation\Blender*\blender.exe" -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1
        if ($Candidate) { $Blender = $Candidate.FullName }
    }
}
if (-not $Blender -or -not (Test-Path -LiteralPath $Blender)) { throw 'Blender introuvable. Ajouter -Blender "C:\chemin\blender.exe".' }
if ($Samples -lt 1 -or $Samples -gt 256) { throw 'Samples doit etre compris entre 1 et 256.' }
& $Blender --background --factory-startup --python-exit-code 1 --python (Join-Path $PSScriptRoot "render_sprites.py") -- --samples $Samples
if ($LASTEXITCODE -ne 0) { throw "Generation Blender echouee (code $LASTEXITCODE). Aucun succes annonce." }
$Generated = Join-Path $Repo 'assets/generated'
$Manifest = Join-Path $Generated 'manifest.json'
if (-not (Test-Path $Manifest)) { throw 'Manifeste manquant.' }
$Data = Get-Content -LiteralPath $Manifest -Raw | ConvertFrom-Json
foreach ($Entry in $Data.files.PSObject.Properties) {
    $File = Join-Path $Generated $Entry.Name
    if (-not (Test-Path $File) -or (Get-FileHash $File -Algorithm SHA256).Hash.ToLowerInvariant() -ne $Entry.Value) { throw "Asset manquant ou hash incorrect : $File" }
}
# Copy only runtime PNG/JSON resources; .blend sources remain outside the imported game.
$Package = Join-Path $Repo 'games/slave-rift/assets/generated'
foreach ($Entry in $Data.files.PSObject.Properties) {
    if (-not $Entry.Name.EndsWith('.png')) { continue }
    $Target = Join-Path $Package $Entry.Name
    New-Item -ItemType Directory -Path (Split-Path $Target) -Force | Out-Null
    Copy-Item -LiteralPath (Join-Path $Generated $Entry.Name) -Destination $Target -Force
}
Copy-Item -LiteralPath $Manifest -Destination (Join-Path $Package 'manifest.json') -Force
Write-Host 'Assets termines et synchronises. Consulter assets/generated/manifest.json.'
