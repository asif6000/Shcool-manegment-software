# 🚀 cPanel Automated Deployment Toolkit

**Project:** Dream Roots Millennium School  
**Remote Host:** `dreamrootsmilleniumschool.com`  
**cPanel Port:** 2083  
**FTP User:** `irvrzogs`  

---

## 📁 Directory Structure Overview

The deployment follows standard Laravel cPanel architecture:
* Application root: `/home/irvrzogs/dream_app/` (all code outside public)
* Web root: `/home/irvrzogs/public_html/` (assets, public `robots.txt`, `index.php`)

---

## ⚡ Quick Start Options

### Option A: 1-Click Interactive Menu (Recommended)
Simply double-click or run:
```cmd
DEPLOY.bat
```
This gives you an interactive menu:
1. **Test FTP Connection** — Verifies connection to the server.
2. **Deploy Recent Updates (Full)** — Takes a live backup of remote files, then uploads recent Face Recognition & SEO updates.
3. **Deploy Recent Updates (Fast)** — Uploads recent patch files immediately.
4. **Backup Live Server Files** — Downloads all targeted live files into a timestamped folder with an MD5 `MANIFEST.txt`.
5. **Build Production ZIP Packages** — Prepares `dream_app_update.zip` and `public_html_update.zip`.
6. **Upload ZIP Packages** — Uploads the zip files directly to the root for cPanel File Manager extraction.

---

### Option B: PowerShell CLI
Run directly from PowerShell:

```powershell
# Test connection
.\deploy.ps1 -Action Test

# Full deploy (Backup live files first, then deploy patch)
.\deploy.ps1 -Action Full

# Backup only
.\deploy.ps1 -Action Backup

# Deploy patch files only
.\deploy.ps1 -Action Deploy

# Create ZIP packages
.\deploy.ps1 -Action Package

# Upload ZIP packages
.\deploy.ps1 -Action UploadZip
```

---

## 🗄️ Database Migrations (phpMyAdmin)

After deploying code, update the database using **either**:

1. **Option 1 (via phpMyAdmin in cPanel)**:
   - Log into cPanel: [https://dreamrootsmilleniumschool.com:2083](https://dreamrootsmilleniumschool.com:2083)
   - Open **phpMyAdmin**.
   - Select the school database.
   - Click the **Import** tab and upload `cpanel-deploy/migrations-2026-11-09.sql`.
   - Click **Go**.

2. **Option 2 (via SSH Terminal if enabled in cPanel)**:
   ```bash
   cd ~/dream_app
   php artisan migrate --force
   php artisan optimize:clear
   ```

---

## 🔄 Rollback Procedure

If you ever need to restore previous versions:
1. Every run of `-Action Full` or `-Action Backup` creates a timestamped folder: `cpanel-deploy/backup-YYYY-MM-DD-HHMMSS/`.
2. Inspect `MANIFEST.txt` inside that folder to view hashes.
3. Upload those files back via File Manager or FTP.
