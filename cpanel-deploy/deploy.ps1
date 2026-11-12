<#
.SYNOPSIS
    Automated cPanel FTP/FTPS Deployment Script for Dream Roots Millennium School.

.DESCRIPTION
    Automates testing, backing up live files, and deploying updates to cPanel.
    Supports both native .NET FTP and curl fallback.

.PARAMETER Action
    Test      : Test FTP/FTPS connection
    Backup    : Backup current remote files that will be affected by deployment
    Deploy    : Deploy latest patch files (Face Recognition & SEO modules)
    Package   : Build clean production .zip packages
    UploadZip : Upload .zip packages to cPanel root
    Full      : Backup -> Deploy Patch in one step

.EXAMPLE
    .\deploy.ps1 -Action Test
    .\deploy.ps1 -Action Deploy
    .\deploy.ps1 -Action Full
#>

[CmdletBinding()]
param(
    [ValidateSet("Test", "Backup", "Deploy", "Package", "UploadZip", "Full")]
    [string]$Action = "Full"
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$configFile = Join-Path $scriptDir "deploy.config.json"

if (-not (Test-Path $configFile)) {
    Write-Error "Configuration file not found: $configFile"
    exit 1
}

$config = Get-Content $configFile -Raw | ConvertFrom-Json
$ftpHost = $config.ftp.host
$ftpPort = $config.ftp.port
$ftpUser = $config.ftp.user
$ftpPass = $config.ftp.pass
$useSsl  = [bool]$config.ftp.useSsl
$passive = [bool]$config.ftp.passive

$remoteAppDir    = $config.paths.remoteAppDir.Trim('/')
$remotePublicDir = $config.paths.remotePublicDir.Trim('/')
$localRoot       = (Resolve-Path (Join-Path $scriptDir $config.paths.localRootDir)).Path
$patchFiles      = $config.patchFiles

function Write-Step([string]$msg) {
    Write-Host "`n====> $msg" -ForegroundColor Cyan
}

function Write-Success([string]$msg) {
    Write-Host " [OK] $msg" -ForegroundColor Green
}

function Write-Warn([string]$msg) {
    Write-Host " [WARN] $msg" -ForegroundColor Yellow
}

function Write-Fail([string]$msg) {
    Write-Host " [FAIL] $msg" -ForegroundColor Red
}

function Get-FtpUri([string]$remoteRelativePath) {
    $cleanPath = $remoteRelativePath.TrimStart('/')
    return "ftp://$ftpHost`:$ftpPort/$cleanPath"
}

function Create-FtpRequest([string]$remoteRelativePath, [string]$method) {
    $uri = Get-FtpUri $remoteRelativePath
    $req = [System.Net.FtpWebRequest]::Create($uri)
    $req.Credentials = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)
    $req.Method = $method
    $req.UsePassive = $passive
    $req.EnableSsl = $useSsl
    $req.KeepAlive = $false
    $req.Timeout = 15000
    return $req
}

function Test-FtpConnection {
    Write-Step "Testing FTP connection to $ftpHost`:$ftpPort ($ftpUser)..."
    try {
        $req = Create-FtpRequest "" ([System.Net.WebRequestMethods+Ftp]::ListDirectory)
        $resp = $req.GetResponse()
        $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
        $listing = $reader.ReadToEnd()
        $reader.Close()
        $resp.Close()

        Write-Success "Connection successful! Root directory listing accessible."
        Write-Host "`nRemote Root Directories/Files Found:" -ForegroundColor Gray
        $listing.Split("`n") | Where-Object { $_.Trim() -ne "" } | ForEach-Object {
            Write-Host "  - $($_.Trim())" -ForegroundColor DarkGray
        }
        return $true
    }
    catch {
        Write-Fail "Native FTP connection failed: $($_.Exception.Message)"

        # Fallback test with curl if installed
        $curl = Get-Command curl.exe -ErrorAction SilentlyContinue
        if ($curl) {
            Write-Host "Trying fallback with curl.exe..." -ForegroundColor Yellow
            $curlArgs = @("-s", "-l", "-u", "$($ftpUser):$($ftpPass)", "ftp://$ftpHost`:$ftpPort/")
            $output = & $curl.Path $curlArgs 2>&1
            if ($LASTEXITCODE -eq 0) {
                Write-Success "curl connection succeeded!"
                return $true
            } else {
                Write-Fail "curl connection failed with exit code $LASTEXITCODE."
            }
        }
        return $false
    }
}

function Ensure-RemoteDirectory([string]$remoteDir) {
    $parts = $remoteDir.Trim('/').Split('/')
    $current = ""
    foreach ($part in $parts) {
        if (-not $part) { continue }
        $current = if ($current) { "$current/$part" } else { $part }
        try {
            $req = Create-FtpRequest $current ([System.Net.WebRequestMethods+Ftp]::MakeDirectory)
            $resp = $req.GetResponse()
            $resp.Close()
        }
        catch {
            # Directory may already exist (550), which is expected
        }
    }
}

function Download-RemoteFile([string]$remotePath, [string]$localDestination) {
    try {
        $destDir = Split-Path -Parent $localDestination
        if (-not (Test-Path $destDir)) {
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
        }

        $req = Create-FtpRequest $remotePath ([System.Net.WebRequestMethods+Ftp]::DownloadFile)
        $resp = $req.GetResponse()
        $stream = $resp.GetResponseStream()
        $fileStream = [System.IO.File]::Create($localDestination)
        $stream.CopyTo($fileStream)
        $fileStream.Close()
        $stream.Close()
        $resp.Close()
        return $true
    }
    catch {
        return $false
    }
}

function Upload-LocalFile([string]$localPath, [string]$remotePath) {
    if (-not (Test-Path $localPath)) {
        Write-Fail "Local file not found: $localPath"
        return $false
    }

    $remoteDir = Split-Path -Parent $remotePath
    if ($remoteDir) {
        Ensure-RemoteDirectory $remoteDir
    }

    try {
        $req = Create-FtpRequest $remotePath ([System.Net.WebRequestMethods+Ftp]::UploadFile)
        $fileBytes = [System.IO.File]::ReadAllBytes($localPath)
        $req.ContentLength = $fileBytes.Length
        $stream = $req.GetRequestStream()
        $stream.Write($fileBytes, 0, $fileBytes.Length)
        $stream.Close()
        $resp = $req.GetResponse()
        $resp.Close()
        return $true
    }
    catch {
        # Fallback to curl
        $curl = Get-Command curl.exe -ErrorAction SilentlyContinue
        if ($curl) {
            $uri = Get-FtpUri $remotePath
            $args = @("-s", "-T", $localPath, "-u", "$($ftpUser):$($ftpPass)", "--ftp-create-dirs", $uri)
            & $curl.Path $args 2>&1 | Out-Null
            if ($LASTEXITCODE -eq 0) {
                return $true
            }
        }
        Write-Fail "Upload error for $remotePath : $($_.Exception.Message)"
        return $false
    }
}

function Get-RemoteTargetPath([string]$relPath) {
    if ($relPath.StartsWith("public/")) {
        $sub = $relPath.Substring(7)
        return "$remotePublicDir/$sub"
    } else {
        return "$remoteAppDir/$relPath"
    }
}

function Backup-LiveFiles {
    Write-Step "Creating backup of live files before deployment..."
    $timestamp = (Get-Date).ToString("yyyy-MM-dd-HHmmss")
    $backupDir = Join-Path $scriptDir "backup-$timestamp"
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null

    $manifestLines = @(
        "Backup taken: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')",
        "Target Server: $ftpHost",
        "Files downloaded from live environment:",
        ""
    )

    $downloadCount = 0
    foreach ($file in $patchFiles) {
        $remotePath = Get-RemoteTargetPath $file
        $localBackupPath = Join-Path $backupDir $file
        
        Write-Host "Backing up remote: $remotePath ... " -NoNewline
        if (Download-RemoteFile $remotePath $localBackupPath) {
            $hash = (Get-FileHash $localBackupPath -Algorithm MD5).Hash.ToLower()
            Write-Host "DOWNLOADED ($hash)" -ForegroundColor Green
            $manifestLines += "$hash  $file"
            $downloadCount++
        } else {
            Write-Host "NOT FOUND ON REMOTE (New file)" -ForegroundColor Yellow
            $manifestLines += "(new file)  $file"
        }
    }

    $manifestPath = Join-Path $backupDir "MANIFEST.txt"
    $manifestLines | Out-File -FilePath $manifestPath -Encoding UTF8
    Write-Success "Backup complete ($downloadCount files saved to $backupDir)"
    return $backupDir
}

function Deploy-PatchFiles {
    Write-Step "Deploying $(($patchFiles).Count) patch files to cPanel..."
    $successCount = 0
    $failCount = 0

    foreach ($file in $patchFiles) {
        $localPath = Join-Path $localRoot $file
        $remotePath = Get-RemoteTargetPath $file

        Write-Host "Uploading: $file -> $remotePath ... " -NoNewline
        if (Upload-LocalFile $localPath $remotePath) {
            Write-Host "DONE" -ForegroundColor Green
            $successCount++
        } else {
            Write-Host "FAILED" -ForegroundColor Red
            $failCount++
        }
    }

    Write-Host "`nDeployment Summary:" -ForegroundColor Cyan
    Write-Host "  Success: $successCount" -ForegroundColor Green
    if ($failCount -gt 0) {
        Write-Host "  Failed : $failCount" -ForegroundColor Red
    } else {
        Write-Success "All patch files deployed successfully!"
    }
}

function Package-ZipFiles {
    Write-Step "Building clean production ZIP archives..."

    $zipApp = Join-Path $scriptDir "dream_app_update.zip"
    $zipPublic = Join-Path $scriptDir "public_html_update.zip"

    if (Test-Path $zipApp) { Remove-Item $zipApp -Force }
    if (Test-Path $zipPublic) { Remove-Item $zipPublic -Force }

    # Package public directory
    Write-Host "Packaging public assets to $zipPublic..." -ForegroundColor Gray
    Compress-Archive -Path (Join-Path $localRoot "public\*") -DestinationPath $zipPublic -Force
    Write-Success "public_html_update.zip created ($([math]::Round((Get-Item $zipPublic).Length / 1MB, 2)) MB)"

    # List of directories/files for dream_app
    $appIncludes = @("app", "bootstrap", "config", "database", "lang", "resources", "routes", "artisan", "composer.json", "composer.lock")
    $includePaths = $appIncludes | ForEach-Object { Join-Path $localRoot $_ } | Where-Object { Test-Path $_ }

    Write-Host "Packaging core application files to $zipApp..." -ForegroundColor Gray
    Compress-Archive -Path $includePaths -DestinationPath $zipApp -Force
    Write-Success "dream_app_update.zip created ($([math]::Round((Get-Item $zipApp).Length / 1MB, 2)) MB)"
}

function Upload-ZipPackages {
    $zipApp = Join-Path $scriptDir "dream_app_update.zip"
    $zipPublic = Join-Path $scriptDir "public_html_update.zip"

    if (-not (Test-Path $zipApp) -or -not (Test-Path $zipPublic)) {
        Package-ZipFiles
    }

    Write-Step "Uploading ZIP packages to cPanel root..."
    Write-Host "Uploading $(Split-Path -Leaf $zipApp)..." -NoNewline
    if (Upload-LocalFile $zipApp (Split-Path -Leaf $zipApp)) {
        Write-Host " DONE" -ForegroundColor Green
    } else {
        Write-Host " FAILED" -ForegroundColor Red
    }

    Write-Host "Uploading $(Split-Path -Leaf $zipPublic)..." -NoNewline
    if (Upload-LocalFile $zipPublic (Split-Path -Leaf $zipPublic)) {
        Write-Host " DONE" -ForegroundColor Green
    } else {
        Write-Host " FAILED" -ForegroundColor Red
    }

    Write-Success "Packages uploaded. You can now extract them directly in cPanel File Manager."
}

# --- Main Dispatcher ---
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " cPanel FTP Deployment Tool - Dream Roots Millennium School" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

switch ($Action) {
    "Test" {
        Test-FtpConnection
    }
    "Backup" {
        Backup-LiveFiles
    }
    "Deploy" {
        Deploy-PatchFiles
    }
    "Package" {
        Package-ZipFiles
    }
    "UploadZip" {
        Upload-ZipPackages
    }
    "Full" {
        $connected = Test-FtpConnection
        if (-not $connected) {
            Write-Warn "Could not verify connection automatically. Would you still like to proceed?"
            $confirm = Read-Host "Proceed with deployment? (y/n)"
            if ($confirm -ne 'y') {
                exit 0
            }
        }
        Backup-LiveFiles
        Deploy-PatchFiles
    }
}
