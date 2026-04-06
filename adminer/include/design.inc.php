<?php
namespace Adminer;

/** Print HTML header
* @param string $title used in title, breadcrumb and heading, should be HTML escaped
* @param mixed $breadcrumb ["key" => "link", "key2" => ["link", "desc"]], null for nothing, false for driver only, true for driver and server
* @param string $title2 used after colon in title and heading, should be HTML escaped
*/
function page_header(string $title, string $error = "", $breadcrumb = array(), string $title2 = ""): void {
	page_headers();
	if (is_ajax() && $error) {
		page_messages($error);
		exit;
	}
	if (!ob_get_level()) {
		ob_start('ob_gzhandler', 4096);
	}
	$title_all = $title . ($title2 != "" ? ": $title2" : "");
	$title_page = strip_tags($title_all . (SERVER != "" && SERVER != "localhost" ? h(" - " . SERVER) : "") . " - " . adminer()->name());
	// initial-scale=1 is the default but Chrome 134 on iOS is not able to zoom out without it
	?>
<!DOCTYPE html>
<html lang="<?php echo LANG; ?>" dir="<?php echo lang('ltr'); ?>">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="robots" content="noindex">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo $title_page; ?></title>
<link rel="stylesheet" href="../adminer/static/default.css">
<?php

	$css = adminer()->css();
	if (is_int(key($css))) { // legacy return value
		$css = array_fill_keys($css, 'light');
	}
	$has_light = in_array('light', $css) || in_array('', $css);
	$has_dark = in_array('dark', $css) || in_array('', $css);
	$dark = ($has_light
		? ($has_dark ? null : false) // both styles - autoswitching, only adminer.css - light
		: ($has_dark ?: null) // only adminer-dark.css - dark, neither - autoswitching
	);
	$media = " media='(prefers-color-scheme: dark)'";
	if ($dark !== false) {
		echo "<link rel='stylesheet'" . ($dark ? "" : $media) . " href='../adminer/static/dark.css'>\n";
	}
	echo "<meta name='color-scheme' content='" . ($dark === null ? "light dark" : ($dark ? "dark" : "light")) . "'>\n";

	// this is matched by compile.php
	echo script_src("../adminer/static/functions.js");
	echo script_src("static/editing.js");
	if (adminer()->head($dark)) {
		echo "<link rel='icon' href='data:image/gif;base64,R0lGODlhEAAQAJEAAAQCBPz+/PwCBAROZCH5BAEAAAAALAAAAAAQABAAAAI2hI+pGO1rmghihiUdvUBnZ3XBQA7f05mOak1RWXrNq5nQWHMKvuoJ37BhVEEfYxQzHjWQ5qIAADs='>\n";
		echo "<link rel='apple-touch-icon' href='../adminer/static/logo.png'>\n";
	}
	foreach ($css as $url => $mode) {
		$attrs = ($mode == 'dark' && !$dark
			? $media
			: ($mode == 'light' && $has_dark ? " media='(prefers-color-scheme: light)'" : "")
		);
		echo "<link rel='stylesheet'$attrs href='" . h($url) . "'>\n";
	}
	echo "\n<body class='" . lang('ltr') . " nojs";
	adminer()->bodyClass();
	echo "'>\n";
	$filename = get_temp_dir() . "/adminer.version";
	echo script("mixin(document.body, {onkeydown: bodyKeydown, onclick: bodyClick"
		. (isset($_COOKIE["adminer_version"]) ? "" : ", onload: partial(verifyVersion, '" . VERSION . "')")
		. "});
document.body.classList.replace('nojs', 'js');
const offlineMessage = '" . js_escape(lang('You are offline.')) . "';
const thousandsSeparator = '" . js_escape(lang(',')) . "';")
	;
	echo "<div id='help' class='jush-" . JUSH . " jsonly hidden'></div>\n";
	echo script("mixin(qs('#help'), {onmouseover: () => { helpOpen = 1; }, onmouseout: helpMouseout});");
	echo "<div id='content'>\n";
	echo "<span id='menuopen' class='jsonly'>" . icon("move", "", "menu", "") . "</span>" . script("qs('#menuopen').onclick = event => { qs('#foot').classList.toggle('foot'); event.stopPropagation(); }");
	if ($breadcrumb !== null) {
		$link = substr(preg_replace('~\b(username|db|ns)=[^&]*&~', '', ME), 0, -1);
		echo '<p id="breadcrumb"><a href="' . h($link ?: ".") . '">' . get_driver(DRIVER) . '</a> » ';
		$link = substr(preg_replace('~\b(db|ns)=[^&]*&~', '', ME), 0, -1);
		$server = adminer()->serverName(SERVER);
		$server = ($server != "" ? $server : lang('Server'));
		if ($breadcrumb === false) {
			echo "$server\n";
		} else {
			echo "<a href='" . h($link) . "' accesskey='1' title='Alt+Shift+1'>$server</a> » ";
			if ($_GET["ns"] != "" || (DB != "" && is_array($breadcrumb))) {
				echo '<a href="' . h($link . "&db=" . urlencode(DB) . (support("scheme") ? "&ns=" : "")) . '">' . h(DB) . '</a> » ';
			}
			if (is_array($breadcrumb)) {
				if ($_GET["ns"] != "") {
					echo '<a href="' . h(substr(ME, 0, -1)) . '">' . h($_GET["ns"]) . '</a> » ';
				}
				foreach ($breadcrumb as $key => $val) {
					$desc = (is_array($val) ? $val[1] : h($val));
					if ($desc != "") {
						echo "<a href='" . h(ME . "$key=") . urlencode(is_array($val) ? $val[0] : $val) . "'>$desc</a> » ";
					}
				}
			}
			echo "$title\n";
		}
	}
	echo "<h2>$title_all</h2>\n";
	echo "<div id='ajaxstatus' class='jsonly hidden'></div>\n";
	restart_session();
	page_messages($error);
	$databases = &get_session("dbs");
	if (DB != "" && $databases && !in_array(DB, $databases, true)) {
		$databases = null;
	}
	stop_session();
	define('Adminer\PAGE_HEADER', 1);
}

/** Send HTTP headers */
function page_headers(): void {
	header("Content-Type: text/html; charset=utf-8");
	header("Cache-Control: no-cache");
	header("X-Frame-Options: deny"); // ClickJacking protection in IE8, Safari 4, Chrome 2, Firefox 3.6.9
	header("X-XSS-Protection: 0"); // prevents introducing XSS in IE8 by removing safe parts of the page
	header("X-Content-Type-Options: nosniff");
	header("Referrer-Policy: origin-when-cross-origin");
	foreach (adminer()->csp(csp()) as $csp) {
		$header = array();
		foreach ($csp as $key => $val) {
			$header[] = "$key $val";
		}
		header("Content-Security-Policy: " . implode("; ", $header));
	}
	adminer()->headers();
}

/** Get Content Security Policy headers
* @return list<string[]> of arrays with directive name in key, allowed sources in value
*/
function csp(): array {
	return array(
		array(
			"script-src" => "'self' 'unsafe-inline' 'nonce-" . get_nonce() . "' 'strict-dynamic'", // 'self' is a fallback for browsers not supporting 'strict-dynamic', 'unsafe-inline' is a fallback for browsers not supporting 'nonce-'
			"connect-src" => "'self' https://www.adminer.org",
			"frame-src" => "'none'",
			"object-src" => "'none'",
			"base-uri" => "'none'",
			"form-action" => "'self'",
		),
	);
}

/** Get a CSP nonce
* @return string Base64 value
*/
function get_nonce(): string {
	static $nonce;
	if (!$nonce) {
		$nonce = base64_encode(rand_string());
	}
	return $nonce;
}

/** Print flash and error messages */
function page_messages(string $error): void {
	$uri = preg_replace('~^[^?]*~', '', $_SERVER["REQUEST_URI"]);
	$messages = idx($_SESSION["messages"], $uri);
	if ($messages) {
		echo "<div class='message'>" . implode("</div>\n<div class='message'>", $messages) . "</div>" . script("messagesPrint();");
		unset($_SESSION["messages"][$uri]);
	}
	if ($error) {
		echo "<div class='error'>$error</div>\n";
	}
	if (adminer()->error) { // separate <div>
		echo "<div class='error'>" . adminer()->error . "</div>\n";
	}
}

/** Print HTML footer
* @param ''|'auth'|'db'|'ns' $missing
*/
function page_footer(string $missing = ""): void {
	echo "</div>\n\n<div id='foot' class='foot'>\n<div id='menu'>\n";
	adminer()->navigation($missing);
	echo "</div>\n";
	if ($missing != "auth") {
		?>
<form action="" method="post">
<p class="logout">
<span><?php echo h($_GET["username"]) . "\n"; ?></span>
<input type="submit" name="logout" value="<?php echo lang('Logout'); ?>" id="logout">
<?php echo input_token(); ?>
</form>
<?php
	}
	echo "</div>\n\n";
	echo script("setupSubmitHighlight(document);");
	echo script("
(function() {
	var chars = 'qwertasdfzxcvbyuophklnm'; // left-hand first, g and i removed to avoid Vimium-C conflicts
	var len = chars.length;
	var maxLabels = len * len; // 676
	function makeLabel(i) {
		return chars[Math.floor(i / len)] + chars[i % len];
	}
	var BADGE = 'display:inline-block;font-family:monospace;font-size:1.1rem;font-weight:bold;color:#000;background:#f5e642;border:1px solid #bba;border-radius:3px;padding:0 2px;margin:0 2px 0 0;min-width:1.3em;text-align:center;cursor:default;line-height:1;vertical-align:middle;position:relative;z-index:9999;';

	var map = {};
	var idx = 0;
	var buf = '';
	var labelling = false;

	var SELECTOR = 'a[href], input[type=submit], input[type=button], input[type=reset], button, input[type=checkbox], input[type=radio], input[type=text], input:not([type]), input[type=password], input[type=number], input[type=search], textarea, select';

	function isAlreadyLabelled(el) {
		var prev = el.previousSibling;
		while (prev && prev.nodeType === 3) prev = prev.previousSibling;
		return prev && prev.nodeType === 1 && prev.className === 'hint';
	}

	function labelElement(el) {
		if (idx >= maxLabels) return;
		if (isAlreadyLabelled(el)) return;
		var type = (el.type || '').toLowerCase();
		if (type === 'hidden') return;
		if (el.tagName === 'A') {
			var href = el.href;
			if (!href || el.getAttribute('href') === '#') return;
		}
		var label = makeLabel(idx++);
		var span = document.createElement('span');
		span.className = 'hint';
		span.textContent = label;
		span.style.cssText = BADGE;
		el.parentNode.insertBefore(span, el);
		map[label] = el;
	}

	function labelAll(root) {
		labelling = true;
		(root || document).querySelectorAll(SELECTOR).forEach(labelElement);
		labelling = false;
	}

	function updateHighlight(prefix) {
		document.querySelectorAll('span.hint').forEach(function(s) {
			s.style.background = (prefix && s.textContent.indexOf(prefix) === 0) ? '#ff9800' : '#f5e642';
		});
	}

	function activate(el) {
		var type = (el.type || '').toLowerCase();
		if (el.tagName === 'A') {
			el.click();
		} else if (type === 'submit' || type === 'button' || type === 'reset' || el.tagName === 'BUTTON') {
			el.click();
		} else if (type === 'checkbox' || type === 'radio') {
			el.click();
		} else {
			el.focus();
		}
	}

	document.addEventListener('keydown', function(e) {
		var t = e.target;
		if (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT') return;
		if (e.key.length === 1 && chars.indexOf(e.key) !== -1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
			buf += e.key;
			updateHighlight(buf);
			if (buf.length === 2) {
				var el = map[buf];
				buf = '';
				updateHighlight('');
				if (el) activate(el);
			}
			e.preventDefault();
			e.stopPropagation();
		} else if (e.key === 'Escape') {
			buf = '';
			updateHighlight('');
		}
	}, true);

	var observer = new MutationObserver(function(mutations) {
		if (labelling) return;
		mutations.forEach(function(m) {
			m.addedNodes.forEach(function(node) {
				if (node.nodeType !== 1 || node.className === 'hint') return;
				labelling = true;
				if (node.matches && node.matches(SELECTOR)) labelElement(node);
				node.querySelectorAll(SELECTOR).forEach(labelElement);
				labelling = false;
			});
		});
	});

	// tableClick uses tr.firstChild.firstChild to find the checkbox.
	// Our badge span is now td.firstChild, so we patch trCheck to
	// skip non-input elements and find the actual checkbox.
	if (typeof trCheck === 'function') {
		var _trCheck = trCheck;
		trCheck = function(el) {
			if (el && el.tagName !== 'INPUT' && el.tagName !== 'SELECT') {
				var input = el.parentNode && el.parentNode.querySelector('input[type=checkbox]');
				if (input) el = input;
			}
			return _trCheck(el);
		};
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			labelAll();
			observer.observe(document.body, {childList: true, subtree: true});
		});
	} else {
		labelAll();
		observer.observe(document.body, {childList: true, subtree: true});
	}
})();
");
}
