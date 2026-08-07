# Builds a WordPress.org-ready release package:
#   release/svn/trunk/           - plugin files at trunk root (WP.org requires files directly in trunk/)
#   release/svn/tags/<version>/  - copy of trunk (files directly in tag root)
#   release/svn/assets/          - icon, banner, screenshots
#   release/flexible-donations-for-stripe-<version>.zip - same contents as trunk, files at zip root
# Output goes to release/ (gitignored). Run from the plugin root.
$ErrorActionPreference = 'Stop'

$root = $PSScriptRoot
$plugin = 'flexible-donations-for-stripe'
$version = Select-String -Path "$root\flexible-donations-for-stripe.php" -Pattern "Version:\s*(\S+)" | ForEach-Object { $_.Matches[0].Groups[1].Value }
$releaseDir = Join-Path $root 'release'
$svnTrunk = Join-Path $releaseDir 'svn\trunk'
$svnTag = Join-Path $releaseDir "svn\tags\$version"
$svnAssets = Join-Path $releaseDir 'svn\assets'

if (Test-Path $releaseDir) { Remove-Item -Recurse -Force $releaseDir }

function Copy-Package($dest) {
    New-Item -ItemType Directory -Force -Path $dest | Out-Null
    Copy-Item -Recurse -Force "$root\flexible-donations-for-stripe.php", "$root\includes", "$root\index.php", "$root\composer.json", "$root\readme.txt", "$root\changelog.txt", "$root\LICENSE", "$root\vendor" -Destination $dest
    New-Item -ItemType Directory -Force -Path "$dest\assets" | Out-Null
    Copy-Item -Recurse -Force "$root\assets\css", "$root\assets\js" -Destination "$dest\assets"
}

Copy-Package $svnTrunk
Copy-Package $svnTag
New-Item -ItemType Directory -Force -Path $svnAssets | Out-Null
Copy-Item -Recurse -Force "$root\assets\banner-*.png", "$root\assets\icon-*.png", "$root\assets\screenshot-*.png" -Destination $svnAssets

$zip = Join-Path $releaseDir "flexible-donations-for-stripe-$version.zip"
Compress-Archive -Path "$svnTrunk\*" -DestinationPath $zip -Force

$sizeMB = [math]::Round((Get-Item $zip).Length / 1MB, 2)
Write-Host "Package: $zip ($sizeMB MB)  Tag: $version"
if ($sizeMB -gt 9.5) { Write-Warning "ZIP is ${sizeMB}MB - WordPress.org limit is 10MB" }
