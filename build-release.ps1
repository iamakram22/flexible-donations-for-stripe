# Builds an SVN-ready WordPress.org release package:
#   release/svn/trunk/<plugin>/   - plugin files + vendor (SVN trunk)
#   release/svn/tags/<version>/   - copy of trunk (SVN tag)
#   release/svn/assets/           - icon, banner, screenshots
#   release/custom-donations-for-stripe-<version>.zip - WP.org upload (trunk only)
# Output goes to release/ (gitignored). Run from the plugin root.
$ErrorActionPreference = 'Stop'

$root = $PSScriptRoot
# ponytail: local dev folder predates the rename; the WP.org slug is what the package folder must be
$plugin = 'custom-donations-for-stripe'
$version = Select-String -Path "$root\custom-donations-for-stripe.php" -Pattern "Version:\s*(\S+)" | ForEach-Object { $_.Matches[0].Groups[1].Value }
$releaseDir = Join-Path $root 'release'
$svnTrunk = Join-Path $releaseDir 'svn\trunk'
$svnTag = Join-Path $releaseDir "svn\tags\$version"
$svnAssets = Join-Path $releaseDir 'svn\assets'

if (Test-Path $releaseDir) { Remove-Item -Recurse -Force $releaseDir }

function Copy-Package($dest) {
    $target = Join-Path $dest $plugin
    New-Item -ItemType Directory -Force -Path $target | Out-Null
    Copy-Item -Recurse -Force "$root\custom-donations-for-stripe.php", "$root\includes", "$root\index.php", "$root\composer.json", "$root\readme.txt", "$root\changelog.txt", "$root\LICENSE", "$root\vendor" -Destination $target
    New-Item -ItemType Directory -Force -Path "$target\assets" | Out-Null
    Copy-Item -Recurse -Force "$root\assets\css", "$root\assets\js" -Destination "$target\assets"
}

Copy-Package $svnTrunk
Copy-Package $svnTag
New-Item -ItemType Directory -Force -Path $svnAssets | Out-Null
Copy-Item -Recurse -Force "$root\assets\banner-*.png", "$root\assets\icon-*.png", "$root\assets\screenshot-*.png" -Destination $svnAssets

$zip = Join-Path $releaseDir "custom-donations-for-stripe-$version.zip"
Compress-Archive -Path "$svnTrunk\*" -DestinationPath $zip -Force

$sizeMB = [math]::Round((Get-Item $zip).Length / 1MB, 2)
Write-Host "Package: $zip ($sizeMB MB)  Tag: $version"
if ($sizeMB -gt 9.5) { Write-Warning "ZIP is ${sizeMB}MB - WordPress.org limit is 10MB" }
