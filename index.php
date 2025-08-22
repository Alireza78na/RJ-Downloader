<?php
// --- Security Headers ---
// A strict CSP helps prevent XSS attacks.
$csp = "default-src 'self'; ";
$csp .= "script-src 'self'; "; // JavaScript only from the same origin
$csp .= "style-src 'self' https://fonts.googleapis.com; "; // CSS from self and Google Fonts
$csp .= "font-src 'self' https://fonts.gstatic.com; "; // Fonts from self and Google Fonts
$csp .= "img-src 'self' https: data:; "; // Images from self, any HTTPS source, and data URIs
$csp .= "connect-src 'self';"; // AJAX requests only to self
header("Content-Security-Policy: " . $csp);

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer-when-downgrade");
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دانلودر حرفه‌ای رادیو جوان</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body data-theme="dark">
    <svg width="0" height="0" style="display:none;">
        <symbol id="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></symbol>
        <symbol id="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></symbol>
        <symbol id="icon-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></symbol>
        <symbol id="icon-download" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></symbol>
        <symbol id="icon-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></symbol>
        <symbol id="icon-telegram" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.17.91-.494 1.208-.822 1.23-.696.047-1.225-.46-1.9-1.002-1.045-.816-1.67-1.31-2.6-2.044-.954-.753.27-1.156.477-1.335.14-.123.253-.23.268-.252.017-.024 3.79-3.46 3.824-3.766a.216.216 0 0 0-.053-.186.216.216 0 0 0-.21-.043c-.16.03-.49.138-2.282 1.442l-.01.008-1.585 1.025-.002.002c-.52.34-1.015.53-1.545.51a3.1 3.1 0 0 1-1.343-.45c-.655-.32-1.157-.49-1.142-1.023.013-.484.456-1.01.99-1.522 3.27-3.08 5.54-4.85 5.568-4.857z"/></symbol>
    </svg>

    <div class="container">
        <header class="header"><h1>RJ Downloader</h1><button id="theme-toggle" title="تغییر تم"><svg width="24" height="24"><use href="#icon-moon"/></svg></button></header>
        <div class="main-form">
            <form id="download-form">
                <textarea id="input-area" placeholder="...لینک آهنگ، پلی‌لیست، ویدیو یا عبارت مورد نظر برای جستجو را وارد کنید"></textarea>
                <button type="submit" id="submit-btn" class="main-button"><svg><use href="#icon-search"/></svg><span>پردازش</span></button>
            </form>
            <div id="history"><div id="history-buttons"></div></div>
        </div>
        <div id="loader" class="loader" style="display: none;"><div class="spinner"></div></div>
        <div id="results-area">
             <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                <p>به دانلودر حرفه‌ای رادیو جوان خوش آمدید!</p>
                <small>برای شروع، لینک مورد نظر را وارد کرده یا عبارتی را برای جستجو بنویسید.</small>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <!-- Modal for "Download All" -->
    <div id="download-all-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <button class="modal-close-btn">&times;</button>
            <h3>لینک‌های دانلود پلی‌لیست</h3>
            <p>برای کپی کردن تمام لینک‌ها از دکمه زیر استفاده کنید.</p>
            <textarea id="download-all-links" readonly></textarea>
            <button id="copy-all-btn" class="main-button" style="margin-top: 1rem;">
                <svg width="20" height="20"><use href="#icon-copy"/></svg>
                <span>کپی همه لینک‌ها</span>
            </button>
        </div>
    </div>

    <script src="assets/script.js"></script>
</body>
</html>
