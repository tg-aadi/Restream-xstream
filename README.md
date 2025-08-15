# 📺 PHP IPTV Restream Proxy Script

A PHP-based IPTV stream proxy that supports both **TS** and **M3U8** streaming formats.  
It can be used to relay IPTV streams from a given server to end users, while customizing headers and optionally caching M3U8 manifests for better performance.

---

## 🚀 Features

- ✅ Supports both `.ts` and `.m3u8` streaming.
- ✅ Custom User-Agent and headers.
- ✅ Optional **5-second caching** for `.m3u8` playlists to reduce origin hits.
- ✅ Redirect handling for streams that move to another host.
- ✅ Prevents direct client IP leakage to the origin server.
- ✅ Works with most Xtream Codes-based IPTV servers.
- ✅ For `.ts` Access like this 'http://your-domain/stream.php/$id.ts
- ✅ For `.m3u8` Access like this 'http://your-domain/stream.php/$id.m3u8
 
---

## ✨ Credits

Script developed by "[@tg_aadi](https://t.me/tg_aadi)"
 https://chatgpt.com/#:~:text=%5B%40tg_aadi%5D(https%3A//t.me/tg_aadi)

IPTV proxy logic for Xtream Codes-based servers

---

## ⚙ Configuration

Edit the **configuration section** at the top of the script:

```php
// ============ ⚙ CONFIGURATION ============
$hostname = '';  // IPTV server host and port
$username = '';        // IPTV account username
$password = '';        // IPTV account password
$user_agent = 'Mozilla/5.0 ...'; // User-Agent to send to the IPTV server
// =========================================
