<?php

// ============ ⚙ CONFIGURATION ============
$hostname = '';
$username = '';
$password = '';
$user_agent = 'Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stbapp ver: 2 rev: 250 Safari/533.3';
// =========================================

// 🔍 Parse request URI and extract ID and output type
$uri = $_SERVER['REQUEST_URI'];
$output_type = (stripos($uri, '.m3u8') !== false) ? 'm3u8' : 'ts';
$id = preg_replace('/\.(ts|m3u8)$/i', '', basename($uri));

// ❌ Validate ID early — before headers are sent
if (empty($id) || $id === basename($_SERVER['PHP_SELF'])) {
    header("Content-Type: application/json");
    echo json_encode([
        "error" => true,
        "message" => "ID Not Provided"
    ]);
    exit;
}

// 🔗 Build base stream URL
$baseStreamUrl = "http://$hostname/live/$username/$password/$id";

// 🎬 Handle TS Streaming
if ($output_type === 'ts') {
    $tsUrl = "$baseStreamUrl.ts";

    $headers = [
        "Icy-MetaData: 1",
        "User-Agent: $user_agent",
        "Accept-Encoding: identity",
        "Host: $hostname",
        "Connection: Keep-Alive",
        "Referer: http://localhost/",
        "Origin: http://localhost/",
        "X-Forwarded-For:",
        "Via:",
        "Client-IP:",
        "True-Client-IP:",
        "Forwarded:"
    ];

    @ini_set('zlib.output_compression', 'Off');
    @ini_set('output_buffering', 'Off');
    @ini_set('implicit_flush', 1);
    while (ob_get_level()) ob_end_flush();
    ob_implicit_flush(true);

    header("Content-Type: video/mp2t");
    header("Content-Disposition: inline; filename=\"$hostname-{$id}.ts\"");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tsUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) {
        echo $data;
        flush();
        return strlen($data);
    });

    $result = curl_exec($ch);

    if (curl_errno($ch) || $result === false) {
        http_response_code(502);
        echo "❌ cURL Error: " . curl_error($ch);
    }

    curl_close($ch);
    exit;
}

// 🎞️ Handle M3U8 Streaming
if ($output_type === 'm3u8') {
    set_time_limit(0);
    ini_set('memory_limit', '-1');
    ob_implicit_flush(true);

    $m3u8Url = "$baseStreamUrl.m3u8";
    $headers = [
        "User-Agent: $user_agent",
        "Host: $hostname",
        "Connection: Keep-Alive",
        "Referer: http://localhost/",
        "Origin: http://localhost/",
        "Accept-Encoding: gzip, deflate, br",
        "X-Forwarded-For:",
        "Via:",
        "Client-IP:",
        "True-Client-IP:",
        "Forwarded:"
    ];

    // Optional: Cache m3u8 for 5 seconds to reduce origin hits
    $cache_file = sys_get_temp_dir() . "/m3u8_cache_{$id}.txt";
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 5) {
        header("Content-Type: application/vnd.apple.mpegurl");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        echo file_get_contents($cache_file);
        exit;
    }

    function fetchStream($url, $headers = [], $followRedirect = false) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => $followRedirect,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            return [false, false];
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headersOut = substr($response, 0, $headerSize);
        $bodyOut = substr($response, $headerSize);
        curl_close($ch);

        return [$headersOut, $bodyOut];
    }

    list($header, $body) = fetchStream($m3u8Url, $headers);

    if ($body === false) {
        header("Location: https://tg-aadi.vercel.app/intro.m3u8");
        exit;
    }

    if (preg_match('/Location:\s*(.+?)\s*\n/i', $header, $match)) {
        $redirectedUrl = trim($match[1]);
        $parsed = parse_url($redirectedUrl);
        $host = $parsed['host'] ?? $hostname;
        $port = isset($parsed['port']) ? ":{$parsed['port']}" : '';

        list(, $redirectedBody) = fetchStream($redirectedUrl, [], true);

        if ($redirectedBody === false) {
            header("Location: https://tg-aadi.vercel.app/intro.m3u8");
            exit;
        }

        $proxiedBody = preg_replace_callback('/(\/(?:hlsr|hls|live)\/[^#\s"]+)/i', function ($matches) use ($host, $port) {
            return "http://$host$port" . $matches[1];
        }, $redirectedBody);

        file_put_contents($cache_file, $proxiedBody);

        header("Content-Type: application/vnd.apple.mpegurl");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        echo $proxiedBody;
        exit;
    } else {
        file_put_contents($cache_file, $body);
        header("Content-Type: application/vnd.apple.mpegurl");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        echo $body;
        exit;
    }
}
?>
