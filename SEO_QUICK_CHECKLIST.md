# 🚀 SEO Implementation - Quick Setup Checklist

## ✅ Deployment Steps

### 1. Before Deploying
```bash
# Test locally first
php artisan serve
curl http://localhost:8000/sitemap.xml
```

### 2. Upload Files
**New files to upload:**
- [ ] `/app/Services/SeoService.php`
- [ ] `/app/Services/SitemapService.php`
- [ ] `/app/Http/Controllers/AdminSeoController.php`
- [ ] `/app/Http/Controllers/SitemapController.php`
- [ ] `/database/migrations/2026_11_08_000001_create_seo_settings_table.php`
- [ ] `/resources/views/partials/seo-tags.blade.php`
- [ ] `/resources/views/admin/seo/settings.blade.php`
- [ ] `/resources/views/admin/seo/preview.blade.php`

**Files to update:**
- [ ] `/routes/web.php` (append new routes)
- [ ] `/public/robots.txt` (replace entire file)
- [ ] `/resources/views/layouts/public.blade.php` (update head section)
- [ ] `/app/Http/Controllers/LandingController.php` (add SEO data)
- [ ] `/app/Http/Controllers/PublicNoticeController.php` (add SEO data)
- [ ] `/app/Http/Controllers/PublicTeacherController.php` (add SEO data)
- [ ] `/resources/views/landing.blade.php` (add @push)
- [ ] `/resources/views/public/notices/index.blade.php` (add @push)

### 3. After Upload
```bash
# Clear all caches
php artisan config:clear
php artisan view:clear
php artisan cache:clear

# Apply migration (optional)
php artisan migrate

# Verify routes exist
php artisan route:list | grep seo
```

### 4. Google Search Console
- [ ] Create account at https://search.google.com/search-console
- [ ] Add domain: `dreamrootsmilleniumschool.com`
- [ ] Verify DNS ownership
- [ ] Submit sitemap: `sitemap.xml`
- [ ] Request indexing

---

## 🔍 Verification Commands

### Check Sitemap
```bash
curl -I https://dreamrootsmilleniumschool.com/sitemap.xml
# Should return HTTP/1.1 200 OK
```

### Check Robots.txt
```bash
curl https://dreamrootsmilleniumshell.com/robots.txt
# Should show disallow rules and sitemap location
```

### View Page Source (Browser DevTools)
Open any public page → View Source
Look for:
- `<meta property="og:title">` ✓
- `<link rel="canonical">` ✓  
- `<script type="application/ld+json">` ✓

---

## 🧪 Testing Checklist

- [ ] Home page shows SEO title "হোম"
- [ ] Notices page shows Bengali title
- [ ] Teachers page displays properly
- [ ] Sitemap accessible at `/sitemap.xml`
- [ ] Robots.txt not blocking CSS/images
- [ ] Admin can access `/admin/seo`
- [ ] Meta tags appear in page source
- [ ] Open Graph works on social share
- [ ] JSON-LD schema valid (test at google.com/test/rich-results)

---

## ⚠️ Common Issues & Fixes

| Issue | Solution |
|-------|----------|
| Sitemap returns 404 | Check route exists: `php artisan route:list | grep sitemap` |
| Cache still old version | Run `php artisan view:clear && php artisan config:clear` |
| Admin panel not accessible | Ensure you have admin role with proper permissions |
| Schema errors | Verify school address/phone in database is complete |
| Images not showing in previews | Set OG image URL in admin SEO settings |

---

## 📞 Support Contacts

For issues, check:
- Laravel logs: `storage/logs/laravel.log`
- Browser console for JavaScript errors
- Network tab for failed requests

---

## 🎯 Success Metrics

After 2 weeks in production:
- Increased organic traffic
- Better rankings for "Kushtia school" keywords
- Improved search result click-through rates
- Rich snippets appearing in Google results

---

**Last Updated**: November 8, 2026  
**Version**: 1.0.0  
**Status**: Production Ready ✅
