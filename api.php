<?php
// --- PART 1: PHP BACKEND LOGIC ---

// --- Custom Error and Exception Handling ---
// We log errors to a file instead of showing them to the user.
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');
// Don't display errors to the user, as it can break the JSON output.
ini_set('display_errors', 0);
error_reporting(E_ALL);

set_exception_handler(function($exception) {
    header('Content-Type: application/json', true, 500);
    echo json_encode(['results' => [['error' => 'خطای داخلی سرور رخ داده است.']]] );
    exit;
});

set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

header('Content-Type: application/json');

// --- Rate Limiting ---
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_SECONDS', 2); // Only 1 request per 2 seconds per IP
define('RATE_LIMIT_DIR', __DIR__ . '/rate_limit_logs/');

if (RATE_LIMIT_ENABLED && is_writable(RATE_LIMIT_DIR)) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $logFile = RATE_LIMIT_DIR . md5($ip);

    if (file_exists($logFile) && (time() - filemtime($logFile) < RATE_LIMIT_SECONDS)) {
        header('HTTP/1.1 429 Too Many Requests');
        echo json_encode(['results' => [['error' => 'شما در هر ۲ ثانیه فقط یک درخواست می‌توانید ارسال کنید.']]] );
        exit;
    }
    // Only update the timestamp if the directory is writable
    touch($logFile);
}


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
    // --- Caching Logic ---
    define('CACHE_ENABLED', true);
    define('CACHE_TTL', 3600); // 1 hour
    define('CACHE_DIR', __DIR__ . '/cache/');

    if (CACHE_ENABLED && is_writable(CACHE_DIR)) {
        $cacheKey = md5($input) . '.json';
        $cacheFile = CACHE_DIR . $cacheKey;

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < CACHE_TTL)) {
            $cachedData = json_decode(file_get_contents($cacheFile), true);
            if ($cachedData) {
                // To signify it's a cached result, we can add a flag (optional)
                $cachedData['cached'] = true;
                return $cachedData;
            }
        }
    }

    $isSearch = !filter_var($input, FILTER_VALIDATE_URL);

    if ($isSearch) {
        $url = 'https://www.radiojavan.com/searchs/mp3?q=' . urlencode($input);
    } else {
        $url = $input;
        // Ensure the URL has a scheme for parse_url to work correctly.
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = "https://" . $url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        // Remove www. from host to simplify check
        $host = preg_replace('/^www\./', '', $host);

        $allowed_hosts = ['radiojavan.com', 'play.radiojavan.com', 'rj.com'];

        if (!$host || !in_array($host, $allowed_hosts)) {
            return ['error' => "لینک وارد شده نامعتبر است یا پشتیبانی نمی‌شود: " . htmlspecialchars($input)];
        }
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
    $finalResult = ['data' => $result];

    // --- Save to Cache ---
    if (CACHE_ENABLED && isset($cacheFile) && is_writable(CACHE_DIR)) {
        file_put_contents($cacheFile, json_encode($finalResult));
    }

    return $finalResult;
}

// Main controller for AJAX: processes one or more lines of input.
$input = trim($_POST['input_data'] ?? '');
if (empty($input)) {
    echo json_encode(['results' => [['error' => 'ورودی خالی است.']]] );
    exit;
}

$lines = array_filter(array_map('trim', explode("\n", $input)));
$finalResults = [];
foreach ($lines as $line) {
    $finalResults[] = processRequest($line);
}

echo json_encode(['results' => $finalResults]);
