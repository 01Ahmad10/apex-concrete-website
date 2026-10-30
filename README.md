# Apex Concrete Polishing — website

Static site (HTML / CSS / JS) with a small PHP handler for the enquiry form, built to run on Hostinger shared hosting.

| File | Purpose |
| --- | --- |
| `index.html`, `services.html`, `gallery.html`, `contact.html` | The four pages |
| `style.css`, `editorial.css`, `app.js` | Styles and behaviour |
| `send-enquiry.php` | Receives the contact form and emails it via Hostinger SMTP |
| `mail-config.example.php` | Template for the mail settings (copy to `mail-config.php` on the server) |
| `sitemap.xml`, `robots.txt` | SEO |
| `favicon.ico`, `site.webmanifest`, `assets/icon-*.png`, `assets/apple-touch-icon.png` | Favicons |
| `.htaccess` | HTTPS redirect, caching, protects `mail-config.php` |

## Deploy on Hostinger

1. Upload everything in this folder to `public_html` (File Manager, FTP, or **Websites → Git** deployment from this repository).
2. In Hostinger → **Emails**, make sure the mailbox `info@apexconcretepolishing.com.au` exists.
3. In `public_html`, copy `mail-config.example.php` → `mail-config.php` and set `smtp_pass` to that mailbox's password.
   (`mail-config.php` is git-ignored, so the password never reaches GitHub.)
4. Open `/contact.html` and send a test enquiry. Without `smtp_pass` the handler falls back to PHP `mail()`.

## SEO

- Unique titles, meta descriptions, canonical URLs, Open Graph / Twitter cards on every page.
- `LocalBusiness` structured data on the home page.
- Google Search Console verification tag is included in every page `<head>`.
- After the site is live, submit `https://apexconcretepolishing.com.au/sitemap.xml` in Search Console.
