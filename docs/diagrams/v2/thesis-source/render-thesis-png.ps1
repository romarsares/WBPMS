$ErrorActionPreference = 'Stop'

$sourceDir = $PSScriptRoot
$outputDir = Join-Path (Split-Path $sourceDir -Parent) 'thesis-png'
New-Item -ItemType Directory -Force -Path $outputDir | Out-Null

$renderer = Get-Command 'mmdc.cmd' -ErrorAction SilentlyContinue
if (-not $renderer) {
    $renderer = Get-Command 'mmdc' -ErrorAction SilentlyContinue
}
if (-not $renderer) {
    throw 'Mermaid CLI (mmdc) was not found on PATH. Run this script through npm exec with @mermaid-js/mermaid-cli.'
}

foreach ($source in Get-ChildItem -LiteralPath $sourceDir -Filter 'figure-*.mmd' -File | Sort-Object Name) {
    $output = Join-Path $outputDir ($source.BaseName + '.png')
    & $renderer.Source -i $source.FullName -o $output --size 2400 -b white
    if ($LASTEXITCODE -ne 0) {
        throw "Mermaid rendering failed for $($source.Name) with exit code $LASTEXITCODE."
    }
}

Write-Output "Rendered $((Get-ChildItem -LiteralPath $outputDir -Filter 'figure-*.png' -File).Count) landscape PNG files to $outputDir"
