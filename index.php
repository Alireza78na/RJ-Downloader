<?php
// --- PART 1: PHP BACKEND LOGIC ---

// This script block only runs for AJAX requests initiated by the frontend JavaScript.
if (isset($_POST['action']) && $_POST['action'] === 'process_url') {
    
    // Set response header to JSON and suppress any other output.
    header('Content-Type: application/json');
    error_reporting(0);
    ini_set('display_errors', 0);

    // Fetches content from a URL using cURL.
    function fetchContent(string $url): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36'
        ]);
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($html)) {
            return ['error' => 'خطا در ارتباط با سرور رادیو جوان.'];
        }
        return ['html' => $html];
    }

    // Extracts the __NEXT_DATA__ JSON block from the page's HTML.
    function extractJsonData(string $html): array {
        if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/', $html, $matches)) {
            $json = json_decode($matches[1], true);
            return json_last_error() === JSON_ERROR_NONE ? ['data' => $json] : ['error' => 'خطا در پردازش اطلاعات دریافتی.'];
        }
        return ['error' => 'ساختار صفحه تغییر کرده و اطلاعات قابل استخراج نیست.'];
    }
    
    // Processes a single request, which can be a URL or a search query.
    function processRequest(string $input): array {
        $isSearch = !filter_var($input, FILTER_VALIDATE_URL);
        $url = $isSearch ? 'https://www.radiojavan.com/searchs/mp3?q=' . urlencode($input) : $input;

        if (!$isSearch && !str_contains($input, 'radiojavan.com')) {
            return ['error' => "لینک وارد شده نامعتبر است: " . htmlspecialchars($input)];
        }

        $fetchResult = fetchContent($url);
        if (isset($fetchResult['error'])) return $fetchResult;
        
        $jsonResult = extractJsonData($fetchResult['html']);
        if (isset($jsonResult['error'])) return $jsonResult;
        
        $pageProps = $jsonResult['data']['props']['pageProps'] ?? [];
        $result = [];

        if ($isSearch) {
            $songs = $pageProps['results']['song'] ?? [];
            $result['type'] = 'search';
            $result['query'] = htmlspecialchars($input);
            foreach($songs as $item) {
                 $result['items'][] = ['title' => $item['name'] ?? 'بی‌نام', 'artist' => $item['artist'] ?? 'ناشناس', 'cover' => $item['photo_500'] ?? '', 'download_url' => $item['link'] ?? ''];
            }
        } else if (str_contains($url, "/song/") || str_contains($url, "/podcast/")) {
            $media = $pageProps['media'];
            $result['type'] = 'song';
            $result['items'][] = ['title' => $media['song'] ?? $media['title'] ?? 'بی‌نام', 'artist' => $media['artist'] ?? 'ناشناس', 'cover' => $media['photo_hd'] ?? $media['photo'] ?? '', 'download_url' => $media['link'] ?? ''];
        } elseif (str_contains($url, "/video/")) {
            $media = $pageProps['media'];
            $qualities = [];
            if (!empty($media['lq_link'])) $qualities['کیفیت پایین'] = $media['lq_link'];
            if (!empty($media['hq_link'])) $qualities['کیفیت بالا'] = $media['hq_link'];
            if (!empty($media['hd_4k_link'])) $qualities['4K'] = $media['hd_4k_link'];
            $result['type'] = 'video';
            $result['items'][] = ['title' => $media['song'] ?? 'بی‌نام', 'artist' => $media['artist'] ?? 'ناشناس', 'cover' => $media['photo_hd'] ?? $media['photo'] ?? '', 'qualities' => $qualities];
        } elseif (str_contains($url, "/playlist/")) {
            $playlistItems = $pageProps['playlist']['items'] ?? [];
            $result['type'] = 'playlist';
            $result['playlist_name'] = htmlspecialchars($pageProps['playlist']['name'] ?? 'پلی‌لیست');
            foreach ($playlistItems as $item) {
                $result['items'][] = ['title' => $item['song'] ?? 'بی‌نام', 'artist' => $item['artist'] ?? 'ناشناس', 'cover' => $item['photo_hd'] ?? $item['photo'] ?? '', 'download_url' => $item['link'] ?? ''];
            }
        } else {
            return ['error' => 'این نوع لینک پشتیبانی نمی‌شود.'];
        }

        if (empty($result['items'])) return ['error' => 'محتوایی برای ورودی شما یافت نشد.'];
        
        $result['source'] = $input;
        return ['data' => $result];
    }

    // Main controller for AJAX: processes one or more lines of input.
    $input = trim($_POST['input_data'] ?? '');
    $lines = array_filter(array_map('trim', explode("\n", $input)));
    $finalResults = [];
    foreach ($lines as $line) {
        $finalResults[] = processRequest($line);
    }

    echo json_encode(['results' => $finalResults]);
    exit;
}

// --- PART 2: HTML, CSS, JAVASCRIPT (FRONTEND) ---
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
    <style>
        :root {
            --font-family: 'Vazirmatn', sans-serif;
            --bg: #121212; --bg-2: #1e1e1e; --card-bg: #2a2a2a; --text: #e0e0e0;
            --text-muted: #888; --border: #444; --shadow: rgba(0,0,0,0.4);
            --accent: #7c3aed; --accent-hover: #6d28d9; --green: #10b981; --blue: #3b82f6;
        }
        html[data-theme='light'] {
            --bg: #f5f5f5; --bg-2: #e5e5e5; --card-bg: #ffffff; --text: #1f2937;
            --text-muted: #6b7280; --border: #e5e7eb; --shadow: rgba(0,0,0,0.1);
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: var(--font-family); background-color: var(--bg); color: var(--text);
            margin: 0; padding: 2rem 1rem; transition: background-color 0.3s, color 0.3s;
        }
        .container { max-width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        h1 { font-size: 2rem; margin: 0; background: linear-gradient(45deg, var(--accent), #c9002f); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        #theme-toggle { background: var(--card-bg); border: 1px solid var(--border); color: var(--text); cursor: pointer; width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; transition: all 0.2s ease; }
        #theme-toggle:hover { border-color: var(--accent); color: var(--accent); }
        .main-form { background: var(--card-bg); padding: 2rem; border-radius: 16px; box-shadow: 0 10px 30px var(--shadow); }
        textarea {
            width: 100%; min-height: 120px; padding: 1rem; border: 1px solid var(--border);
            border-radius: 12px; font-size: 1rem; background-color: var(--bg-2);
            color: var(--text); resize: vertical; transition: all 0.2s ease;
        }
        textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 20%, transparent); }
        .main-button {
            display: flex; align-items: center; justify-content: center; gap: 0.75rem; width: 100%;
            padding: 1rem; background-image: linear-gradient(45deg, var(--accent), var(--accent-hover));
            color: white; border: none; border-radius: 12px; font-size: 1.125rem; font-weight: 700;
            cursor: pointer; transition: all 0.3s ease; margin-top: 1.5rem;
        }
        .main-button:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 20px color-mix(in srgb, var(--accent) 30%, transparent); }
        .main-button:disabled { background-color: #555; cursor: not-allowed; }
        .main-button svg { width: 24px; height: 24px; }
        #history { margin-top: 1rem; }
        #history button {
            font-family: var(--font-family); background: var(--bg-2); border: 1px solid var(--border);
            color: var(--text-muted); font-size: 0.75rem; padding: 0.25rem 0.75rem; margin: 0.25rem;
            border-radius: 20px; cursor: pointer; transition: all 0.2s ease;
        }
        #history button:hover { background: var(--accent); color: white; border-color: var(--accent); }
        .result-group { margin-top: 2rem; }
        .result-group-header { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem; padding: 0.5rem 1rem; background: var(--bg-2); border-radius: 8px; overflow-wrap: break-word; }
        .media-card {
            background: var(--card-bg); border-radius: 16px; padding: 1rem; margin-bottom: 1rem;
            display: grid; grid-template-columns: 100px 1fr; gap: 1.5rem; align-items: center;
            box-shadow: 0 4px 20px var(--shadow); transition: all 0.3s ease;
        }
        .media-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px var(--shadow); }
        .media-cover { width: 100px; height: 100px; border-radius: 12px; object-fit: cover; }
        .media-info h3 { margin: 0 0 0.25rem 0; font-size: 1.25rem; }
        .media-info p { margin: 0; color: var(--text-muted); }
        .media-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; }
        .media-actions a, .media-actions button {
            display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;
            color: white; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.875rem;
            border: none; cursor: pointer; transition: all 0.2s ease;
        }
        .media-actions .dl-btn { background-color: var(--green); }
        .media-actions .copy-btn { background-color: #0ea5e9; }
        .media-actions .telegram-btn { background-color: #229ED9; }
        .download-all-main-btn {
            display: flex; align-items: center; justify-content: center; gap: 1rem; width: 100%; margin-bottom: 1rem;
            padding: 1.25rem; background-image: linear-gradient(45deg, #059669, var(--green)); color: white; border: none;
            border-radius: 16px; font-size: 1.25rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease;
        }
        .download-all-main-btn:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 6px 25px color-mix(in srgb, var(--green) 30%, transparent); }
        .download-all-main-btn:disabled { background: #555; }
        .media-actions a:hover, .media-actions button:hover { transform: scale(1.05); }
        .loader { text-align: center; padding: 3rem 0; }
        .spinner { width: 56px; height: 56px; border: 5px solid var(--border); border-bottom-color: var(--accent); border-radius: 50%; display: inline-block; animation: rotation 1s linear infinite; }
        @keyframes rotation { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .toast {
            position: fixed; bottom: -100px; left: 50%; transform: translateX(-50%); background: linear-gradient(45deg, #1f2937, #374151); color: white;
            padding: 1rem 2rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); z-index: 1000; transition: bottom 0.5s ease-in-out;
        }
        .toast.show { bottom: 20px; }
        .empty-state { text-align: center; padding: 2rem; background: var(--card-bg); border-radius: 16px; margin-top: 2rem; }
        .empty-state svg { width: 64px; height: 64px; color: var(--text-muted); margin-bottom: 1rem; }
        .empty-state p { font-size: 1.2rem; margin: 0 0 1rem 0; }
        .empty-state small { color: var(--text-muted); line-height: 1.6; }
        @media (max-width: 600px) {
            h1 { font-size: 1.5rem; }
            .media-card { grid-template-columns: 1fr; text-align: center; }
            .media-cover { margin: 0 auto 1rem auto; }
        }
    </style>
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
    
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('download-form');
        const inputArea = document.getElementById('input-area');
        const submitBtn = document.getElementById('submit-btn');
        const loader = document.getElementById('loader');
        const resultsArea = document.getElementById('results-area');
        const themeToggle = document.getElementById('theme-toggle');
        const historyContainer = document.getElementById('history-buttons');
        let isLoading = false;
        
        // --- Theme Manager ---
        const applyTheme = (theme) => {
            document.documentElement.setAttribute('data-theme', theme);
            themeToggle.innerHTML = `<svg width="24" height="24"><use href="${theme === 'dark' ? '#icon-sun' : '#icon-moon'}"/></svg>`;
        };
        const currentTheme = localStorage.getItem('theme') || 'dark';
        applyTheme(currentTheme);
        themeToggle.addEventListener('click', () => {
            const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            localStorage.setItem('theme', newTheme);
            applyTheme(newTheme);
        });

        // --- Toast Notifier ---
        const showToast = (message) => {
            const toast = document.getElementById('toast');
            toast.textContent = message; toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        };
        
        // --- History Manager ---
        let history = JSON.parse(localStorage.getItem('rj_history')) || [];
        const updateHistory = (newItem) => {
            if (!history.find(h => h.source === newItem.source)) {
                history.unshift(newItem);
                history = history.slice(0, 5);
                localStorage.setItem('rj_history', JSON.stringify(history));
                renderHistory();
            }
        };
        const renderHistory = () => {
            historyContainer.innerHTML = history.length > 0 ? '<small>تاریخچه اخیر:</small>' : '';
            history.forEach(item => {
                const btn = document.createElement('button');
                let text;
                if (item.type === 'playlist') {
                    text = `پلی‌لیست: ${item.playlist_name}`;
                } else if (item.source.includes('http')) {
                    text = item.items[0].title;
                } else {
                    text = `جستجو: ${item.query}`;
                }
                btn.textContent = text.substring(0, 35) + (text.length > 35 ? '...' : '');
                btn.title = text;
                btn.onclick = () => { inputArea.value = item.source; form.dispatchEvent(new Event('submit')); };
                historyContainer.appendChild(btn);
            });
        };
        renderHistory();

        // --- Form Submission (AJAX) ---
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (isLoading || inputArea.value.trim() === '') return;

            isLoading = true;
            submitBtn.disabled = true;
            submitBtn.querySelector('span').textContent = 'در حال پردازش...';
            loader.style.display = 'block';
            resultsArea.innerHTML = '';

            // CORRECTED: Manually create FormData and append data.
            const formData = new FormData();
            formData.append('action', 'process_url');
            formData.append('input_data', inputArea.value);

            try {
                const response = await fetch('', { method: 'POST', body: formData });
                if (!response.ok) {
                   throw new Error(`Server responded with status: ${response.status}`);
                }
                const data = await response.json();
                
                if (data.results.length === 0 || data.results.every(r => r.error && !r.data)) {
                    resultsArea.innerHTML = `<div class="empty-state"><p>نتیجه‌ای یافت نشد!</p><small>لطفاً ورودی خود را بررسی کرده و دوباره تلاش کنید.</small></div>`;
                } else {
                    data.results.forEach(result => {
                        if (result.error) {
                            showToast(`خطا: ${result.error}`);
                        } else if (result.data) {
                            resultsArea.insertAdjacentHTML('beforeend', renderResultGroup(result.data));
                            if (result.data.type !== 'search') updateHistory(result.data);
                        }
                    });
                }
            } catch (error) {
                console.error('Fetch Error:', error);
                showToast('خطای عمومی رخ داد. لطفاً اتصال خود را بررسی کنید.');
            } finally {
                isLoading = false;
                submitBtn.disabled = false;
                submitBtn.querySelector('span').textContent = 'پردازش';
                loader.style.display = 'none';
            }
        });

        // --- UI Rendering ---
        const renderResultGroup = (data) => {
            let header = `<div class="result-group-header">ورودی: ${data.source}</div>`;
            if (data.type === 'playlist') {
                header += `<button class="download-all-main-btn" data-group-id="${data.source}"><svg width="24" height="24"><use href="#icon-download"/></svg><span>دانلود همه (${data.items.length} فایل)</span></button>`;
            } else if (data.type === 'search') {
                header = `<div class="result-group-header">نتایج جستجو برای: "${data.query}"</div>`;
            }
            const itemsHTML = data.items.map(item => `
                <div class="media-card" data-group-id="${data.source}">
                    <img src="${item.cover}" alt="Cover" class="media-cover" loading="lazy">
                    <div class="media-info">
                        <h3>${item.title}</h3><p>${item.artist}</p>
                        <div class="media-actions">${generateActionButtons(item)}</div>
                    </div>
                </div>`).join('');
            return `<div class="result-group">${header}${itemsHTML}</div>`;
        };

        const generateActionButtons = (item) => {
            const dlIcon = `<svg width="16" height="16"><use href="#icon-download"/></svg>`;
            const copyIcon = `<svg width="16" height="16"><use href="#icon-copy"/></svg>`;
            const telegramIcon = `<svg width="16" height="16"><use href="#icon-telegram"/></svg>`;
            let buttons = '';

            const createTelegramLink = (url, title) => {
                const text = `لینک دانلود "${title}"`;
                return `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`;
            };

            if (item.download_url) {
                buttons += `<a href="${item.download_url}" class="dl-btn" target="_blank" rel="noopener noreferrer">${dlIcon}دانلود</a>`;
                buttons += `<a href="${createTelegramLink(item.download_url, item.title)}" class="telegram-btn" target="_blank" rel="noopener noreferrer">${telegramIcon}تلگرام</a>`;
                buttons += `<button class="copy-btn" data-link="${item.download_url}">${copyIcon}کپی</button>`;
            } else if (item.qualities) {
                Object.entries(item.qualities).forEach(([quality, link]) => {
                    buttons += `<a href="${link}" class="dl-btn" target="_blank" rel="noopener noreferrer">${dlIcon}${quality}</a>`;
                });
            }
            return buttons;
        };
        
        // --- Event Delegation for Dynamic Buttons ---
        resultsArea.addEventListener('click', (e) => {
            const copyBtn = e.target.closest('.copy-btn');
            const downloadAllBtn = e.target.closest('.download-all-main-btn');
            
            if (copyBtn) {
                navigator.clipboard.writeText(copyBtn.dataset.link).then(() => showToast('لینک با موفقیت کپی شد!'));
            }
            
            if (downloadAllBtn && !downloadAllBtn.disabled) {
                const groupId = downloadAllBtn.dataset.groupId;
                const links = document.querySelectorAll(`.media-card[data-group-id="${groupId}"] .dl-btn`);
                showToast(`شروع دانلود ${links.length} فایل...`);
                
                downloadAllBtn.disabled = true;
                downloadAllBtn.querySelector('span').textContent = 'در حال آماده‌سازی...';

                links.forEach((link, index) => {
                    setTimeout(() => window.open(link.href, '_blank'), index * 1000);
                });

                setTimeout(() => {
                    downloadAllBtn.disabled = false;
                    downloadAllBtn.querySelector('span').textContent = `دانلود همه (${links.length} فایل)`;
                }, links.length * 1000 + 500);
            }
        });
    });
    </script>
</body>
</html>
