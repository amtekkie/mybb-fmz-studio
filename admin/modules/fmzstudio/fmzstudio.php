<?php
/**
 * FMZ Studio -- Admin Module
 *
 * Handles all FMZ Studio pages: Manage, Import, Export, Global FMZ Options, Header & Footer, Editor.
 * Registered as a top-level admin module via module_meta.php.
 *
 * @version 2.0.0
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

require_once MYBB_ROOT . 'inc/plugins/fmzstudio/core.php';
require_once MYBB_ADMIN_DIR . 'inc/functions_themes.php';

global $mybb, $db, $page, $lang, $cache, $plugins;

$fmz = new FMZStudio();

// Load Bootstrap Icons on all FMZ Studio pages
$page->extra_header .= '<link rel="stylesheet" href="../themes/fmz-default/vendor/bootstrap-icons.min.css" />';

/* ====================================================================
   Toolbar Builder Renderer (graphical drag & drop for toolbar config)
   ==================================================================== */

function fmz_render_toolbar_builder($id, $currentValue)
{
    // All available buttons with their Bootstrap Icons and labels
    $allButtons = array(
        'bold'          => array('icon' => 'bi-type-bold',          'label' => 'Bold'),
        'italic'        => array('icon' => 'bi-type-italic',        'label' => 'Italic'),
        'underline'     => array('icon' => 'bi-type-underline',     'label' => 'Underline'),
        'strikethrough' => array('icon' => 'bi-type-strikethrough', 'label' => 'Strikethrough'),
        'fontFamily'    => array('icon' => 'bi-fonts',              'label' => 'Font Family'),
        'fontSize'      => array('icon' => 'bi-text-paragraph',     'label' => 'Font Size'),
        'fontColor'     => array('icon' => 'bi-palette',            'label' => 'Text Color'),
        'highlight'     => array('icon' => 'bi-paint-bucket',       'label' => 'Highlight'),
        'alignLeft'     => array('icon' => 'bi-text-left',          'label' => 'Align Left'),
        'alignCenter'   => array('icon' => 'bi-text-center',        'label' => 'Center'),
        'alignRight'    => array('icon' => 'bi-text-right',         'label' => 'Align Right'),
        'alignJustify'  => array('icon' => 'bi-justify',            'label' => 'Justify'),
        'bulletList'    => array('icon' => 'bi-list-ul',            'label' => 'Bullet List'),
        'numberedList'  => array('icon' => 'bi-list-ol',            'label' => 'Numbered List'),
        'indent'        => array('icon' => 'bi-text-indent-left',   'label' => 'Indent'),
        'outdent'       => array('icon' => 'bi-text-indent-right',  'label' => 'Outdent'),
        'link'          => array('icon' => 'bi-link-45deg',         'label' => 'Link'),
        'image'         => array('icon' => 'bi-image',              'label' => 'Image'),
        'video'         => array('icon' => 'bi-camera-video',       'label' => 'Video'),
        'table'         => array('icon' => 'bi-table',              'label' => 'Table'),
        'emoji'         => array('icon' => 'bi-emoji-smile',        'label' => 'Emoji'),
        'gif'           => array('icon' => 'bi-filetype-gif',       'label' => 'GIF'),
        'quote'         => array('icon' => 'bi-chat-quote',         'label' => 'Quote'),
        'code'          => array('icon' => 'bi-code-slash',         'label' => 'Code'),
        'formula'       => array('icon' => 'bi-calculator',         'label' => 'Formula'),
        'hr'            => array('icon' => 'bi-dash-lg',            'label' => 'Horiz. Rule'),
        'removeFormat'  => array('icon' => 'bi-eraser',             'label' => 'Clear Format'),
        'undo'          => array('icon' => 'bi-arrow-counterclockwise', 'label' => 'Undo'),
        'redo'          => array('icon' => 'bi-arrow-clockwise',    'label' => 'Redo'),
        'saveDraft'     => array('icon' => 'bi-floppy',             'label' => 'Save Draft'),
        'source'        => array('icon' => 'bi-code-square',        'label' => 'Source'),
    );

    // Parse current value into active items
    $activeParts = array_map('trim', explode(',', $currentValue));
    $activeIds = array();
    foreach ($activeParts as $p) {
        if ($p !== '' && ($p === '|' || isset($allButtons[$p]))) {
            $activeIds[] = $p;
        }
    }

    // Available = all buttons NOT in active
    $usedIds = array_filter($activeIds, function ($x) { return $x !== '|'; });
    $availableIds = array();
    foreach ($allButtons as $btnId => $btn) {
        if (!in_array($btnId, $usedIds)) {
            $availableIds[] = $btnId;
        }
    }

    $fieldName = htmlspecialchars_uni('opt_' . $id);

    // Build the chip HTML helper
    $chipHtml = function ($btnId, $info = null) {
        if ($btnId === '|') {
            return '<span class="fmz-tb-chip fmz-tb-sep" draggable="true" data-id="|" title="Separator">'
                 . '<i class="bi bi-grip-vertical" style="opacity:.4"></i> |</span>';
        }
        if (!$info) return '';
        $icon  = htmlspecialchars_uni($info['icon']);
        $label = htmlspecialchars_uni($info['label']);
        $bid   = htmlspecialchars_uni($btnId);
        return '<span class="fmz-tb-chip" draggable="true" data-id="' . $bid . '" title="' . $label . '">'
             . '<i class="bi ' . $icon . '"></i> ' . $label . '</span>';
    };

    $html = '<input type="hidden" name="' . $fieldName . '" id="fmz-tb-value" value="' . htmlspecialchars_uni($currentValue) . '" />';

    // Styles
    $html .= '<style>
.fmz-tb-builder{display:flex;gap:12px;margin-top:6px}
.fmz-tb-panel{flex:1;border:1px solid #ddd;border-radius:6px;background:#fafafa;min-height:120px}
.fmz-tb-panel-head{padding:6px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center}
.fmz-tb-panel-body{padding:6px;display:flex;flex-wrap:wrap;gap:4px;min-height:80px}
.fmz-tb-chip{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#fff;border:1px solid #ccc;border-radius:4px;font-size:12px;cursor:grab;user-select:none;transition:background .12s,border-color .12s,box-shadow .12s}
.fmz-tb-chip:hover{background:#e8f5e9;border-color:#0d9488}
.fmz-tb-chip.fmz-tb-sep{background:#f5f5f5;color:#999;font-weight:700}
.fmz-tb-chip.fmz-tb-dragover{border-color:#0d9488;box-shadow:-2px 0 0 #0d9488}
.fmz-tb-chip i{font-size:14px}
.fmz-tb-panel-body.fmz-tb-dragover-zone{background:#e0f2f1;border-color:#0d9488}
.fmz-tb-add-sep{background:none;border:1px dashed #aaa;border-radius:4px;padding:4px 10px;font-size:11px;color:#888;cursor:pointer;white-space:nowrap}
.fmz-tb-add-sep:hover{border-color:#0d9488;color:#0d9488}
</style>';

    // Active panel
    $html .= '<div class="fmz-tb-builder">';
    $html .= '<div class="fmz-tb-panel">';
    $html .= '<div class="fmz-tb-panel-head">Active Toolbar <button type="button" class="fmz-tb-add-sep" id="fmz-tb-add-sep" title="Add Separator">+ Separator</button></div>';
    $html .= '<div class="fmz-tb-panel-body" id="fmz-tb-active">';
    foreach ($activeIds as $aid) {
        if ($aid === '|') {
            $html .= $chipHtml('|');
        } elseif (isset($allButtons[$aid])) {
            $html .= $chipHtml($aid, $allButtons[$aid]);
        }
    }
    $html .= '</div></div>';

    // Available panel
    $html .= '<div class="fmz-tb-panel">';
    $html .= '<div class="fmz-tb-panel-head">Available Buttons</div>';
    $html .= '<div class="fmz-tb-panel-body" id="fmz-tb-available">';
    foreach ($availableIds as $aid) {
        $html .= $chipHtml($aid, $allButtons[$aid]);
    }
    $html .= '</div></div>';
    $html .= '</div>';

    // JavaScript for drag & drop
    $html .= '<script>
(function(){
    var activeEl = document.getElementById("fmz-tb-active");
    var availEl  = document.getElementById("fmz-tb-available");
    var hiddenInput = document.getElementById("fmz-tb-value");
    var dragItem = null;

    function syncValue() {
        var chips = activeEl.querySelectorAll(".fmz-tb-chip");
        var ids = [];
        chips.forEach(function(c){ ids.push(c.getAttribute("data-id")); });
        hiddenInput.value = ids.join(",");
    }

    function bindChip(chip) {
        chip.addEventListener("dragstart", function(e) {
            dragItem = chip;
            chip.style.opacity = ".4";
            e.dataTransfer.effectAllowed = "move";
        });
        chip.addEventListener("dragend", function() {
            chip.style.opacity = "1";
            dragItem = null;
            document.querySelectorAll(".fmz-tb-dragover").forEach(function(el){ el.classList.remove("fmz-tb-dragover"); });
            document.querySelectorAll(".fmz-tb-dragover-zone").forEach(function(el){ el.classList.remove("fmz-tb-dragover-zone"); });
        });
        chip.addEventListener("dragover", function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = "move";
            chip.classList.add("fmz-tb-dragover");
        });
        chip.addEventListener("dragleave", function() {
            chip.classList.remove("fmz-tb-dragover");
        });
        chip.addEventListener("drop", function(e) {
            e.preventDefault();
            chip.classList.remove("fmz-tb-dragover");
            if (!dragItem || dragItem === chip) return;
            var parent = chip.parentNode;
            var chips = Array.from(parent.children);
            var dragIdx = chips.indexOf(dragItem);
            var dropIdx = chips.indexOf(chip);
            if (dragItem.parentNode !== parent) {
                parent.insertBefore(dragItem, chip);
            } else if (dragIdx < dropIdx) {
                parent.insertBefore(dragItem, chip.nextSibling);
            } else {
                parent.insertBefore(dragItem, chip);
            }
            syncValue();
        });
        // Double-click to move between panels
        chip.addEventListener("dblclick", function() {
            var currentPanel = chip.parentNode;
            if (currentPanel === activeEl) {
                if (chip.getAttribute("data-id") === "|") {
                    chip.remove();
                } else {
                    availEl.appendChild(chip);
                }
            } else {
                activeEl.appendChild(chip);
            }
            syncValue();
        });
    }

    function bindPanel(panel) {
        panel.addEventListener("dragover", function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = "move";
            panel.classList.add("fmz-tb-dragover-zone");
        });
        panel.addEventListener("dragleave", function(e) {
            if (!panel.contains(e.relatedTarget)) {
                panel.classList.remove("fmz-tb-dragover-zone");
            }
        });
        panel.addEventListener("drop", function(e) {
            e.preventDefault();
            panel.classList.remove("fmz-tb-dragover-zone");
            if (!dragItem) return;
            // Only append if dropped on the panel itself (not on a chip)
            if (e.target === panel) {
                panel.appendChild(dragItem);
                syncValue();
            }
        });
    }

    document.querySelectorAll(".fmz-tb-chip").forEach(bindChip);
    bindPanel(activeEl);
    bindPanel(availEl);

    document.getElementById("fmz-tb-add-sep").addEventListener("click", function() {
        var sep = document.createElement("span");
        sep.className = "fmz-tb-chip fmz-tb-sep";
        sep.draggable = true;
        sep.setAttribute("data-id", "|");
        sep.title = "Separator";
        sep.innerHTML = \'<i class="bi bi-grip-vertical" style="opacity:.4"></i> |\';
        activeEl.appendChild(sep);
        bindChip(sep);
        syncValue();
    });
})();
</script>';

    return $html;
}

// Determine action: explicit query param > module routing > default
$action = $mybb->get_input('action');
if (empty($action)) {
    // MyBB admin passes the action via the module param (e.g. fmzstudio-plugin_settings)
    // but does NOT store it in $mybb->input['action']. Extract it from the module param.
    $moduleParts = explode('-', $mybb->get_input('module'), 2);
    if (!empty($moduleParts[1])) {
        $action = $moduleParts[1];
    }
}
if (empty($action)) {
    $action = 'manage';
}

/* ====================================================================
   License Gate — require a valid license before any other action
   ==================================================================== */

require_once MYBB_ROOT . 'inc/plugins/fmzstudio/license.php';
FMZLicense::ensureSettings();   // migration safety — creates rows if missing

if ($action !== 'license') {
    // Primary gate — encrypted license validation
    if (!FMZLicense::isValid()) {
        admin_redirect("index.php?module=fmzstudio-license");
        exit;
    }
    // Integrity gate — detect tampering with the license file itself
    $__fmz_ih = FMZLicense::integrityHash();
    if (empty($__fmz_ih)) {
        admin_redirect("index.php?module=fmzstudio-license");
        exit;
    }
}

/* ====================================================================
   Shared Button Styles (used on Manage, Plugins, etc.)
   ==================================================================== */

$btnStyle        = 'display:inline-flex;align-items:center;gap:5px;padding:5px 13px;background:#333;color:#ccc;border:1px solid #555;border-radius:5px;font-size:12px;font-weight:500;text-decoration:none;cursor:pointer;white-space:nowrap;transition:all .15s ease';
$btnStyleSuccess = 'display:inline-flex;align-items:center;gap:5px;padding:5px 13px;background:linear-gradient(135deg,#0d9488 0%,#0b7c72 100%);color:#fff;border:1px solid #0a6e64;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;cursor:pointer;white-space:nowrap;transition:all .15s ease;box-shadow:0 1px 3px rgba(0,0,0,.12)';
$btnStyleDanger  = 'display:inline-flex;align-items:center;gap:5px;padding:5px 13px;background:linear-gradient(135deg,#c0392b 0%,#a5301f 100%);color:#fff;border:1px solid #8e2518;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;cursor:pointer;white-space:nowrap;transition:all .15s ease;box-shadow:0 1px 3px rgba(0,0,0,.12)';
$btnStyleWarn    = 'display:inline-flex;align-items:center;gap:5px;padding:5px 13px;background:linear-gradient(135deg,#e67e22 0%,#d35400 100%);color:#fff;border:1px solid #bf4b00;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;cursor:pointer;white-space:nowrap;transition:all .15s ease;box-shadow:0 1px 3px rgba(0,0,0,.12)';

$fmzBtnHoverCss = '<style>
.fmz-btn:hover { filter:brightness(1.15); box-shadow:0 2px 6px rgba(0,0,0,.2) !important; transform:translateY(-1px); }
.fmz-btn:active { filter:brightness(.92); transform:translateY(0); box-shadow:0 1px 2px rgba(0,0,0,.1) !important; }
</style>';

/* ====================================================================
   Inline Help Box Helper
   ==================================================================== */

function fmz_help_box($title, $body) {
    return '<div style="margin-top:16px;margin-bottom:16px;border:1px solid #3a7d76;border-radius:6px;background:linear-gradient(135deg,#f0faf9 0%,#f8fffe 100%);overflow:hidden">'
         . '<div style="display:flex;align-items:center;gap:8px;padding:10px 14px">'
         . '<i class="bi bi-info-circle" style="color:#0d9488;font-size:15px"></i>'
         . '<strong style="color:#0b7c72;font-size:13px">' . $title . '</strong></div>'
         . '<div style="padding:0 16px 14px;font-size:12.5px;line-height:1.7;color:#444">' . $body . '</div>'
         . '</div>';
}

/**
 * Render a single nav-link repeater row (used in the admin UI).
 */
function fmz_nav_link_row($index, $link = array())
{
    $text = isset($link['text']) ? htmlspecialchars_uni($link['text']) : '';
    $url  = isset($link['url'])  ? htmlspecialchars_uni($link['url'])  : '';
    $icon = isset($link['icon']) ? htmlspecialchars_uni($link['icon']) : '';
    return '<tr class="fmz-nav-row">'
        . '<td><input type="text" class="fmz-nav-text" value="' . $text . '" placeholder="Link text" style="width:100%;padding:4px 6px;font-size:12px;border:1px solid #ddd;border-radius:3px" /></td>'
        . '<td><input type="text" class="fmz-nav-url" value="' . $url . '" placeholder="https://..." style="width:100%;padding:4px 6px;font-size:12px;border:1px solid #ddd;border-radius:3px" /></td>'
        . '<td>'
        . '<input type="hidden" class="fmz-nav-icon" value="' . $icon . '" />'
        . '<button type="button" class="fmz-icon-pick-btn" data-target-type="nav" style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;font-size:12px;border:1px solid #ddd;border-radius:4px;background:#fafafa;cursor:pointer">'
        . '<i class="bi ' . ($icon ?: 'bi-grid-3x3-gap') . ' fmz-nav-icon-preview"></i> <span class="fmz-icon-label">' . ($icon ?: 'Choose icon') . '</span></button>'
        . '</td>'
        . '<td style="text-align:center"><button type="button" class="fmz-nav-del" style="background:none;border:none;color:#e74c3c;cursor:pointer;font-size:14px" title="Remove"><i class="bi bi-trash"></i></button></td>'
        . '</tr>';
}

/**
 * Return the master icon list as an associative array.
 * Keys are Bootstrap Icon class names, values are human-readable labels.
 * Grouped by category for the modal grid.
 */
function fmz_get_icon_list()
{
    return array(
        // ── Social / Brand ──
        'bi-discord' => 'Discord', 'bi-youtube' => 'YouTube', 'bi-twitch' => 'Twitch',
        'bi-twitter-x' => 'Twitter / X', 'bi-facebook' => 'Facebook', 'bi-instagram' => 'Instagram',
        'bi-tiktok' => 'TikTok', 'bi-reddit' => 'Reddit', 'bi-github' => 'GitHub',
        'bi-steam' => 'Steam', 'bi-linkedin' => 'LinkedIn', 'bi-whatsapp' => 'WhatsApp',
        'bi-telegram' => 'Telegram', 'bi-snapchat' => 'Snapchat', 'bi-pinterest' => 'Pinterest',
        'bi-paypal' => 'PayPal', 'bi-spotify' => 'Spotify', 'bi-mastodon' => 'Mastodon',
        'bi-threads' => 'Threads', 'bi-wechat' => 'WeChat',
        // ── Navigation / UI ──
        'bi-house' => 'Home', 'bi-house-door' => 'Home Door', 'bi-search' => 'Search',
        'bi-list' => 'List', 'bi-grid' => 'Grid', 'bi-grid-3x3-gap' => 'Grid 3x3',
        'bi-three-dots' => 'More', 'bi-three-dots-vertical' => 'More Vert',
        'bi-arrow-left' => 'Arrow Left', 'bi-arrow-right' => 'Arrow Right',
        'bi-arrow-up' => 'Arrow Up', 'bi-arrow-down' => 'Arrow Down',
        'bi-chevron-left' => 'Chevron Left', 'bi-chevron-right' => 'Chevron Right',
        'bi-box-arrow-up-right' => 'External Link', 'bi-link-45deg' => 'Link',
        'bi-link' => 'Link Chain', 'bi-signpost' => 'Signpost',
        // ── Communication ──
        'bi-chat' => 'Chat', 'bi-chat-dots' => 'Chat Dots', 'bi-chat-left-text' => 'Chat Text',
        'bi-chat-square' => 'Chat Square', 'bi-envelope' => 'Email', 'bi-envelope-open' => 'Email Open',
        'bi-telephone' => 'Phone', 'bi-telephone-fill' => 'Phone Fill',
        'bi-megaphone' => 'Megaphone', 'bi-broadcast' => 'Broadcast',
        'bi-bell' => 'Bell', 'bi-bell-fill' => 'Bell Fill',
        'bi-reply' => 'Reply', 'bi-send' => 'Send',
        // ── Content / Media ──
        'bi-image' => 'Image', 'bi-images' => 'Images', 'bi-camera' => 'Camera',
        'bi-camera-video' => 'Video Camera', 'bi-film' => 'Film', 'bi-play-circle' => 'Play',
        'bi-music-note' => 'Music', 'bi-music-note-list' => 'Playlist',
        'bi-mic' => 'Microphone', 'bi-headphones' => 'Headphones',
        'bi-newspaper' => 'News', 'bi-journal-text' => 'Journal',
        'bi-book' => 'Book', 'bi-bookmark' => 'Bookmark', 'bi-bookmark-star' => 'Bookmark Star',
        'bi-file-earmark-text' => 'Document', 'bi-file-earmark-pdf' => 'PDF',
        'bi-file-earmark-code' => 'Code File', 'bi-file-earmark-zip' => 'ZIP File',
        'bi-folder' => 'Folder', 'bi-folder-fill' => 'Folder Fill',
        // ── Actions ──
        'bi-download' => 'Download', 'bi-upload' => 'Upload',
        'bi-cloud-download' => 'Cloud Download', 'bi-cloud-upload' => 'Cloud Upload',
        'bi-pencil' => 'Edit', 'bi-pencil-square' => 'Edit Square',
        'bi-trash' => 'Delete', 'bi-trash3' => 'Delete Alt',
        'bi-plus-circle' => 'Add', 'bi-dash-circle' => 'Remove',
        'bi-plus-lg' => 'Plus', 'bi-x-lg' => 'Close',
        'bi-check-lg' => 'Check', 'bi-check-circle' => 'Check Circle',
        'bi-check2-all' => 'Double Check', 'bi-x-circle' => 'X Circle',
        'bi-eye' => 'View', 'bi-eye-slash' => 'Hide',
        'bi-clipboard' => 'Clipboard', 'bi-copy' => 'Copy',
        'bi-share' => 'Share', 'bi-share-fill' => 'Share Fill',
        'bi-pin' => 'Pin', 'bi-pin-angle' => 'Pin Angle',
        // ── Commerce ──
        'bi-cart' => 'Cart', 'bi-cart-fill' => 'Cart Fill', 'bi-bag' => 'Bag',
        'bi-shop' => 'Shop', 'bi-shop-window' => 'Shop Window',
        'bi-coin' => 'Coin', 'bi-cash-stack' => 'Cash',
        'bi-credit-card' => 'Credit Card', 'bi-wallet' => 'Wallet',
        'bi-gift' => 'Gift', 'bi-gift-fill' => 'Gift Fill',
        'bi-receipt' => 'Receipt', 'bi-tag' => 'Tag', 'bi-tags' => 'Tags',
        // ── People / Users ──
        'bi-person' => 'Person', 'bi-person-fill' => 'Person Fill',
        'bi-people' => 'People', 'bi-people-fill' => 'People Fill',
        'bi-person-plus' => 'Add User', 'bi-person-check' => 'Verified User',
        'bi-person-badge' => 'Badge User', 'bi-person-circle' => 'Avatar',
        // ── Status / Info ──
        'bi-info-circle' => 'Info', 'bi-question-circle' => 'Help',
        'bi-exclamation-triangle' => 'Warning', 'bi-exclamation-circle' => 'Alert',
        'bi-shield-check' => 'Shield Check', 'bi-shield-lock' => 'Shield Lock',
        'bi-lock' => 'Lock', 'bi-unlock' => 'Unlock',
        'bi-key' => 'Key', 'bi-fingerprint' => 'Fingerprint',
        'bi-flag' => 'Flag', 'bi-flag-fill' => 'Flag Fill',
        'bi-patch-check' => 'Verified', 'bi-award' => 'Award',
        'bi-trophy' => 'Trophy', 'bi-trophy-fill' => 'Trophy Fill',
        'bi-star' => 'Star', 'bi-star-fill' => 'Star Fill',
        'bi-heart' => 'Heart', 'bi-heart-fill' => 'Heart Fill',
        'bi-hand-thumbs-up' => 'Thumbs Up', 'bi-hand-thumbs-down' => 'Thumbs Down',
        'bi-emoji-smile' => 'Smile', 'bi-emoji-heart-eyes' => 'Heart Eyes',
        // ── Technology ──
        'bi-cpu' => 'CPU', 'bi-cpu-fill' => 'CPU Fill',
        'bi-gpu-card' => 'GPU', 'bi-motherboard' => 'Motherboard',
        'bi-code-slash' => 'Code', 'bi-terminal' => 'Terminal',
        'bi-bug' => 'Bug', 'bi-braces' => 'Braces',
        'bi-database' => 'Database', 'bi-server' => 'Server',
        'bi-hdd' => 'Hard Drive', 'bi-usb-drive' => 'USB',
        'bi-wifi' => 'WiFi', 'bi-bluetooth' => 'Bluetooth',
        'bi-globe' => 'Globe', 'bi-globe2' => 'Globe Alt',
        'bi-controller' => 'Controller', 'bi-joystick' => 'Joystick',
        'bi-headset' => 'Headset', 'bi-headset-vr' => 'VR Headset',
        'bi-phone' => 'Phone Device', 'bi-laptop' => 'Laptop', 'bi-display' => 'Monitor',
        'bi-printer' => 'Printer', 'bi-router' => 'Router',
        // ── General / Misc ──
        'bi-gear' => 'Gear', 'bi-gear-fill' => 'Gear Fill',
        'bi-tools' => 'Tools', 'bi-wrench' => 'Wrench', 'bi-hammer' => 'Hammer',
        'bi-palette' => 'Palette', 'bi-brush' => 'Brush', 'bi-paint-bucket' => 'Paint',
        'bi-lightning' => 'Lightning', 'bi-lightning-fill' => 'Lightning Fill',
        'bi-fire' => 'Fire', 'bi-snow' => 'Snow',
        'bi-sun' => 'Sun', 'bi-moon' => 'Moon', 'bi-cloud' => 'Cloud',
        'bi-umbrella' => 'Umbrella', 'bi-droplet' => 'Droplet',
        'bi-calendar' => 'Calendar', 'bi-calendar-event' => 'Calendar Event',
        'bi-clock' => 'Clock', 'bi-alarm' => 'Alarm', 'bi-hourglass' => 'Hourglass',
        'bi-map' => 'Map', 'bi-geo-alt' => 'Location', 'bi-compass' => 'Compass',
        'bi-building' => 'Building', 'bi-hospital' => 'Hospital',
        'bi-rss' => 'RSS', 'bi-activity' => 'Activity',
        'bi-graph-up' => 'Graph Up', 'bi-bar-chart' => 'Bar Chart', 'bi-pie-chart' => 'Pie Chart',
        'bi-speedometer2' => 'Speedometer', 'bi-bullseye' => 'Bullseye',
        'bi-box' => 'Box', 'bi-archive' => 'Archive', 'bi-puzzle' => 'Puzzle',
        'bi-layers' => 'Layers', 'bi-stack' => 'Stack',
        'bi-aspect-ratio' => 'Aspect Ratio', 'bi-crop' => 'Crop',
        'bi-magic' => 'Magic', 'bi-scissors' => 'Scissors',
        'bi-paperclip' => 'Paperclip', 'bi-binder-clip' => 'Binder Clip'
    );
}

/**
 * Render a single theme option row into a FormContainer.
 * Handles all option types: text, textarea, yesno, select, color, numeric, image, nav_links.
 */
function fmz_render_option_row($form, $form_container, $key, $def, $values, $mybb)
{
    $title = isset($def['title']) ? $def['title'] : $key;
    $desc  = isset($def['description']) ? $def['description'] : '';
    $type  = isset($def['type']) ? $def['type'] : 'text';
    $val   = isset($values[$key]) ? $values[$key] : '';

    switch ($type) {
        case 'textarea':
            $input = $form->generate_text_area('opt_' . $key, $val, array('rows' => 4, 'style' => 'width:95%'));
            break;
        case 'yesno':
            $input = $form->generate_yes_no_radio('opt_' . $key, $val);
            break;
        case 'select':
            $opts = isset($def['options']) ? $def['options'] : array();
            $input = $form->generate_select_box('opt_' . $key, $opts, $val);
            break;
        case 'radio':
            $opts = isset($def['options']) ? $def['options'] : array();
            $radios = '';
            foreach ($opts as $optVal => $optLabel) {
                $checked = ($val === (string)$optVal) ? ' checked' : '';
                $id = 'opt_' . htmlspecialchars_uni($key) . '_' . htmlspecialchars_uni($optVal);
                $radios .= '<label style="display:inline-flex;align-items:center;gap:5px;margin-right:16px;cursor:pointer;font-size:13px">' 
                         . '<input type="radio" name="opt_' . htmlspecialchars_uni($key) . '" value="' . htmlspecialchars_uni($optVal) . '" id="' . $id . '"' . $checked . ' />' 
                         . $optLabel . '</label>';
            }
            $input = '<div style="display:flex;align-items:center;gap:4px">' . $radios . '</div>';
            break;
        case 'color':
            $input = '<input type="color" name="opt_' . htmlspecialchars_uni($key) . '" value="' . htmlspecialchars_uni($val) . '" />';
            break;
        case 'icon_chooser':
            $safeKey = htmlspecialchars_uni($key);
            $safeVal = $val ? htmlspecialchars_uni($val) : '';
            $previewIcon = $safeVal ?: 'bi-grid-3x3-gap';
            $input = '<div style="display:flex;align-items:center;gap:8px">'
                   . '<input type="hidden" name="opt_' . $safeKey . '" id="fmz-icon-val-' . $safeKey . '" value="' . $safeVal . '" />'
                   . '<button type="button" class="fmz-icon-pick-btn" data-target-input="fmz-icon-val-' . $safeKey . '" data-target-preview="fmz-icon-prev-' . $safeKey . '" data-target-label="fmz-icon-lbl-' . $safeKey . '" '
                   . 'style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;font-size:13px;border:1px solid #ccc;border-radius:6px;background:#fafafa;cursor:pointer">'
                   . '<i class="bi ' . $previewIcon . '" id="fmz-icon-prev-' . $safeKey . '" style="font-size:20px"></i> '
                   . '<span id="fmz-icon-lbl-' . $safeKey . '">' . ($safeVal ?: 'Choose icon&hellip;') . '</span>'
                   . '</button>';
            if ($safeVal) {
                $input .= ' <button type="button" class="fmz-icon-clear-btn" data-target-input="fmz-icon-val-' . $safeKey . '" data-target-preview="fmz-icon-prev-' . $safeKey . '" data-target-label="fmz-icon-lbl-' . $safeKey . '" '
                        . 'style="font-size:11px;background:none;border:1px solid #ddd;border-radius:4px;padding:3px 8px;cursor:pointer;color:#888" title="Remove icon">&times; Clear</button>';
            }
            $input .= '</div>';
            break;
        case 'numeric':
            $input = $form->generate_numeric_field('opt_' . $key, $val, array('style' => 'width:150px'));
            break;
        case 'image':
            $input = '';
            if (!empty($val)) {
                $previewUrl = htmlspecialchars_uni($mybb->settings['bburl'] . '/' . $val);
                $input .= '<div style="margin-bottom:8px">'
                        . '<img src="' . $previewUrl . '" style="max-width:200px;max-height:100px;border:1px solid #ddd;border-radius:4px;padding:4px;background:#f9f9f9" />'
                        . '<br /><small style="color:#888">Current: ' . htmlspecialchars_uni($val) . '</small>'
                        . '</div>';
                $input .= '<label style="display:inline-flex;align-items:center;gap:4px;margin-bottom:8px;cursor:pointer">'
                        . '<input type="checkbox" name="opt_' . htmlspecialchars_uni($key) . '_remove" value="1" /> '
                        . '<span style="font-size:12px;color:#c0392b">Remove current image</span></label><br />';
            }
            $input .= '<input type="file" name="opt_' . htmlspecialchars_uni($key) . '_file" accept="image/*,.ico" />';
            $input .= '<br /><small style="color:#888">Max 5MB. Allowed: PNG, JPG, GIF, SVG, WebP, ICO</small>';
            if (!empty($def['has_dimensions'])) {
                $wVal = isset($values[$key . '_width'])  ? $values[$key . '_width']  : (isset($def['default_width'])  ? $def['default_width']  : '');
                $hVal = isset($values[$key . '_height']) ? $values[$key . '_height'] : (isset($def['default_height']) ? $def['default_height'] : '');
                $input .= '<div style="margin-top:8px;display:flex;align-items:center;gap:8px">'
                        . '<label style="font-size:12px">Width: <input type="number" name="opt_' . htmlspecialchars_uni($key) . '_width" value="' . htmlspecialchars_uni($wVal) . '" style="width:80px" min="0" /> px</label>'
                        . '<label style="font-size:12px">Height: <input type="number" name="opt_' . htmlspecialchars_uni($key) . '_height" value="' . htmlspecialchars_uni($hVal) . '" style="width:80px" min="0" /> px</label>'
                        . '<small style="color:#888">(0 or empty = auto)</small>'
                        . '</div>';
            }
            break;
        default:
            $input = $form->generate_text_box('opt_' . $key, $val, array('style' => 'width:95%'));
            break;
    }

    $form_container->output_row($title, $desc, $input);
}

/* ====================================================================
   Page Manager — API: Save Page (AJAX)
   ==================================================================== */

if ($action === 'pages_api') {
    header('Content-Type: application/json; charset=utf-8');
    verify_post_check($mybb->get_input('my_post_key'));

    $api_action = $mybb->get_input('api_action');

    // ── Save page ──
    if ($api_action === 'save') {
        $pid = intval($mybb->get_input('pid'));
        $clean_slug = preg_replace('/[^a-zA-Z0-9\-_\/]/', '', $mybb->get_input('slug'));
        $clean_groups = preg_replace('/[^0-9,]/', '', $mybb->get_input('allowed_groups'));

        // Data for insert_query/update_query — do NOT pre-escape (these functions escape internally)
        $data = array(
            'title'            => $mybb->get_input('title'),
            'slug'             => $clean_slug,
            'content'          => $mybb->get_input('content'),
            'status'           => in_array($mybb->get_input('status'), array('draft','published')) ? $mybb->get_input('status') : 'draft',
            'meta_title'       => $mybb->get_input('meta_title'),
            'meta_description' => $mybb->get_input('meta_description'),
            'allowed_groups'   => $clean_groups,
            'custom_css'       => $mybb->get_input('custom_css'),
            'custom_js'        => $mybb->get_input('custom_js'),
            'updated_at'       => TIME_NOW,
        );

        // Check slug uniqueness (simple_select WHERE clause needs manual escaping)
        $slug_esc = $db->escape_string($clean_slug);
        $slugCheck = $db->simple_select('fmz_pages', 'pid', "slug='" . $slug_esc . "'" . ($pid ? " AND pid != {$pid}" : ''));
        if ($db->num_rows($slugCheck)) {
            echo json_encode(array('error' => 'A page with this slug already exists.'));
            exit;
        }

        if ($pid > 0) {
            $db->update_query('fmz_pages', $data, "pid={$pid}");
        } else {
            $data['author_uid'] = intval($mybb->user['uid']);
            $data['created_at'] = TIME_NOW;
            // Get next disporder
            $query = $db->simple_select('fmz_pages', 'MAX(disporder) as maxd');
            $data['disporder'] = intval($db->fetch_field($query, 'maxd')) + 1;
            $pid = $db->insert_query('fmz_pages', $data);
        }

        echo json_encode(array('success' => true, 'pid' => $pid));
        exit;
    }

    // ── Get page data ──
    if ($api_action === 'get') {
        $pid = intval($mybb->get_input('pid'));
        $query = $db->simple_select('fmz_pages', '*', "pid={$pid}");
        $row = $db->fetch_array($query);
        if (!$row) {
            echo json_encode(array('error' => 'Page not found.'));
        } else {
            echo json_encode(array('success' => true, 'page' => $row));
        }
        exit;
    }

    // ── Reorder pages ──
    if ($api_action === 'reorder') {
        $order = @json_decode($mybb->get_input('order'), true);
        if (is_array($order)) {
            foreach ($order as $i => $pid) {
                $db->update_query('fmz_pages', array('disporder' => intval($i)), "pid=" . intval($pid));
            }
        }
        echo json_encode(array('success' => true));
        exit;
    }

    // ── Check slug availability ──
    if ($api_action === 'check_slug') {
        $slug = preg_replace('/[^a-zA-Z0-9\-_\/]/', '', $mybb->get_input('slug'));
        $pid = intval($mybb->get_input('pid'));
        if (!$slug) {
            echo json_encode(array('available' => false));
            exit;
        }
        $where = "slug='" . $db->escape_string($slug) . "'";
        if ($pid > 0) $where .= " AND pid != {$pid}";
        $check = $db->simple_select('fmz_pages', 'pid', $where);
        if (!$db->num_rows($check)) {
            echo json_encode(array('available' => true));
        } else {
            // Find unique suggestion
            $base = preg_replace('/-\d+$/', '', $slug);
            $suggestion = '';
            for ($i = 2; $i <= 100; $i++) {
                $candidate = $base . '-' . $i;
                $cWhere = "slug='" . $db->escape_string($candidate) . "'";
                if ($pid > 0) $cWhere .= " AND pid != {$pid}";
                $cCheck = $db->simple_select('fmz_pages', 'pid', $cWhere);
                if (!$db->num_rows($cCheck)) {
                    $suggestion = $candidate;
                    break;
                }
            }
            echo json_encode(array('available' => false, 'suggestion' => $suggestion));
        }
        exit;
    }

    // ── Set front page ──
    if ($api_action === 'set_front_page') {
        $front_page_type = $mybb->get_input('front_page_type');
        $front_page_slug = '';

        if ($front_page_type === 'page') {
            $front_page_slug = preg_replace('/[^a-zA-Z0-9\-_\/]/', '', $mybb->get_input('front_page_slug'));
            // Verify page exists
            $check = $db->simple_select('fmz_pages', 'pid', "slug='" . $db->escape_string($front_page_slug) . "'");
            if (!$db->num_rows($check)) {
                echo json_encode(array('error' => 'Selected page does not exist.'));
                exit;
            }
        } elseif ($front_page_type === 'portal') {
            // Portal — no slug needed
        } else {
            $front_page_type = 'default';
        }

        $cache->update('fmz_front_page', array(
            'type' => $front_page_type,
            'slug' => $front_page_slug,
        ));

        echo json_encode(array('success' => true));
        exit;
    }

    echo json_encode(array('error' => 'Unknown API action.'));
    exit;
}

/* ====================================================================
   Page Manager — Delete Page
   ==================================================================== */

if ($action === 'pages_delete') {
    verify_post_check($mybb->get_input('my_post_key'));
    $pid = intval($mybb->get_input('pid'));
    if ($pid > 0) {
        $db->delete_query('fmz_pages', "pid={$pid}");
        flash_message('Page deleted successfully.', 'success');
    }
    admin_redirect("index.php?module=fmzstudio-pages");
}

/* ====================================================================
   Page Manager — Add / Edit Page (HTML Editor)
   ==================================================================== */

if ($action === 'pages_add' || $action === 'pages_edit') {
    // Secondary license validation — independent code path
    if (!FMZLicense::assertLicensed()) {
        flash_message('License validation failed. Please re-enter your license key.', 'error');
        admin_redirect("index.php?module=fmzstudio-license");
    }

    $pid = intval($mybb->get_input('pid'));
    $pageData = array();

    if ($action === 'pages_edit' && $pid > 0) {
        $query = $db->simple_select('fmz_pages', '*', "pid={$pid}");
        $pageData = $db->fetch_array($query);
        if (!$pageData) {
            flash_message('Page not found.', 'error');
            admin_redirect("index.php?module=fmzstudio-pages");
        }
    }

    // Load usergroups for permission selector
    $usergroups = array();
    $query = $db->simple_select('usergroups', 'gid, title', '', array('order_by' => 'title'));
    while ($row = $db->fetch_array($query)) {
        $usergroups[] = $row;
    }

    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Page Manager", "index.php?module=fmzstudio-pages");
    $page->add_breadcrumb_item($action === 'pages_edit' ? "Edit Page" : "Add Page");

    $page->output_header("FMZ Studio - Page Editor");

    $pageDataJson = json_encode($pageData ?: new stdClass());
    $usergroupsJson = json_encode($usergroups);
    $bbnameJson = json_encode($mybb->settings['bbname']);
    $bburlJson = json_encode($mybb->settings['bburl']);
    $postKey = $mybb->post_code;

    echo <<<HTML
<link rel="stylesheet" href="../jscripts/fmzstudio/pagebuilder.css" />

<div id="pb-builder" data-pid="{$pid}" data-post-key="{$postKey}">
    <!-- Top Bar -->
    <div class="pb-topbar">
        <div class="pb-topbar-left">
            <a href="index.php?module=fmzstudio-pages" class="pb-topbar-btn" title="Back"><i class="bi bi-arrow-left"></i></a>
            <input type="text" id="pb-title" class="pb-title-input" placeholder="Page Title" />
            <input type="text" id="pb-slug" class="pb-slug-input" placeholder="page-slug" />
            <span class="pb-permalink"><i class="bi bi-link-45deg"></i> <span id="pb-permalink">&mdash;</span></span>
        </div>
        <div class="pb-topbar-right">
            <select id="pb-status" class="pb-status-select">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
            </select>
            <button type="button" id="pb-preview" class="pb-topbar-btn" title="Preview"><i class="bi bi-eye"></i> Preview</button>
            <button type="button" id="pb-save" class="pb-topbar-btn pb-btn-primary"><i class="bi bi-check-lg"></i> Save</button>
        </div>
    </div>

    <!-- Editor Body -->
    <div class="pb-editor-body">
        <div id="pb-monaco-container" class="pb-monaco-container"></div>
    </div>

    <!-- Page Settings (collapsible bottom) -->
    <div class="pb-settings-wrap">
        <button type="button" id="pb-settings-toggle" class="pb-settings-toggle"><i class="bi bi-chevron-up"></i> Page Settings</button>
        <div id="pb-settings" class="pb-settings" style="display:none">
            <div class="pb-settings-grid">
                <div class="pb-field"><label>Meta Title</label><input type="text" class="pb-input" id="pb-meta-title" placeholder="Optional SEO title" /></div>
                <div class="pb-field"><label>Meta Description</label><textarea class="pb-input" id="pb-meta-desc" rows="2" placeholder="Optional SEO description"></textarea></div>
                <div class="pb-field"><label>Allowed Groups <small>(empty = all)</small></label><div id="pb-groups"></div></div>
                <div class="pb-field"><label>Custom CSS</label><textarea class="pb-input pb-code" id="pb-custom-css" rows="3" placeholder="/* page-specific CSS */"></textarea></div>
                <div class="pb-field"><label>Custom JS</label><textarea class="pb-input pb-code" id="pb-custom-js" rows="3" placeholder="// page-specific JS"></textarea></div>
            </div>
        </div>
    </div>

    <!-- Variables Reference (below settings) -->
    <div class="pb-vars-wrap">
        <button type="button" id="pb-vars-toggle" class="pb-settings-toggle"><i class="bi bi-braces"></i> Available Variables</button>
        <div id="pb-vars" class="pb-vars-panel" style="display:none">
            <p class="pb-vars-note">Click a variable to insert it at the cursor position in the editor.</p>
            <div class="pb-vars-grid">
                <div class="pb-vars-group">
                    <h4>Page Shell</h4>
                    <code class="pb-var-item" data-var="{\$headerinclude}">{\$headerinclude}</code>
                    <code class="pb-var-item" data-var="{\$header}">{\$header}</code>
                    <code class="pb-var-item" data-var="{\$footer}">{\$footer}</code>
                </div>
                <div class="pb-vars-group">
                    <h4>User</h4>
                    <code class="pb-var-item" data-var="{\$mybb->user['username']}">{\$mybb->user['username']}</code>
                    <code class="pb-var-item" data-var="{\$mybb->user['uid']}">{\$mybb->user['uid']}</code>
                    <code class="pb-var-item" data-var="{\$mybb->user['avatar']}">{\$mybb->user['avatar']}</code>
                    <code class="pb-var-item" data-var="{\$mybb->user['usergroup']}">{\$mybb->user['usergroup']}</code>
                    <code class="pb-var-item" data-var="{\$mybb->user['postnum']}">{\$mybb->user['postnum']}</code>
                    <code class="pb-var-item" data-var="{\$mybb->user['reputation']}">{\$mybb->user['reputation']}</code>
                </div>
                <div class="pb-vars-group">
                    <h4>Board</h4>
                    <code class="pb-var-item" data-var="{\$mybb->settings['bbname']}">{\$mybb->settings['bbname']}</code>
                    <code class="pb-var-item" data-var="{\$mybb->settings['bburl']}">{\$mybb->settings['bburl']}</code>
                    <code class="pb-var-item" data-var="{\$mybb->settings['bbdesc']}">{\$mybb->settings['bbdesc']}</code>
                </div>
                <div class="pb-vars-group">
                    <h4>Global Templates</h4>
                    <code class="pb-var-item" data-var="{\$welcomeblock}">{\$welcomeblock}</code>
                    <code class="pb-var-item" data-var="{\$pm_notice}">{\$pm_notice}</code>
                    <code class="pb-var-item" data-var="{\$boardstats}">{\$boardstats}</code>
                    <code class="pb-var-item" data-var="{\$nav}">{\$nav}</code>
                    <code class="pb-var-item" data-var="{\$forums}">{\$forums}</code>
                    <code class="pb-var-item" data-var="{\$search}">{\$search}</code>
                </div>
                <div class="pb-vars-group">
                    <h4>Conditionals</h4>
                    <code class="pb-var-item" data-var="&lt;if \$mybb-&gt;user['uid'] then&gt;logged in&lt;else&gt;guest&lt;/if&gt;">if / else</code>
                    <code class="pb-var-item" data-var="&lt;if \$mybb-&gt;usergroup['cancp'] then&gt;admin content&lt;/if&gt;">if (admin)</code>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.50.0/min/vs/loader.js"></script>
<script>
window.FMZ_PAGE_DATA = {$pageDataJson};
window.FMZ_PAGE_USERGROUPS = {$usergroupsJson};
window.FMZ_PAGE_BBNAME = {$bbnameJson};
window.FMZ_PAGE_BBURL = {$bburlJson};
</script>
<script src="../jscripts/fmzstudio/pagebuilder.js"></script>
HTML;

    $page->output_footer();
    exit;
}

/* ====================================================================
   Page Manager — List Pages
   ==================================================================== */

if ($action === 'pages') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Page Manager");

    $page->output_header("FMZ Studio - Page Manager");

    echo $fmzBtnHoverCss;

    echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">';
    echo '<h2 style="margin:0;font-size:18px"><i class="bi bi-file-earmark-code" style="color:#0d9488;margin-right:8px"></i>Page Manager</h2>';
    echo '<div style="display:flex;gap:8px">';
    echo '<a href="index.php?module=fmzstudio-pages_add&action=pages_add" class="fmz-btn" style="' . $btnStyleSuccess . '"><i class="bi bi-plus-lg"></i> Add Page</a>';
    echo '</div></div>';

    echo fmz_help_box('Page Manager', 'Create and manage custom HTML pages. Write HTML with MyBB template variables — pages are accessible at <code>/page-slug</code> (clean URL). Each page can have its own CSS, JS, SEO meta tags, and usergroup permissions.');

    // List pages
    $pages = array();
    if ($db->table_exists('fmz_pages')) {
        $query = $db->simple_select('fmz_pages', '*', '', array('order_by' => 'disporder', 'order_dir' => 'ASC'));
        while ($row = $db->fetch_array($query)) {
            $pages[] = $row;
        }
    }

    // ── Front Page Selector ──
    $front_page_data = $cache->read('fmz_front_page');
    $fp_type = is_array($front_page_data) ? ($front_page_data['type'] ?? 'default') : 'default';
    $fp_slug = is_array($front_page_data) ? ($front_page_data['slug'] ?? '') : '';

    echo '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:20px">';
    echo '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">';
    echo '<label style="font-weight:600;font-size:13px;white-space:nowrap"><i class="bi bi-house-door" style="color:#0d9488;margin-right:6px"></i>Front Page:</label>';
    echo '<select id="fmz-front-page-select" style="padding:6px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;min-width:250px">';
    echo '<option value="default"' . ($fp_type === 'default' ? ' selected' : '') . '>Default (Forum Index)</option>';
    echo '<option value="portal"' . ($fp_type === 'portal' ? ' selected' : '') . '>Portal Page</option>';

    foreach ($pages as $p) {
        if ($p['status'] === 'published') {
            $selected = ($fp_type === 'page' && $fp_slug === $p['slug']) ? ' selected' : '';
            echo '<option value="page:' . htmlspecialchars_uni($p['slug']) . '"' . $selected . '>' . htmlspecialchars_uni($p['title']) . ' (/' . htmlspecialchars_uni($p['slug']) . ')</option>';
        }
    }

    echo '</select>';
    echo '<button id="fmz-front-page-save" class="fmz-btn" style="' . $btnStyleSuccess . ';font-size:12px;padding:5px 14px"><i class="bi bi-check-lg"></i> Save</button>';
    echo '<span id="fmz-front-page-status" style="font-size:12px;color:#059669;display:none"></span>';
    echo '</div>';
    echo '<small style="color:#64748b;margin-top:8px;display:block">Set which page visitors see at your forum\'s root URL. When a custom page or portal is selected, the forum list remains accessible at <code>index.php?forums</code>.</small>';
    echo '</div>';

    echo '<script>
document.getElementById("fmz-front-page-save").addEventListener("click", function() {
    var sel = document.getElementById("fmz-front-page-select");
    var val = sel.value, type = "default", slug = "";
    if (val === "portal") { type = "portal"; }
    else if (val.indexOf("page:") === 0) { type = "page"; slug = val.substring(5); }
    var btn = this;
    btn.disabled = true; btn.innerHTML = "<i class=\'bi bi-arrow-repeat\'></i> Saving...";
    var fd = new FormData();
    fd.append("my_post_key", "' . $mybb->post_code . '");
    fd.append("api_action", "set_front_page");
    fd.append("front_page_type", type);
    fd.append("front_page_slug", slug);
    fetch("index.php?module=fmzstudio-pages_api&action=pages_api", { method: "POST", body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false; btn.innerHTML = "<i class=\'bi bi-check-lg\'></i> Save";
            var st = document.getElementById("fmz-front-page-status");
            if (data.success) {
                st.style.display = "inline"; st.style.color = "#059669";
                st.textContent = "\u2713 Saved";
                setTimeout(function() { st.style.display = "none"; }, 3000);
            } else {
                st.style.display = "inline"; st.style.color = "#dc2626";
                st.textContent = "\u2717 " + (data.error || "Error");
            }
        })
        .catch(function() { btn.disabled = false; btn.innerHTML = "<i class=\'bi bi-check-lg\'></i> Save"; });
});
</script>';

    echo '<table class="fmz-table" style="width:100%;border-collapse:collapse;font-size:13px">';
    echo '<thead><tr style="background:#f1f5f9;text-align:left">';
    echo '<th style="padding:10px 12px;border-bottom:2px solid #e2e8f0;width:30px"></th>';
    echo '<th style="padding:10px 12px;border-bottom:2px solid #e2e8f0">Title</th>';
    echo '<th style="padding:10px 12px;border-bottom:2px solid #e2e8f0">Slug</th>';
    echo '<th style="padding:10px 12px;border-bottom:2px solid #e2e8f0">Status</th>';
    echo '<th style="padding:10px 12px;border-bottom:2px solid #e2e8f0">Author</th>';
    echo '<th style="padding:10px 12px;border-bottom:2px solid #e2e8f0">Last Updated</th>';
    echo '<th style="padding:10px 12px;border-bottom:2px solid #e2e8f0;width:180px">Actions</th>';
    echo '</tr></thead><tbody>';

    if (empty($pages)) {
        echo '<tr><td colspan="7" style="padding:30px;text-align:center;color:#888">';
        echo '<i class="bi bi-file-earmark-plus" style="font-size:32px;display:block;margin-bottom:10px;opacity:.4"></i>';
        echo 'No pages created yet. <a href="index.php?module=fmzstudio-pages_add&action=pages_add">Create your first page</a>';
        echo '</td></tr>';
    }

    foreach ($pages as $p) {
        $authorName = 'System';
        if ($p['author_uid'] > 0) {
            $aquery = $db->simple_select('users', 'username', "uid=" . intval($p['author_uid']));
            $arow = $db->fetch_array($aquery);
            if ($arow) $authorName = htmlspecialchars_uni($arow['username']);
        }

        $statusBadge = $p['status'] === 'published'
            ? '<span style="background:#059669;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600">Published</span>'
            : '<span style="background:#6b7280;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600">Draft</span>';

        $updatedAt = $p['updated_at'] > 0 ? my_date('relative', $p['updated_at']) : my_date('relative', $p['created_at']);
        $viewUrl = $mybb->settings['bburl'] . '/' . htmlspecialchars_uni($p['slug']);

        echo '<tr style="border-bottom:1px solid #e2e8f0" data-pid="' . intval($p['pid']) . '">';
        echo '<td style="padding:8px 12px;cursor:grab"><i class="bi bi-grip-vertical" style="opacity:.4"></i></td>';
        echo '<td style="padding:8px 12px;font-weight:600">' . htmlspecialchars_uni($p['title']) . '</td>';
        echo '<td style="padding:8px 12px"><code>' . htmlspecialchars_uni($p['slug']) . '</code></td>';
        echo '<td style="padding:8px 12px">' . $statusBadge . '</td>';
        echo '<td style="padding:8px 12px">' . $authorName . '</td>';
        echo '<td style="padding:8px 12px"><small>' . $updatedAt . '</small></td>';
        echo '<td style="padding:8px 12px">';
        echo '<div style="display:flex;gap:4px">';
        echo '<a href="index.php?module=fmzstudio-pages_edit&action=pages_edit&pid=' . intval($p['pid']) . '" class="fmz-btn" style="' . $btnStyle . ';font-size:11px;padding:3px 8px" title="Edit"><i class="bi bi-pencil"></i></a>';
        echo '<a href="' . $viewUrl . '" target="_blank" class="fmz-btn" style="' . $btnStyle . ';font-size:11px;padding:3px 8px" title="View"><i class="bi bi-eye"></i></a>';
        echo '<a href="index.php?module=fmzstudio-pages_delete&action=pages_delete&pid=' . intval($p['pid']) . '&my_post_key=' . $mybb->post_code . '" class="fmz-btn" style="' . $btnStyleDanger . ';font-size:11px;padding:3px 8px" title="Delete" onclick="return confirm(\'Delete this page?\')"><i class="bi bi-trash"></i></a>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    $page->output_footer();
    exit;
}

/* ====================================================================
   Documentation Page (tabbed)
   ==================================================================== */

if ($action === 'docs') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Documentation");

    $page->output_header("FMZ Studio - Documentation");

    // Build tab content
    $tabs = array();

    // ── Tab 1: Getting Started ──
    $tabs['getting_started'] = array(
        'title' => 'Getting Started',
        'icon'  => 'bi-rocket-takeoff',
        'content' => '
<h3 style="margin-top:0">What is FMZ Studio?</h3>
<p>FMZ Studio is a modular theme manager and development toolkit for MyBB 1.8. It replaces MyBB\'s built-in theme system with a file-based workflow where themes live as editable files on disk and are synced to the database.</p>

<h4>System Requirements</h4>
<table class="general" cellspacing="0" style="margin-top:8px">
<thead><tr><th style="width:200px">Requirement</th><th>Details</th></tr></thead>
<tr><td><strong>MyBB</strong></td><td>1.8.38 or newer</td></tr>
<tr><td><strong>PHP</strong></td><td>8.0 or newer</td></tr>
<tr><td><strong>PHP ext-zip</strong></td><td>Required for theme import/export (ZIP packages). Usually bundled with PHP but may need enabling.</td></tr>
<tr><td><strong>PHP ext-fileinfo</strong></td><td>Required for image upload MIME validation. Bundled and enabled by default in PHP 8+.</td></tr>
<tr><td><strong>Database</strong></td><td>MySQL 5.7+ / MariaDB 10.3+ / PostgreSQL 9.6+ (same as MyBB)</td></tr>
<tr><td><strong>Web Server</strong></td><td>Apache with <code>mod_rewrite</code> (required for Page Builder clean URLs) or Nginx with equivalent rewrite rules</td></tr>
</table>
<p style="margin-top:8px"><small><code>ext-json</code> and <code>ext-pcre</code> are also used but are built into PHP 8+ and always available.</small></p>

<h4>Key Features</h4>
<ul>
<li><strong>File-Based Themes</strong> — Templates, CSS, JS, and images live in <code>themes/{slug}/</code> as real files you can edit in any IDE.</li>
<li><strong>Database Sync</strong> — One-click sync pushes file changes into the MyBB database.</li>
<li><strong>Auto-Sync (Dev Mode)</strong> — File changes sync automatically on page load during development.</li>
<li><strong>Built-in Monaco Editor</strong> — VS Code-like code editor right in the Admin CP.</li>
<li><strong>Global FMZ Options</strong> — Color mode, color palettes, layout, and effects settings.</li>
<li><strong>Header &amp; Footer</strong> — Header style, logo, favicon, navigation links, and footer text.</li>
<li><strong>Page Builder</strong> — Create standalone pages with a full HTML/CSS/JS Monaco editor, clean URL routing, and front page override.</li>
<li><strong>Mini Plugins</strong> — Self-contained plugins that extend themes (WYSIWYG editor, forum icons, profile extras, etc.).</li>
<li><strong>Import / Export</strong> — Upload or download themes as ZIP packages.</li>
</ul>

<h4>Step-by-Step: First-Time Setup</h4>
<ol>
<li>Go to <strong>ACP → Configuration → Plugins</strong> and ensure <em>FMZ Studio</em> is activated.</li>
<li>Navigate to <strong>FMZ Studio → Manage</strong> — you\'ll see all themes in <code>themes/</code>.</li>
<li>Click <strong>Sync</strong> on the theme you want to use — this imports all files into the database.</li>
<li>Click <strong>Set Default</strong> to make it the active board theme.</li>
<li>Visit your forum — the theme is now live.</li>
</ol>

<h4>Step-by-Step: Customize Theme Appearance</h4>
<ol>
<li>Go to <strong>FMZ Studio → Global FMZ Options</strong> for colors and layout, or <strong>Header &amp; Footer</strong> for logo, navigation, and footer.</li>
<li><strong>Colors:</strong> Choose a Quick Preset or adjust individual palette values for Light/Dark modes.</li>
<li><strong>Layout:</strong> Set content max-width (default 1200px), enable the stats sidebar, toggle the loading bar.</li>
<li><strong>Header:</strong> Choose between Default, Centered, or Minimal header layouts. Upload a logo or set an icon.</li>
<li><strong>Navigation:</strong> Add custom links to the navbar with optional icons.</li>
<li><strong>Footer:</strong> Add custom footer text or override the About section text.</li>
<li>Click <strong>Save</strong> — all changes take effect immediately.</li>
</ol>

<h4>Step-by-Step: Enable Mini Plugins</h4>
<ol>
<li>Go to <strong>FMZ Studio → Manage Plugins</strong>.</li>
<li>You\'ll see all available mini plugins bundled with the theme.</li>
<li>Click <strong>Enable</strong> on any plugin (e.g., WYSIWYG Editor, Forum Icons, Profile Extras).</li>
<li>Enabled plugins with settings appear as individual entries in the sidebar — click them to configure.</li>
<li>Reload the forum — the plugin\'s features are active.</li>
</ol>

<h4>Step-by-Step: Edit Theme Files</h4>
<ol>
<li>Go to <strong>FMZ Studio → Manage</strong> → click <strong>Edit</strong> on a theme.</li>
<li>The built-in Monaco editor opens with a file tree on the left.</li>
<li>Click any file to open it (HTML templates, CSS, JS, PHP, JSON).</li>
<li>Make edits — press <kbd>Ctrl+S</kbd> to save &amp; sync to the database.</li>
<li>Alternatively, edit files directly on disk with VS Code or any editor, and auto-sync will pick up changes.</li>
</ol>

<h4>Admin Panel Navigation</h4>
<table class="general" cellspacing="0" style="margin-top:8px">
<tr><td style="width:160px"><strong>Manage Themes</strong></td><td>View, activate, sync, and edit all themes</td></tr>
<tr><td><strong>Import / Export</strong></td><td>Upload or download theme ZIP packages</td></tr>
<tr><td><strong>Global FMZ Options</strong></td><td>Color mode, color palettes, layout, and effects settings</td></tr>
<tr><td><strong>Header &amp; Footer</strong></td><td>Header style, logo, favicon, navigation links, and footer text</td></tr>
<tr><td><strong>Page Manager</strong></td><td>Create and manage standalone pages (Page Builder plugin)</td></tr>
<tr><td><strong><em>Plugin Name</em></strong></td><td>Each enabled plugin with settings gets its own sidebar entry for quick access</td></tr>
<tr><td><strong>Manage Plugins</strong></td><td>Enable/disable theme mini plugins</td></tr>
<tr><td><strong>Studio Settings</strong></td><td>FMZ Studio global settings</td></tr>
<tr><td><strong>Documentation</strong></td><td>This documentation page</td></tr>
</table>
'
    );

    // ── Tab 2: Theme Structure ──
    $tabs['theme_structure'] = array(
        'title' => 'Theme Structure',
        'icon'  => 'bi-folder2-open',
        'content' => '
<h3 style="margin-top:0">Theme Directory Structure</h3>
<p>Each theme lives in <code>themes/{slug}/</code>. The slug is a lowercase, hyphenated version of the theme name.</p>

<pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;font-size:12px;line-height:1.6;overflow-x:auto">
your-theme/
├── <span style="color:#4ec9b0">theme.json</span>            ← <span style="color:#ce9178">REQUIRED</span> — theme manifest
├── <span style="color:#569cd6">css/</span>                  ← stylesheets
│   ├── global.css
│   ├── showthread.css
│   └── ...
├── <span style="color:#569cd6">templates/</span>            ← <span style="color:#ce9178">REQUIRED</span> — at least one .html file
│   ├── header/
│   │   ├── header.html
│   │   └── header_welcomeblock_member.html
│   ├── footer/
│   │   └── footer.html
│   ├── postbit/
│   │   └── postbit.html
│   ├── showthread/
│   │   └── showthread.html
│   ├── ungrouped/
│   │   ├── headerinclude.html
│   │   └── htmldoctype.html
│   └── ...
├── <span style="color:#569cd6">js/</span>                   ← optional — deployed to jscripts/
│   └── main.js
├── <span style="color:#569cd6">images/</span>               ← optional — theme images
│   └── logo.png
├── <span style="color:#569cd6">functions/</span>            ← optional — hooks, options, plugins
│   ├── hooks.php         ← custom PHP hooks
│   ├── options.php       ← theme option definitions
│   └── plugins/          ← mini plugins directory
│       └── my-plugin/
│           └── ...
└── <span style="color:#808080">(any other folders)</span>   ← fonts/, vendor/, etc.
</pre>

<h4>Required Files</h4>
<table class="general" cellspacing="0" style="margin-top:8px">
<tr><td style="width:200px"><code>theme.json</code></td><td>Theme manifest with name, version, stylesheet mapping, and properties. <strong>Required.</strong></td></tr>
<tr><td><code>templates/</code></td><td>Directory containing <code>.html</code> template files organized by group. Must have at least one file. <strong>Required.</strong></td></tr>
</table>

<h4>Optional Files &amp; Folders</h4>
<table class="general" cellspacing="0" style="margin-top:8px">
<tr><td style="width:200px"><code>css/</code></td><td>Stylesheets referenced in <code>theme.json</code>. Each CSS file becomes a MyBB stylesheet.</td></tr>
<tr><td><code>js/</code></td><td>JavaScript files listed in <code>theme.json</code>\'s <code>"js"</code> array. Deployed to <code>jscripts/</code> on sync.</td></tr>
<tr><td><code>images/</code></td><td>Theme images (logo, backgrounds, etc.).</td></tr>
<tr><td><code>functions/hooks.php</code></td><td>Custom PHP hooks loaded at <code>global_intermediate</code>.</td></tr>
<tr><td><code>functions/options.php</code></td><td>Theme option definitions displayed in Global FMZ Options and Header &amp; Footer.</td></tr>
<tr><td><code>functions/plugins/</code></td><td>Mini plugins directory (see Plugin Structure tab).</td></tr>
</table>

<h4>theme.json Reference</h4>
<pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;font-size:12px;line-height:1.6;overflow-x:auto">
{
  <span style="color:#9cdcfe">"name"</span>: <span style="color:#ce9178">"My Theme"</span>,           <span style="color:#6a9955">// REQUIRED — display name</span>
  <span style="color:#9cdcfe">"version"</span>: <span style="color:#ce9178">"1839"</span>,            <span style="color:#6a9955">// MyBB version compatibility</span>
  <span style="color:#9cdcfe">"properties"</span>: {
    <span style="color:#9cdcfe">"editortheme"</span>: <span style="color:#ce9178">"default.css"</span>,
    <span style="color:#9cdcfe">"imgdir"</span>: <span style="color:#ce9178">"images"</span>,
    <span style="color:#9cdcfe">"tablespace"</span>: <span style="color:#ce9178">"0"</span>,
    <span style="color:#9cdcfe">"borderwidth"</span>: <span style="color:#ce9178">"0"</span>
  },
  <span style="color:#9cdcfe">"stylesheets"</span>: [
    { <span style="color:#9cdcfe">"name"</span>: <span style="color:#ce9178">"global.css"</span>, <span style="color:#9cdcfe">"attachedto"</span>: <span style="color:#ce9178">""</span>, <span style="color:#9cdcfe">"order"</span>: <span style="color:#b5cea8">1</span> },
    { <span style="color:#9cdcfe">"name"</span>: <span style="color:#ce9178">"showthread.css"</span>, <span style="color:#9cdcfe">"attachedto"</span>: <span style="color:#ce9178">"showthread.php"</span>, <span style="color:#9cdcfe">"order"</span>: <span style="color:#b5cea8">2</span> }
  ],
  <span style="color:#9cdcfe">"js"</span>: [<span style="color:#ce9178">"main.js"</span>]
}
</pre>

<h4>Templates</h4>
<ul>
<li>Each <code>.html</code> file maps to a MyBB template. Filename (without extension) = template name.</li>
<li>Subdirectories correspond to MyBB template groups (header/, footer/, etc.) — organizational only.</li>
<li>Use standard MyBB template variables: <code>{$variable}</code>, <code>{$lang-&gt;string}</code>, etc.</li>
</ul>
'
    );

    // ── Tab 3: Plugin Structure ──
    $tabs['plugin_structure'] = array(
        'title' => 'Plugin Structure',
        'icon'  => 'bi-puzzle',
        'content' => '
<h3 style="margin-top:0">Mini Plugin Structure</h3>
<p>Mini plugins are self-contained extensions bundled inside a theme. They live under <code>themes/{slug}/functions/plugins/{plugin-id}/</code>.</p>

<pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;font-size:12px;line-height:1.6;overflow-x:auto">
my-plugin/
├── <span style="color:#4ec9b0">plugin.json</span>       ← <span style="color:#ce9178">REQUIRED</span> — plugin manifest
├── <span style="color:#4ec9b0">init.php</span>          ← <span style="color:#ce9178">REQUIRED</span> — loaded at global_intermediate
├── <span style="color:#569cd6">options.php</span>       ← optional — option definitions
├── <span style="color:#569cd6">default.json</span>      ← optional — default option values
├── <span style="color:#569cd6">css/</span>              ← optional — auto-loaded on all pages
│   └── styles.css
├── <span style="color:#569cd6">js/</span>               ← optional — auto-loaded on all pages
│   └── script.js
└── <span style="color:#569cd6">admin.php</span>         ← optional — custom admin panel content
</pre>

<h4>Required Files</h4>
<table class="general" cellspacing="0" style="margin-top:8px">
<tr><td style="width:200px"><code>plugin.json</code></td><td>Manifest with <code>id</code>, <code>name</code>, <code>version</code>, <code>description</code>, <code>author</code>. <strong>Required.</strong></td></tr>
<tr><td><code>init.php</code></td><td>Entry point loaded when the plugin is enabled. Register MyBB hooks here. <strong>Required.</strong></td></tr>
</table>

<h4>plugin.json Example</h4>
<pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;font-size:12px;line-height:1.6;overflow-x:auto">
{
  <span style="color:#9cdcfe">"id"</span>: <span style="color:#ce9178">"my-plugin"</span>,
  <span style="color:#9cdcfe">"name"</span>: <span style="color:#ce9178">"My Custom Plugin"</span>,
  <span style="color:#9cdcfe">"version"</span>: <span style="color:#ce9178">"1.0.0"</span>,
  <span style="color:#9cdcfe">"description"</span>: <span style="color:#ce9178">"A brief description of what this plugin does."</span>,
  <span style="color:#9cdcfe">"author"</span>: <span style="color:#ce9178">"Your Name"</span>
}
</pre>

<h4>options.php Format</h4>
<p>Returns an array of option definitions. Each option has an <code>id</code>, <code>label</code>, <code>description</code>, <code>type</code>, and <code>default</code>:</p>
<pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;font-size:12px;line-height:1.6;overflow-x:auto">
<span style="color:#c586c0">return</span> <span style="color:#569cd6">array</span>(
    <span style="color:#569cd6">array</span>(
        <span style="color:#ce9178">\'id\'</span>          => <span style="color:#ce9178">\'enable_feature\'</span>,
        <span style="color:#ce9178">\'label\'</span>       => <span style="color:#ce9178">\'Enable Feature\'</span>,
        <span style="color:#ce9178">\'description\'</span> => <span style="color:#ce9178">\'Toggle this feature on or off.\'</span>,
        <span style="color:#ce9178">\'type\'</span>        => <span style="color:#ce9178">\'yesno\'</span>,       <span style="color:#6a9955">// text, textarea, yesno, select, color, numeric</span>
        <span style="color:#ce9178">\'default\'</span>     => <span style="color:#ce9178">\'1\'</span>,
    ),
    <span style="color:#6a9955">// ... more options</span>
);
</pre>

<h4>Supported Option Types</h4>
<table class="general" cellspacing="0" style="margin-top:8px">
<tr><td style="width:140px"><code>text</code></td><td>Single-line text input</td></tr>
<tr><td><code>textarea</code></td><td>Multi-line text area</td></tr>
<tr><td><code>yesno</code></td><td>Yes/No radio buttons (stores "1" or "0")</td></tr>
<tr><td><code>select</code></td><td>Dropdown select (provide <code>options</code> array)</td></tr>
<tr><td><code>color</code></td><td>Color picker</td></tr>
<tr><td><code>numeric</code></td><td>Numeric input</td></tr>
<tr><td><code>toolbar_builder</code></td><td>Drag-and-drop toolbar builder (special)</td></tr>
</table>

<h4>How Assets Load</h4>
<ul>
<li><strong>CSS files</strong> in <code>css/</code> are injected as <code>&lt;link&gt;</code> tags into <code>&lt;head&gt;</code> on all pages.</li>
<li><strong>JS files</strong> in <code>js/</code> are injected as <code>&lt;script&gt;</code> tags into <code>&lt;head&gt;</code> on all pages.</li>
<li><strong>init.php</strong> is <code>include_once</code>\'d during <code>global_intermediate</code> — register your hooks here.</li>
<li><strong>options.php</strong> is read by the admin panel to render the settings form.</li>
</ul>
'
    );

    // ── Tab 4: Import / Export ──
    $tabs['import_export'] = array(
        'title' => 'Import &amp; Export',
        'icon'  => 'bi-box-arrow-in-down',
        'content' => '
<h3 style="margin-top:0">Importing Themes</h3>
<ol>
<li>Go to <strong>FMZ Studio → Import / Export</strong>.</li>
<li>Upload a <code>.zip</code> file containing a theme package.</li>
<li>Select a parent theme (defaults to Master Style).</li>
<li>The theme is extracted to <code>themes/{slug}/</code>, synced to the database, and JS files are deployed.</li>
</ol>

<h4>ZIP Package Requirements</h4>
<ul>
<li>The ZIP must contain a directory with a valid <code>theme.json</code> file.</li>
<li><code>theme.json</code> can be at the ZIP root or inside exactly one folder (the importer searches one level deep).</li>
<li>The directory must contain a <code>templates/</code> folder with at least one <code>.html</code> file.</li>
<li>Any referenced CSS files must exist in the <code>css/</code> folder.</li>
<li>Any referenced JS files must exist in the <code>js/</code> folder.</li>
</ul>

<h3>Exporting Themes</h3>
<ol>
<li>Go to <strong>FMZ Studio → Import / Export</strong>.</li>
<li>Click <strong>Download ZIP</strong> next to any theme.</li>
<li>The entire <code>themes/{slug}/</code> directory is packaged into a ZIP file.</li>
</ol>

<h4>Exported ZIP Format</h4>
<p>The exported ZIP contains the theme in a top-level folder named after the theme. This ZIP is <strong>ready to import</strong> on another MyBB installation — just upload it on the Import page.</p>
<pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;font-size:12px;line-height:1.6;overflow-x:auto">
My_Theme.zip
└── My_Theme/
    ├── theme.json
    ├── css/
    │   ├── global.css
    │   └── ...
    ├── templates/
    │   ├── header/
    │   ├── footer/
    │   └── ...
    ├── js/
    │   └── main.js
    ├── images/
    │   └── logo.png
    └── functions/
        ├── hooks.php
        ├── options.php
        └── plugins/
            └── ...
</pre>

<h3>Creating a Theme ZIP Manually</h3>
<p>To create a theme package by hand:</p>
<ol>
<li>Create a folder (e.g. <code>my-theme/</code>).</li>
<li>Add a <code>theme.json</code> with at least a <code>"name"</code> field.</li>
<li>Create a <code>templates/</code> directory with your <code>.html</code> template files.</li>
<li>Add <code>css/</code>, <code>js/</code>, <code>images/</code> as needed.</li>
<li>ZIP the folder: <code>my-theme.zip</code> → containing <code>my-theme/theme.json</code>, <code>my-theme/templates/</code>, etc.</li>
<li>Upload via <strong>FMZ Studio → Import</strong>.</li>
</ol>

<div style="background:#fff8e1;border:1px solid #f0c36d;border-radius:4px;padding:10px 14px;margin-top:12px">
<strong>⚠ Tip:</strong> If re-importing a theme that already exists, FMZ Studio will replace the existing files on disk. The database entries are updated via sync.
</div>
'
    );

    // ── Tab 5: Editor ──
    $tabs['editor'] = array(
        'title' => 'Editor',
        'icon'  => 'bi-code-slash',
        'content' => '
<h3 style="margin-top:0">Built-in Code Editor</h3>
<p>FMZ Studio includes a Monaco-based code editor (the same engine behind VS Code) directly in the Admin CP.</p>

<h4>Getting There</h4>
<ol>
<li>Go to <strong>FMZ Studio → Manage</strong>.</li>
<li>Click <strong>Edit</strong> on any theme that exists on disk.</li>
</ol>

<h4>Features</h4>
<ul>
<li>Full syntax highlighting for HTML, CSS, JavaScript, PHP, and JSON.</li>
<li>Emmet abbreviations for HTML/CSS.</li>
<li>Multi-tab editing — open multiple files at once.</li>
<li>File tree with search, create, rename, and delete.</li>
<li>Changes are saved to disk <strong>and</strong> synced to the database instantly.</li>
</ul>

<h4>Keyboard Shortcuts</h4>
<table class="general" cellspacing="0" style="margin-top:8px">
<tr><td style="width:180px"><code>Ctrl+S</code></td><td>Save &amp; sync to database</td></tr>
<tr><td><code>Ctrl+W</code></td><td>Close current tab</td></tr>
<tr><td><code>Ctrl+Z</code></td><td>Undo</td></tr>
<tr><td><code>Ctrl+Shift+Z</code></td><td>Redo</td></tr>
<tr><td><code>Ctrl+F</code></td><td>Find</td></tr>
<tr><td><code>Ctrl+H</code></td><td>Find &amp; Replace</td></tr>
<tr><td><code>Ctrl+Shift+I</code></td><td>Format document</td></tr>
<tr><td><code>Ctrl+D</code></td><td>Add selection to next match</td></tr>
<tr><td><code>Alt+↑/↓</code></td><td>Move line up/down</td></tr>
</table>

<h4>Right-Click Context Menu</h4>
<p>Right-click files or folders in the file tree for options like: New File, New Folder, Rename, Delete.</p>
'
    );

    // ── Tab 6: Page Builder ──
    $tabs['pagebuilder'] = array(
        'title' => 'Page Builder',
        'icon'  => 'bi-file-earmark-richtext',
        'content' => '
<h3 style="margin-top:0">Page Builder</h3>
<p>The Page Builder mini plugin lets you create standalone pages on your forum with full HTML/CSS/JS editing powered by the Monaco editor. Pages are served via clean URLs and can optionally replace the forum index.</p>

<h4>Key Features</h4>
<ul>
<li><strong>Monaco HTML Editor</strong> &mdash; Full VS Code-like editor with syntax highlighting, Emmet, and autocomplete for writing page content.</li>
<li><strong>Clean URLs</strong> &mdash; Pages are accessible at <code>yourdomain.com/forum/page-slug</code> with no query strings.</li>
<li><strong>Front Page Override</strong> &mdash; Designate any page as the forum\'s front page, replacing the default forum index.</li>
<li><strong>Custom CSS &amp; JS</strong> &mdash; Each page can include its own custom stylesheet and JavaScript.</li>
<li><strong>User Group Permissions</strong> &mdash; Restrict page visibility to specific user groups.</li>
<li><strong>Template Variables</strong> &mdash; Use MyBB template variables like <code>{$mybb-&gt;user[\'username\']}</code> and <code>{$lang-&gt;welcome}</code> in page content.</li>
<li><strong>Conditional Blocks</strong> &mdash; Show/hide content with <code>&lt;if&gt;</code> tags based on user state or group.</li>
<li><strong>Draft Preview</strong> &mdash; Preview unsaved changes via a secure admin-only preview URL.</li>
</ul>

<h4>Step-by-Step: Create a Page</h4>
<ol>
<li>Go to <strong>FMZ Studio &rarr; Page Manager</strong>.</li>
<li>Click <strong>Create New Page</strong>.</li>
<li>Enter a <strong>Title</strong> and <strong>Slug</strong> (URL-safe identifier).</li>
<li>Write your page content in the Monaco HTML editor. A default Bootstrap 5 template is provided.</li>
<li>Optionally add Custom CSS or Custom JS in the sidebar fields.</li>
<li>Set <strong>Visibility</strong> to Published or Draft.</li>
<li>Click <strong>Save Page</strong>.</li>
<li>Visit <code>yourdomain.com/forum/your-slug</code> to see the live page.</li>
</ol>

<h4>Step-by-Step: Set a Front Page</h4>
<ol>
<li>Go to <strong>FMZ Studio &rarr; Page Manager</strong>.</li>
<li>In the <strong>Front Page</strong> dropdown at the top, select the page you want as your forum\'s landing page.</li>
<li>Click <strong>Save</strong>.</li>
<li>Visitors to your forum index will now see the selected page instead of the default forum list.</li>
<li>To restore the default forum index, set the dropdown back to <em>None</em>.</li>
</ol>

<h4>Template Variables &amp; Conditionals</h4>
<p>Page content is processed through a template engine that supports MyBB variables and conditional logic:</p>
<pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;font-size:12px;line-height:1.6;overflow-x:auto">
<span style="color:#6a9955">&lt;!-- MyBB variables --&gt;</span>
<span style="color:#569cd6">&lt;p&gt;</span>Welcome, <span style="color:#4ec9b0">{$mybb-&gt;user[\'username\']}</span>!<span style="color:#569cd6">&lt;/p&gt;</span>
<span style="color:#569cd6">&lt;p&gt;</span>Board name: <span style="color:#4ec9b0">{$mybb-&gt;settings[\'bbname\']}</span><span style="color:#569cd6">&lt;/p&gt;</span>

<span style="color:#6a9955">&lt;!-- Conditional blocks --&gt;</span>
<span style="color:#c586c0">&lt;if $mybb-&gt;user[\'uid\'] &gt; 0 then&gt;</span>
    <span style="color:#569cd6">&lt;p&gt;</span>You are logged in.<span style="color:#569cd6">&lt;/p&gt;</span>
<span style="color:#c586c0">&lt;else&gt;</span>
    <span style="color:#569cd6">&lt;p&gt;</span>Please log in to continue.<span style="color:#569cd6">&lt;/p&gt;</span>
<span style="color:#c586c0">&lt;/if&gt;</span>
</pre>

<h4>Clean URL Setup</h4>
<p>Page Builder requires URL rewriting for clean URLs. Add these rules to your <code>.htaccess</code> (Apache) or nginx config:</p>
<pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;font-size:12px;line-height:1.6;overflow-x:auto">
<span style="color:#6a9955"># Apache (.htaccess)</span>
<span style="color:#569cd6">RewriteRule</span> ^([a-zA-Z0-9][a-zA-Z0-9\-_]*)$ misc.php?fmz_page= [L,QSA]
<span style="color:#569cd6">RewriteRule</span> ^([a-zA-Z0-9][a-zA-Z0-9\-_]*)/  [R=301,L]
</pre>
<p>These rules route <code>/your-slug</code> to the Page Builder handler via <code>misc.php</code>.</p>

<h4>User Group Permissions</h4>
<p>When editing a page, you can restrict access to specific MyBB user groups. If no groups are selected, the page is visible to everyone. Users without permission see a styled Access Denied page.</p>
'
    );
    // ── Tab 7: Developer Guide ──
    $tabs['developer'] = array(
        'title' => 'Developer Guide',
        'icon'  => 'bi-braces',
        'content' => '
<h3 style="margin-top:0">How Sync Works</h3>
<ol>
<li><strong>Files → XML:</strong> FMZ Studio reads <code>theme.json</code>, CSS from <code>css/</code>, and templates from <code>templates/</code>. It builds a standard MyBB theme XML string.</li>
<li><strong>XML → Database:</strong> The XML is imported using MyBB\'s native <code>import_theme_xml()</code>, creating or replacing the theme, its template set, and stylesheets.</li>
<li><strong>JS Deployment:</strong> JS files from the theme\'s <code>js/</code> folder are copied to <code>jscripts/</code>.</li>
</ol>

<h4>Auto-Sync Internals</h4>
<ul>
<li>Runs on the <code>global_start</code> hook (frontend only).</li>
<li>Checks file <code>mtime</code> against a per-theme <code>.fmz_last_sync</code> marker file.</li>
<li>Only changed files are synced — CSS rows updated in <code>themestylesheets</code>, template rows in <code>templates</code>.</li>
<li>Stylesheet cache files (<code>cache/themes/themeN/</code>) are rebuilt after CSS changes.</li>
</ul>

<h4>Step-by-Step: Add a Custom Hook</h4>
<ol>
<li>Open <code>themes/{slug}/functions/hooks.php</code> in your editor.</li>
<li>At the top of the file, register your hook:
<pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;font-size:12px;margin:8px 0">$plugins->add_hook(\'showthread_start\', \'my_custom_function\');</pre></li>
<li>Define your function below:
<pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;font-size:12px;margin:8px 0">function my_custom_function() {
    global $mybb, $templates;
    // Your code here
}</pre></li>
<li>Save the file — if auto-sync is on, it loads immediately on next page view.</li>
</ol>

<h4>Step-by-Step: Add a Theme Option</h4>
<ol>
<li>Open <code>themes/{slug}/functions/options.php</code>.</li>
<li>Add an entry to the returned array:
<pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;font-size:12px;margin:8px 0">\'my_option\' => array(
    \'title\'       => \'My Option\',
    \'description\' => \'What this option controls.\',
    \'type\'        => \'yesno\',  // text, textarea, yesno, select, color, numeric
    \'default\'     => \'1\',
),</pre></li>
<li>Read the value in <code>hooks.php</code> using <code>$opts[\'my_option\']</code> (options are loaded by FMZ Studio\'s core).</li>
<li>Save both files and visit <strong>Global FMZ Options</strong> — your new option appears.</li>
</ol>

<h4>Step-by-Step: Create a Mini Plugin</h4>
<ol>
<li>Create a folder: <code>themes/{slug}/functions/plugins/my-plugin/</code></li>
<li>Create <code>plugin.json</code>:
<pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;font-size:12px;margin:8px 0">{
  "id": "my-plugin",
  "name": "My Plugin",
  "version": "1.0.0",
  "description": "What it does.",
  "author": "Your Name"
}</pre></li>
<li>Create <code>init.php</code> — register your hooks here:
<pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;font-size:12px;margin:8px 0">&lt;?php
global $plugins;
$plugins->add_hook(\'index_end\', \'my_plugin_index\');

function my_plugin_index() {
    // Your code here
}</pre></li>
<li>Go to <strong>FMZ Studio → Manage Plugins</strong> → enable your plugin.</li>
<li>Optionally add <code>css/</code>, <code>js/</code> folders for auto-loaded assets, or <code>options.php</code> for settings.</li>
<li>Enabled plugins with settings appear as individual entries in the FMZ Studio sidebar.</li>
</ol>

<h4>Editor API Endpoints</h4>
<table class="general" cellspacing="0" style="margin-top:8px">
<tr><td style="width:300px"><code>?action=api_filetree&amp;slug=...</code></td><td>GET — File tree for a theme dir</td></tr>
<tr><td><code>?action=api_readfile&amp;slug=...&amp;path=...</code></td><td>GET — File content</td></tr>
<tr><td><code>?action=api_savefile</code></td><td>POST — Save &amp; sync (requires CSRF token)</td></tr>
<tr><td><code>?action=api_createfile</code></td><td>POST — Create new file</td></tr>
<tr><td><code>?action=api_createfolder</code></td><td>POST — Create new folder</td></tr>
<tr><td><code>?action=api_deletefile</code></td><td>POST — Delete a file</td></tr>
<tr><td><code>?action=api_deletefolder</code></td><td>POST — Delete a folder</td></tr>
<tr><td><code>?action=api_rename</code></td><td>POST — Rename file or folder</td></tr>
</table>

<h4>Image Upload API</h4>
<p><code>POST fmz_upload.php</code> — Handles image uploads from the WYSIWYG editor.</p>
<ul>
<li>Validates login, CSRF token, MIME type, file extension, and file size.</li>
<li>Saves to <code>uploads/fmz_images/YYYY-MM/{uid}_{random}.{ext}</code>.</li>
<li>Returns: <code>{ "success": true, "url": "...", "width": N, "height": N }</code></li>
</ul>

<h4>CSS Custom Properties</h4>
<p>All theme colors use CSS variables on <code>:root</code>. Override via Global FMZ Options or custom CSS:</p>
<pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;font-size:12px;margin:8px 0">
--tekbb-accent           /* Primary accent */
--tekbb-accent-hover     /* Accent hover */
--tekbb-accent-subtle    /* Semi-transparent accent (auto-generated) */
--tekbb-heading-bg       /* Card/table header bg */
--tekbb-surface          /* Card/panel backgrounds */
--tekbb-border           /* Borders and dividers */
--tekbb-muted            /* Secondary text */
--tekbb-text-inv         /* Text on dark/accent backgrounds */
--tekbb-link / link-hover
--tekbb-btn-bg / btn-hover
--tekbb-nav-bg
--tekbb-footer-bg / footer-color
--tekbb-shadow           /* Box shadow token */
</pre>

<h4>Mini Plugin Loading Order</h4>
<ol>
<li><code>global_intermediate</code> hook fires → <code>loadMiniPlugins()</code> scans <code>functions/plugins/</code></li>
<li>Enabled plugins\' <code>init.php</code> are <code>include_once</code>\'d — register your hooks here.</li>
<li><code>pre_output_page</code> hook fires → CSS/JS assets from plugin <code>css/</code> and <code>js/</code> folders are injected into the page HTML.</li>
<li>Plugin hooks run at their registered hook points.</li>
</ol>

<h4>File Structure Reference</h4>
<pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;font-size:12px;line-height:1.6;overflow-x:auto">
<span style="color:#569cd6">inc/plugins/</span>
├── fmz.php                      ← plugin entry point (hooks, install, auto-sync)
└── fmzstudio/
    └── core.php                 ← core class (import, export, sync, API)

<span style="color:#569cd6">admin/modules/fmzstudio/</span>
├── module_meta.php              ← ACP registration (sidebar menu)
└── fmzstudio.php                ← ACP pages (manage, editor, docs, etc.)

<span style="color:#569cd6">jscripts/fmzstudio/</span>
├── editor.js                    ← Monaco editor client-side logic
└── pagebuilder.js               ← Page Builder editor logic

<span style="color:#569cd6">themes/{slug}/functions/plugins/</span>
└── fmz-pagebuilder/
    ├── init.php                 ← page routing &amp; front page hooks
    └── renderer.php             ← page template engine

fmz_upload.php                   ← public image upload endpoint
</pre>
'
    );

    // ── Tab 8: Troubleshooting ──
    $tabs['troubleshooting'] = array(
        'title' => 'Troubleshooting',
        'icon'  => 'bi-bug',
        'content' => '
<h3 style="margin-top:0">Common Issues &amp; Fixes</h3>

<h4>Theme not showing changes after editing files</h4>
<ol>
<li>Check that <strong>Auto-Sync</strong> is enabled (<strong>ACP → Configuration → Settings → FMZ Studio</strong>).</li>
<li>If disabled, go to <strong>FMZ Studio → Manage</strong> and click <strong>Sync</strong> on the theme.</li>
<li>Clear theme cache: Delete files inside <code>cache/themes/</code> and reload.</li>
<li>If editing CSS, MyBB caches stylesheets in <code>cache/themes/themeN/</code> — these are rebuilt on sync.</li>
</ol>

<h4>Theme options not saving</h4>
<ol>
<li>Ensure your <code>options.php</code> returns a valid PHP array with no syntax errors.</li>
<li>Check that the <code>default.json</code> file is writable by the web server.</li>
<li>Verify the option key is unique — duplicate keys silently overwrite.</li>
</ol>

<h4>Mini Plugin not loading</h4>
<ol>
<li>Confirm the plugin is <strong>enabled</strong> in <strong>FMZ Studio → Manage Plugins</strong>.</li>
<li>Check that <code>plugin.json</code> exists and has a valid <code>"id"</code> field.</li>
<li>Check that <code>init.php</code> exists — this is required.</li>
<li>Look for PHP errors in your server error log.</li>
</ol>

<h4>Page Builder pages not loading (404)</h4>
<ol>
<li>Ensure <code>.htaccess</code> has the Page Builder rewrite rules (see Page Builder tab).</li>
<li>Verify <code>mod_rewrite</code> is enabled in Apache.</li>
<li>Check that the page slug is valid — only letters, numbers, hyphens, and underscores.</li>
<li>Confirm the page is set to <strong>Published</strong> (not Draft) in the Page Manager.</li>
</ol>

<h4>Front page not showing</h4>
<ol>
<li>Go to <strong>FMZ Studio → Page Manager</strong> and ensure a page is selected in the <strong>Front Page</strong> dropdown.</li>
<li>Verify the selected page is set to Published.</li>
<li>Check that the Page Builder mini plugin is enabled in <strong>Manage Plugins</strong>.</li>
</ol>

<h4>Broken layout after import</h4>
<ol>
<li>Go to <strong>FMZ Studio → Manage</strong> and re-sync the theme.</li>
<li>Set the theme as default: <strong>ACP → Themes</strong> → select the FMZ theme → <strong>Set as Default</strong>.</li>
<li>Ensure Bootstrap vendor files exist in <code>themes/{slug}/vendor/</code>.</li>
<li>Check <code>headerinclude.html</code> references the correct vendor file paths.</li>
</ol>

<h4>Color palette changes not applying</h4>
<ol>
<li>Colors only override when they differ from the default values in <code>options.php</code>.</li>
<li>Ensure you\'re editing the correct palette — Light or Dark — matching your <code>color_mode</code> setting.</li>
<li>Hard-refresh the page (<kbd>Ctrl+Shift+R</kbd>) to bypass browser CSS cache.</li>
</ol>

<h4>Loading bar not visible</h4>
<ol>
<li>Ensure <strong>Loading Bar</strong> is set to <em>Yes</em> in <strong>Global FMZ Options → Layout &amp; Effects</strong>.</li>
<li>The bar uses the accent color — if your accent matches the page background, it won\'t be visible.</li>
<li>The bar only appears during page navigation (link clicks and form submits).</li>
</ol>
'
    );

    // ── Render tabbed interface ──
    $activeTab = $mybb->get_input('tab');
    if (empty($activeTab) || !isset($tabs[$activeTab])) {
        $activeTab = 'getting_started';
    }

    echo '<style>
.fmz-docs-wrap{display:flex;gap:0;min-height:500px;border:1px solid #ccc;border-radius:6px;overflow:hidden;background:#fff}
.fmz-docs-sidebar{width:200px;min-width:200px;background:#f5f5f5;border-right:1px solid #ddd;padding:8px 0}
.fmz-docs-sidebar a{display:flex;align-items:center;gap:8px;padding:9px 16px;color:#555;font-size:13px;text-decoration:none;border-left:3px solid transparent;transition:all .15s}
.fmz-docs-sidebar a:hover{background:#eee;color:#333}
.fmz-docs-sidebar a.active{background:#e8f5f4;color:#0d9488;border-left-color:#0d9488;font-weight:600}
.fmz-docs-sidebar a i{font-size:15px;width:18px;text-align:center}
.fmz-docs-content{flex:1;padding:24px 32px;overflow-y:auto;max-height:70vh}
.fmz-docs-content h3{color:#0b7c72;border-bottom:2px solid #e0f2f1;padding-bottom:6px;margin-top:24px}
.fmz-docs-content h3:first-child{margin-top:0}
.fmz-docs-content h4{color:#333;margin-top:18px}
.fmz-docs-content code{background:#f0f0f0;padding:1px 5px;border-radius:3px;font-size:12px;color:#c7254e}
.fmz-docs-content pre code{background:none;padding:0;color:inherit}
.fmz-docs-content ul,.fmz-docs-content ol{padding-left:24px}
.fmz-docs-content li{margin-bottom:4px}
.fmz-docs-content table.general{width:100%;border:1px solid #ddd;border-radius:4px;overflow:hidden}
.fmz-docs-content table.general td{padding:8px 12px;border-bottom:1px solid #eee;font-size:12.5px;vertical-align:top}
.fmz-docs-content table.general tr:last-child td{border-bottom:none}
</style>';

    echo '<div class="fmz-docs-wrap">';
    echo '<div class="fmz-docs-sidebar">';
    foreach ($tabs as $key => $tab) {
        $cls = ($key === $activeTab) ? ' class="active"' : '';
        echo '<a href="index.php?module=fmzstudio-docs&tab=' . $key . '"' . $cls . '>'
           . '<i class="bi ' . $tab['icon'] . '"></i>'
           . $tab['title'] . '</a>';
    }
    echo '</div>';
    echo '<div class="fmz-docs-content">';
    echo $tabs[$activeTab]['content'];
    echo '</div>';
    echo '</div>';

    $page->output_footer();
    exit;
}

/* ====================================================================
   License Page — Activate / Deactivate license key
   ==================================================================== */

if ($action === 'license') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("License");

    $errors  = [];
    $success = '';

    // Handle POST actions
    if ($mybb->request_method === 'post') {
        verify_post_check($mybb->get_input('my_post_key'));

        $licAction = $mybb->get_input('lic_action');

        if ($licAction === 'activate') {
            $inputKey = trim($mybb->get_input('license_key'));
            if (empty($inputKey)) {
                $errors[] = 'Please enter a license key.';
            } else {
                $result = FMZLicense::activate($inputKey);
                if ($result['success']) {
                    $success = htmlspecialchars_uni($result['message']);
                } else {
                    $errors[] = htmlspecialchars_uni($result['message']);
                }
            }
        } elseif ($licAction === 'deactivate') {
            $result = FMZLicense::deactivate();
            if ($result['success']) {
                $success = htmlspecialchars_uni($result['message']);
            } else {
                $errors[] = htmlspecialchars_uni($result['message']);
            }
        }
    }

    $page->output_header("FMZ Studio - License");

    // Show errors / success via standard MyBB flash
    if (!empty($errors)) {
        $page->output_inline_error($errors);
    }
    if (!empty($success)) {
        echo '<div class="alert" style="padding:10px 15px;margin-bottom:15px;background:#e8f8f5;border:1px solid #0d9488;border-radius:4px;color:#0b5e57"><i class="bi bi-check-circle" style="margin-right:6px"></i>' . $success . '</div>';
    }

    // Current license state
    $currentKey    = FMZLicense::getKey();
    $currentStatus = FMZLicense::getStatus();
    $currentEmail  = FMZLicense::getEmail();
    $currentExpiry = FMZLicense::getExpiry();
    $currentDomain = FMZLicense::getDomain();
    $isValid       = FMZLicense::isValid();

    $statusLabels = [
        'valid'            => 'Active',
        'reissued'         => 'Active (Reissued)',
        'redistributable'  => 'Active (Redistributable)',
        'expired'          => 'Expired',
        'invalid'          => 'Invalid',
        'already_active'   => 'Already In Use',
    ];

    if ($isValid) {
        // ── Licensed: show info table + deactivation ──
        $statusLabel = $statusLabels[$currentStatus] ?? ucfirst($currentStatus);
        $maskedKey   = substr($currentKey, 0, 4) . str_repeat('*', max(0, strlen($currentKey) - 8)) . substr($currentKey, -4);
        $expiryText  = ($currentExpiry === 'lifetime') ? 'Lifetime' : date('F j, Y', strtotime($currentExpiry));

        $table = new Table;
        $table->construct_header("Field", ['width' => 180]);
        $table->construct_header("Value");

        $statusBadge = '<span style="background:#0d9488;color:#fff;padding:2px 10px;border-radius:4px;font-size:11px;font-weight:600">' . htmlspecialchars_uni($statusLabel) . '</span>';
        $table->construct_cell('<strong>Status</strong>');
        $table->construct_cell($statusBadge);
        $table->construct_row();

        $table->construct_cell('<strong>License Key</strong>');
        $table->construct_cell('<code>' . htmlspecialchars_uni($maskedKey) . '</code>');
        $table->construct_row();

        if ($currentEmail) {
            $table->construct_cell('<strong>Email</strong>');
            $table->construct_cell(htmlspecialchars_uni($currentEmail));
            $table->construct_row();
        }

        $table->construct_cell('<strong>Expires</strong>');
        $table->construct_cell(htmlspecialchars_uni($expiryText));
        $table->construct_row();

        $table->construct_cell('<strong>Domain</strong>');
        $table->construct_cell('<code>' . htmlspecialchars_uni($currentDomain) . '</code>');
        $table->construct_row();

        $table->output("License Information");

        // Deactivate form
        $deactivateForm = new Form("index.php?module=fmzstudio-license", "post");
        echo $deactivateForm->generate_hidden_field('lic_action', 'deactivate');

        $form_container = new FormContainer("Transfer License");
        $form_container->output_row(
            "Deactivate License",
            "Deactivate this license key to use it on a different MyBB installation. The plugin will stop working here until a new key is entered.",
            '<button type="submit" class="button" onclick="return confirm(\'Are you sure you want to deactivate this license? FMZ Studio will stop working until a new key is entered.\')"><i class="bi bi-x-circle"></i> Deactivate License</button>'
        );
        $form_container->end();

        $deactivateForm->end();

    } else {
        // ── Not licensed: show activation form ──
        if (!empty($currentKey) && empty($errors)) {
            $statusLabel = $statusLabels[$currentStatus] ?? 'Invalid';
            $page->output_inline_error(['Your current license is <strong>' . htmlspecialchars_uni($statusLabel) . '</strong>. Please enter a valid license key to continue using FMZ Studio.']);
        }

        $activateForm = new Form("index.php?module=fmzstudio-license", "post");
        echo $activateForm->generate_hidden_field('lic_action', 'activate');

        $form_container = new FormContainer("Activate License");

        $form_container->output_row(
            "License Key <em>*</em>",
            "Enter your FMZ Studio license key. You received the key via email after purchase from <a href=\"https://tektove.com\">tektove.com</a>.",
            $activateForm->generate_text_box('license_key', '', ['style' => 'width:300px;font-family:monospace;letter-spacing:1px', 'placeholder' => 'XXXX-XXXX-XXXX-XXXX'])
        );

        $form_container->output_row(
            "Domain",
            "Your license will be bound to this domain.",
            '<code>' . htmlspecialchars_uni(FMZLicense::getSiteDomain()) . '</code>'
        );

        $form_container->end();

        $buttons = [$activateForm->generate_submit_button("Activate License")];
        $activateForm->output_submit_wrapper($buttons);

        $activateForm->end();

        // Help box
        echo fmz_help_box("Need help?",
            '<ul style="margin:0;padding-left:20px;line-height:1.8">'
            . '<li>You can find your license key in your <a href="https://tektove.com/my-account/">Tektove account</a> under Orders.</li>'
            . '<li>Each license key can be active on one domain at a time. Deactivate from the old site first to transfer.</li>'
            . '<li><strong>Redistributable</strong> licenses can be activated on multiple domains simultaneously.</li>'
            . '<li>Contact <a href="mailto:support@tektove.com">support@tektove.com</a> if your key was lost or compromised — we can reissue it.</li>'
            . '</ul>'
        );
    }

    $page->output_footer();
    exit;
}

/* ====================================================================
   File API Endpoints — secondary license check on all write operations
   ==================================================================== */

if (in_array($action, ['api_savefile', 'api_createfile', 'api_createfolder', 'api_deletefile', 'api_deletefolder', 'api_rename', 'api_sync'], true)) {
    if (!FMZLicense::assertLicensed()) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'License validation failed.']);
        exit;
    }
}

if ($action === 'api_filetree') {
    header('Content-Type: application/json');
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $tree = $fmz->getFileTree($slug);
    if ($tree === false) {
        echo json_encode(array('error' => 'Theme not found on disk.'));
    } else {
        echo json_encode($tree);
    }
    exit;
}

if ($action === 'api_readfile') {
    header('Content-Type: application/json');
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $path = $mybb->get_input('path');
    $data = $fmz->readThemeFile($slug, $path);
    if ($data === false) {
        echo json_encode(array('error' => 'Cannot read file.'));
    } else {
        echo json_encode($data);
    }
    exit;
}

if ($action === 'api_savefile') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug    = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $path    = $mybb->get_input('path');
    $content = $mybb->get_input('content');
    $ok = $fmz->writeThemeFile($slug, $path, $content, true);
    echo json_encode(array('success' => $ok, 'time' => date('H:i:s'), 'errors' => $fmz->getErrors()));
    exit;
}

if ($action === 'api_filelist') {
    header('Content-Type: application/json');
    $slug  = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $files = $fmz->getFlatFileList($slug);
    echo json_encode(array('files' => $files !== false ? $files : array()));
    exit;
}

if ($action === 'api_createfile') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $path = $mybb->get_input('path');
    $ok   = $fmz->createThemeFile($slug, $path);
    echo json_encode(array('success' => $ok, 'errors' => $fmz->getErrors()));
    exit;
}

if ($action === 'api_createfolder') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $path = $mybb->get_input('path');
    $ok   = $fmz->createThemeFolder($slug, $path);
    echo json_encode(array('success' => $ok, 'errors' => $fmz->getErrors()));
    exit;
}

if ($action === 'api_deletefile') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $path = $mybb->get_input('path');
    $ok   = $fmz->deleteThemeFile($slug, $path);
    echo json_encode(array('success' => $ok, 'errors' => $fmz->getErrors()));
    exit;
}

if ($action === 'api_deletefolder') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $path = $mybb->get_input('path');
    $ok   = $fmz->deleteThemeFolder($slug, $path);
    echo json_encode(array('success' => $ok, 'errors' => $fmz->getErrors()));
    exit;
}

if ($action === 'api_rename') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug    = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $oldPath = $mybb->get_input('old_path');
    $newPath = $mybb->get_input('new_path');
    $ok = $fmz->renameThemePath($slug, $oldPath, $newPath);
    echo json_encode(array('success' => $ok, 'errors' => $fmz->getErrors()));
    exit;
}

if ($action === 'api_sync') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $tid  = $fmz->syncToDatabase($slug);
    echo json_encode(array(
        'success' => $tid !== false,
        'tid'     => $tid,
        'errors'  => $fmz->getErrors()
    ));
    exit;
}

/* ====================================================================
   Editor Page (full-page Monaco editor)
   ==================================================================== */

if ($action === 'editor') {
    // Secondary license validation — independent code path
    if (!FMZLicense::assertLicensed()) {
        flash_message('License validation failed. Please re-enter your license key.', 'error');
        admin_redirect("index.php?module=fmzstudio-license");
    }

    $slug = $mybb->get_input('slug');
    if (empty($slug)) {
        flash_message('No theme specified.', 'error');
        admin_redirect("index.php?module=fmzstudio-manage");
    }

    // Verify the theme directory exists
    $themeDir = MYBB_ROOT . 'themes/' . preg_replace('/[^a-z0-9\-]/', '', $slug);
    if (!is_dir($themeDir)) {
        flash_message('Theme directory not found on disk.', 'error');
        admin_redirect("index.php?module=fmzstudio-manage");
    }

    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Editor: " . htmlspecialchars_uni($slug));

    // Monaco loader CDN
    $page->extra_header .= '
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.50.0/min/vs/loader.min.js"></script>';

    $page->output_header("FMZ Studio - Editor");

    $post_key = $mybb->post_code;
    $base_url = "index.php?module=fmzstudio-manage";
    $safe_slug = htmlspecialchars_uni($slug);
    $bburl = $mybb->settings['bburl'];

    echo <<<HTML
<div id="fmzEditorConfig"
     data-base-url="{$base_url}"
     data-post-key="{$post_key}"
     data-slug="{$safe_slug}"
     style="display:none"></div>

<style>
/* -- FMZ Studio Editor Styles (Light) -- */
.fmz-editor-wrap{display:flex;height:calc(100vh - 120px);background:#ffffff;border-radius:6px;overflow:hidden;position:relative;border:1px solid #dee2e6}
#fmz-sidebar{width:260px;min-width:160px;background:#f5f5f5;display:flex;flex-direction:column;border-right:1px solid #dee2e6;transition:width .2s}
#fmz-sidebar.collapsed{width:0;min-width:0;overflow:hidden;border-right:none}
#fmz-btn-collapse{background:#e8e8e8;border:none;color:#666;font-size:16px;cursor:pointer;padding:6px 3px;border-radius:0;line-height:1;display:flex;align-items:center;z-index:2;border-right:1px solid #dee2e6}
#fmz-btn-collapse:hover{background:#ddd;color:#333}
.fmz-sidebar-header{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;background:#e8e8e8;border-bottom:1px solid #dee2e6;gap:4px}
.fmz-sidebar-header .fmz-sidebar-title{font-size:11px;font-weight:600;color:#555;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;overflow:hidden}
.fmz-sidebar-btns{display:flex;gap:2px}
.fmz-sidebar-btns button{background:none;border:1px solid transparent;color:#666;font-size:14px;cursor:pointer;padding:2px 5px;border-radius:3px;line-height:1}
.fmz-sidebar-btns button:hover{background:#ddd;color:#333;border-color:#ccc}
.fmz-search-wrap{padding:6px 8px;border-bottom:1px solid #dee2e6}
#fmz-file-search{width:100%;background:#fff;border:1px solid #ccc;color:#333;padding:4px 8px;border-radius:3px;font-size:12px;outline:none;box-sizing:border-box}
#fmz-file-search:focus{border-color:#0d9488}
#fmz-file-tree{flex:1;overflow:auto;padding:4px 0;font-size:13px;font-family:Consolas,'Courier New',monospace}
.fmz-tree-item{display:flex;align-items:center;padding:3px 8px;cursor:pointer;color:#333;white-space:nowrap;gap:4px;user-select:none}
.fmz-tree-item:hover{background:#e8f0fe}
.fmz-tree-item.active{background:#d3e3fd;color:#1a1a1a}
.fmz-tree-arrow{font-size:10px;width:14px;text-align:center;color:#888;flex-shrink:0}
.fmz-tree-icon{font-size:14px;flex-shrink:0}
.fmz-tree-name{flex:1;overflow:hidden;text-overflow:ellipsis}
.fmz-tree-children{padding-left:16px}
.fmz-tree-folder-dirty{background:#e2b340;color:#fff;font-size:9px;font-weight:bold;padding:0 4px;border-radius:3px;margin-left:4px}
.fmz-loading,.fmz-error{padding:20px;color:#888;text-align:center;font-size:13px}
.fmz-error{color:#c0392b}
#fmz-resize-handle{width:4px;background:#dee2e6;cursor:col-resize;flex-shrink:0;transition:background .15s}
#fmz-resize-handle:hover,#fmz-resize-handle.active{background:#0d9488}
.fmz-main{flex:1;display:flex;flex-direction:column;min-width:0}
.fmz-tabs-bar{display:flex;background:#f5f5f5;border-bottom:1px solid #dee2e6;overflow-x:auto;flex-shrink:0;min-height:35px}
.fmz-tab{display:flex;align-items:center;padding:6px 12px;color:#666;font-size:12px;cursor:pointer;border-right:1px solid #dee2e6;white-space:nowrap;gap:6px;font-family:Consolas,monospace;max-width:180px}
.fmz-tab:hover{background:#e8f0fe;color:#333}
.fmz-tab.active{background:#ffffff;color:#1a1a1a;border-bottom:2px solid #0d9488}
.fmz-tab .fmz-tab-dirty{color:#b8860b;font-weight:bold}
.fmz-tab .fmz-tab-close{opacity:.5;font-size:14px;line-height:1}
.fmz-tab .fmz-tab-close:hover{opacity:1;color:#c0392b}
#fmz-monaco{flex:1;min-height:0}
.fmz-monaco-placeholder{display:flex;align-items:center;justify-content:center;height:100%;color:#999;font-size:15px;font-style:italic}
.fmz-statusbar{display:flex;align-items:center;justify-content:space-between;padding:2px 12px;background:#007acc;color:#fff;font-size:11px;flex-shrink:0}
.fmz-notify{position:fixed;top:20px;right:20px;padding:10px 20px;border-radius:6px;color:#fff;font-size:13px;z-index:99999;animation:fmzFadeIn .3s;transition:opacity .3s}
.fmz-notify-success{background:#0d9488}
.fmz-notify-error{background:#c0392b}
.fmz-notify-info{background:#007acc}
@keyframes fmzFadeIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.fmz-status-saving{color:#b8860b}
.fmz-status-saved{color:#0d9488}
.fmz-status-error{color:#c0392b}
.fmz-context-menu{position:fixed;background:#ffffff;border:1px solid #dee2e6;border-radius:4px;padding:4px 0;min-width:160px;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,.15);font-size:12px}
.fmz-context-menu-item{padding:6px 16px;color:#333;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:6px}
.fmz-context-menu-item:hover{background:#e8f0fe;color:#1a1a1a}
.fmz-context-menu-item.danger{color:#c0392b}
.fmz-context-menu-item.danger:hover{background:#fde8e8;color:#a02020}
.fmz-context-menu-icon{font-size:14px;flex-shrink:0}
.fmz-context-menu-sep{height:1px;background:#dee2e6;margin:4px 0}
</style>

<div class="fmz-editor-wrap">
    <div id="fmz-sidebar">
        <div class="fmz-sidebar-header">
            <span class="fmz-sidebar-title">Explorer</span>
            <div class="fmz-sidebar-btns">
                <button id="fmz-btn-newfile" title="New File"><i class="bi bi-file-earmark-plus"></i></button>
                <button id="fmz-btn-newfolder" title="New Folder"><i class="bi bi-folder-plus"></i></button>
                <button id="fmz-btn-savesync" title="Save &amp; Sync"><i class="bi bi-floppy"></i></button>
                <button id="fmz-btn-collapse-all" title="Collapse All Folders"><i class="bi bi-arrows-collapse"></i></button>
            </div>
        </div>
        <div class="fmz-search-wrap">
            <input type="text" id="fmz-file-search" placeholder="Search files..." />
        </div>
        <div id="fmz-file-tree"></div>
    </div>
    <button id="fmz-btn-collapse" title="Toggle Sidebar"><i class="bi bi-layout-sidebar-inset"></i></button>
    <div id="fmz-resize-handle"></div>
    <div class="fmz-main">
        <div class="fmz-tabs-bar" id="fmz-tab-bar"></div>
        <div id="fmz-monaco">
            <div class="fmz-monaco-placeholder">Select a file to begin editing</div>
        </div>
        <div class="fmz-statusbar">
            <span id="fmz-status-sync">Ready</span>
            <div style="display:flex;gap:16px">
                <span id="fmz-status-pos"></span>
                <span id="fmz-status-lang"></span>
            </div>
        </div>
    </div>
</div>

<div id="fmz-notifications" style="position:fixed;top:20px;right:20px;z-index:100000;display:flex;flex-direction:column;gap:8px"></div>

<script src="{$bburl}/jscripts/fmzstudio/editor.js"></script>
HTML;

    $page->output_footer();
    exit;
}

/* ====================================================================
   Export Download (POST handler — keeps working via form submit)
   ==================================================================== */

if ($action === 'export') {
    if ($mybb->request_method === 'post' && !empty($mybb->input['tid'])) {
        verify_post_check($mybb->get_input('my_post_key'));

        $tid = $mybb->get_input('tid', MyBB::INPUT_INT);
        $zipPath = $fmz->exportTheme($tid);

        if ($zipPath && file_exists($zipPath)) {
            $filename = basename($zipPath);
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            $fmz->cleanup();
            exit;
        } else {
            $errors = $fmz->getErrors();
            flash_message(implode('<br>', $errors), 'error');
            admin_redirect("index.php?module=fmzstudio-import_export");
        }
    }
    // GET request on old /export URL → redirect to combined page
    admin_redirect("index.php?module=fmzstudio-import_export");
}

/* ====================================================================
   Import Upload (POST handler — redirect back to combined page)
   ==================================================================== */

if ($action === 'import') {
    if ($mybb->request_method === 'post' && isset($_FILES['theme_zip'])) {
        verify_post_check($mybb->get_input('my_post_key'));

        $parentTid = $mybb->get_input('parent_tid', MyBB::INPUT_INT);
        if ($parentTid < 1) $parentTid = 1;

        $tid = $fmz->importFromZip($_FILES['theme_zip'], $parentTid);

        if ($tid) {
            flash_message('Theme imported successfully (TID: ' . $tid . ').', 'success');
            admin_redirect("index.php?module=fmzstudio-manage");
        } else {
            $errors = $fmz->getErrors();
            flash_message(implode('<br>', $errors), 'error');
            admin_redirect("index.php?module=fmzstudio-import_export");
        }
    }
    // GET request on old /import URL → redirect to combined page
    admin_redirect("index.php?module=fmzstudio-import_export");
}

/* ====================================================================
   Import / Export — Combined Page
   ==================================================================== */

if ($action === 'import_export') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Import / Export");

    $page->output_header("FMZ Studio - Import / Export");

    // ── Button style for export ──
    $zipBtnStyle = 'display:inline-flex;align-items:center;gap:6px;padding:5px 14px;'
                 . 'background:linear-gradient(135deg,#0d9488 0%,#0b7c72 100%);color:#fff;'
                 . 'border:1px solid #0a6e64;border-radius:5px;font-size:12px;font-weight:600;'
                 . 'cursor:pointer;white-space:nowrap;transition:all .15s ease;'
                 . 'box-shadow:0 1px 3px rgba(0,0,0,.12)';

    echo '<style>
.fmz-zip-btn:hover { filter:brightness(1.1); box-shadow:0 2px 6px rgba(13,148,136,.35) !important; transform:translateY(-1px); }
.fmz-zip-btn:active { filter:brightness(.95); transform:translateY(0); box-shadow:0 1px 2px rgba(0,0,0,.1) !important; }
</style>';

    // ────────────────────────────────────────────────
    //  EXPORT SECTION
    // ────────────────────────────────────────────────
    $themes = $fmz->listDbThemes();

    $exportForm = new Form("index.php?module=fmzstudio-export", "post");

    $table = new Table;
    $table->construct_header("Theme");
    $table->construct_header("Status", array('width' => 130, 'class' => 'align_center'));
    $table->construct_header("Action", array('width' => 160, 'class' => 'align_center'));

    if (empty($themes)) {
        $table->construct_cell("No themes found in the database.", array('colspan' => 3));
        $table->construct_row();
    } else {
        foreach ($themes as $t) {
            $table->construct_cell(htmlspecialchars_uni($t['name']));
            $status = $t['has_disk']
                ? '<span style="color:#0d9488;font-weight:600">On Disk</span>'
                : '<span style="color:#888">DB Only</span>';
            if ($t['is_default']) $status .= ' &nbsp;<strong style="color:#0d9488">(Default)</strong>';
            $table->construct_cell($status, array('class' => 'align_center'));

            $exportBtn = '<button type="submit" name="tid" value="' . $t['tid']
                       . '" class="fmz-zip-btn" style="' . $zipBtnStyle . '">'
                       . '<i class="bi bi-file-earmark-zip" style="font-size:13px"></i> Download ZIP</button>';
            $table->construct_cell($exportBtn, array('class' => 'align_center'));
            $table->construct_row();
        }
    }

    $table->output("Export Theme");
    echo $exportForm->end();

    // ────────────────────────────────────────────────
    //  IMPORT SECTION
    // ────────────────────────────────────────────────
    $importForm = new Form("index.php?module=fmzstudio-import", "post", "import_form", 1);

    $form_container = new FormContainer("Import Theme from ZIP");
    $form_container->output_row(
        "Theme ZIP File",
        "Upload a <code>.zip</code> theme package containing <code>theme.json</code> and <code>templates/</code>.",
        $importForm->generate_file_upload_box('theme_zip')
    );

    // Parent theme selector
    $parentOptions = array(1 => 'Master Style');
    $query = $db->simple_select('themes', 'tid, name', "tid != 1", array('order_by' => 'name'));
    while ($t = $db->fetch_array($query)) {
        $parentOptions[(int) $t['tid']] = htmlspecialchars_uni($t['name']);
    }
    $form_container->output_row(
        "Parent Theme",
        "Select the parent theme for this import.",
        $importForm->generate_select_box('parent_tid', $parentOptions, 1)
    );

    $form_container->end();

    $buttons = array($importForm->generate_submit_button("Import Theme"));
    $importForm->output_submit_wrapper($buttons);
    echo $importForm->end();

    echo fmz_help_box('Import / Export', '
<p><strong>Export:</strong> Download any theme as a <code>.zip</code> package. The ZIP includes the entire <code>themes/{slug}/</code> directory — templates, CSS, JS, images, functions, plugins, and the <code>theme.json</code> manifest. Exported ZIPs are <strong>import-ready</strong>.</p>
<p><strong>Import:</strong> Upload a <code>.zip</code> file containing a valid FMZ theme package. The ZIP must include a <code>theme.json</code> manifest (at root or one level deep) and a <code>templates/</code> directory with at least one <code>.html</code> file. After upload the theme is extracted, synced to the database, and JS files are deployed.</p>
');

    $page->output_footer();
    exit;
}

/* ====================================================================
   Activate / Deactivate Theme
   ==================================================================== */

if ($action === 'activate') {
    verify_post_check($mybb->get_input('my_post_key'));
    $tid = $mybb->get_input('tid', MyBB::INPUT_INT);
    if ($fmz->activateTheme($tid)) {
        flash_message('Theme activated as default.', 'success');
    } else {
        flash_message(implode('<br>', $fmz->getErrors()), 'error');
    }
    admin_redirect("index.php?module=fmzstudio-manage");
}

if ($action === 'deactivate') {
    verify_post_check($mybb->get_input('my_post_key'));
    $tid = $mybb->get_input('tid', MyBB::INPUT_INT);
    if ($fmz->deactivateTheme($tid)) {
        flash_message('Theme deactivated.', 'success');
    } else {
        flash_message(implode('<br>', $fmz->getErrors()), 'error');
    }
    admin_redirect("index.php?module=fmzstudio-manage");
}

/* ====================================================================
   Delete Theme (DB + disk)
   ==================================================================== */

if ($action === 'delete_theme') {
    verify_post_check($mybb->get_input('my_post_key'));
    $tid = $mybb->get_input('tid', MyBB::INPUT_INT);
    $deleteDisk = $mybb->get_input('disk', MyBB::INPUT_INT) ? true : false;

    if ($tid > 0) {
        // DB theme (may also delete disk)
        if ($fmz->deleteTheme($tid, $deleteDisk)) {
            flash_message('Theme deleted successfully.', 'success');
        } else {
            flash_message(implode('<br>', $fmz->getErrors()), 'error');
        }
    } else {
        // Disk-only theme (no DB entry) — delete folder directly
        $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
        if (!empty($slug) && $slug !== 'fmz-default') {
            $themeDir = MYBB_ROOT . 'themes/' . $slug;
            if (is_dir($themeDir)) {
                $fmz->rrmdir($themeDir);
                flash_message('Theme directory deleted: themes/' . htmlspecialchars_uni($slug) . '/', 'success');
            } else {
                flash_message('Theme directory not found.', 'error');
            }
        } else {
            flash_message('Invalid theme or cannot delete the default theme.', 'error');
        }
    }
    admin_redirect("index.php?module=fmzstudio-manage");
}

/* ====================================================================
   Sync Theme (disk -> database)
   ==================================================================== */

if ($action === 'sync_theme') {
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = $mybb->get_input('slug');
    $tid  = $fmz->syncToDatabase($slug);
    if ($tid) {
        flash_message('Theme synced to database (TID: ' . $tid . ').', 'success');
    } else {
        flash_message(implode('<br>', $fmz->getErrors()), 'error');
    }
    admin_redirect("index.php?module=fmzstudio-manage");
}

/* ====================================================================
   Convert DB Theme to Disk
   ==================================================================== */

if ($action === 'convert') {
    verify_post_check($mybb->get_input('my_post_key'));
    $tid = $mybb->get_input('tid', MyBB::INPUT_INT);

    $query = $db->simple_select('themes', 'name', "tid='{$tid}'");
    $theme = $db->fetch_array($query);
    if ($theme) {
        $slug = $fmz->slug($theme['name']);
        $result = $fmz->extractThemeToDisk($tid, $slug);
        if ($result) {
            flash_message('Theme extracted to themes/' . htmlspecialchars_uni($slug) . '/.', 'success');
        } else {
            flash_message(implode('<br>', $fmz->getErrors()), 'error');
        }
    } else {
        flash_message('Theme not found.', 'error');
    }
    admin_redirect("index.php?module=fmzstudio-manage");
}

/* ====================================================================
   Upload Theme Asset (logo, favicon, etc.)
   ==================================================================== */

if ($action === 'api_upload_asset') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));

    $slug  = $mybb->get_input('slug');
    $field = $mybb->get_input('field'); // e.g. 'site_logo', 'favicon'

    if (empty($slug) || empty($field)) {
        echo json_encode(array('error' => 'Missing parameters.'));
        exit;
    }

    // Sanitise slug
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $uploadDir = MYBB_ROOT . 'themes/' . $slug . '/images/uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(array('error' => 'No file uploaded or upload error.'));
        exit;
    }

    $file = $_FILES['file'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Allowed image extensions
    $allowed = array('png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico');
    if (!in_array($ext, $allowed)) {
        echo json_encode(array('error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed)));
        exit;
    }

    // Validate MIME type matches extension (prevent disguised uploads)
    $allowedMimes = array(
        'png'  => array('image/png'),
        'jpg'  => array('image/jpeg'),
        'jpeg' => array('image/jpeg'),
        'gif'  => array('image/gif'),
        'svg'  => array('image/svg+xml', 'text/xml', 'application/xml'),
        'webp' => array('image/webp'),
        'ico'  => array('image/x-icon', 'image/vnd.microsoft.icon'),
    );
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (isset($allowedMimes[$ext]) && !in_array($detectedMime, $allowedMimes[$ext])) {
        echo json_encode(array('error' => 'File MIME type does not match extension.'));
        exit;
    }

    // Max 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(array('error' => 'File too large. Max 5MB.'));
        exit;
    }

    // Generate safe filename
    $safeName = preg_replace('/[^a-z0-9\-_]/', '', $field) . '.' . $ext;
    $destPath = $uploadDir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(array('error' => 'Failed to save file.'));
        exit;
    }

    // Return relative URL path
    $relUrl = 'themes/' . $slug . '/images/uploads/' . $safeName;
    echo json_encode(array('success' => true, 'url' => $relUrl, 'field' => $field));
    exit;
}

/* ====================================================================
   Save Theme Options (POST handler — shared by Global FMZ Options & Header & Footer)
   ==================================================================== */

if ($action === 'api_saveoptions') {
    verify_post_check($mybb->get_input('my_post_key'));

    $slug = $mybb->get_input('slug');
    $pageFilter = $mybb->get_input('page_filter');
    $redirectTo = $mybb->get_input('redirect_to');
    $options = $fmz->getThemeOptions($slug);

    if ($options) {
        $existing = $fmz->getThemeOptionValues($slug);
        $values = array();

        foreach ($options as $key => $def) {
            // Only process options belonging to the submitted page
            $optPage = isset($def['page']) ? $def['page'] : '';
            if ($pageFilter && $optPage !== $pageFilter) {
                // Preserve existing value for options on other pages
                if (isset($existing[$key])) {
                    $values[$key] = $existing[$key];
                    // Preserve dimension fields too
                    if (!empty($def['has_dimensions'])) {
                        $values[$key . '_width']  = isset($existing[$key . '_width'])  ? $existing[$key . '_width']  : '';
                        $values[$key . '_height'] = isset($existing[$key . '_height']) ? $existing[$key . '_height'] : '';
                    }
                }
                continue;
            }
            $type = isset($def['type']) ? $def['type'] : 'text';

            if ($type === 'image') {
                // Handle file upload for image fields
                $fileKey = 'opt_' . $key . '_file';
                if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES[$fileKey];
                    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowed = array('png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico');

                    if (in_array($ext, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
                        $safeSlug = preg_replace('/[^a-z0-9\-]/', '', $slug);
                        $uploadDir = MYBB_ROOT . 'themes/' . $safeSlug . '/images/uploads';
                        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

                        $safeName = preg_replace('/[^a-z0-9\-_]/', '', $key) . '.' . $ext;
                        $destPath = $uploadDir . '/' . $safeName;

                        if (move_uploaded_file($file['tmp_name'], $destPath)) {
                            $values[$key] = 'themes/' . $safeSlug . '/images/uploads/' . $safeName;
                        } else {
                            $values[$key] = isset($existing[$key]) ? $existing[$key] : '';
                        }
                    } else {
                        $values[$key] = isset($existing[$key]) ? $existing[$key] : '';
                    }
                } else {
                    // Check if "remove" was requested
                    $removeKey = 'opt_' . $key . '_remove';
                    if ($mybb->get_input($removeKey)) {
                        $values[$key] = '';
                    } else {
                        $values[$key] = isset($existing[$key]) ? $existing[$key] : '';
                    }
                }

                // Save dimension fields if they exist in the option definition
                if (!empty($def['has_dimensions'])) {
                    $values[$key . '_width']  = $mybb->get_input('opt_' . $key . '_width');
                    $values[$key . '_height'] = $mybb->get_input('opt_' . $key . '_height');
                }
            } else {
                $rawVal = $mybb->get_input('opt_' . $key);
                // Validate JSON for nav_links type
                if ($type === 'nav_links') {
                    if (!empty($rawVal)) {
                        $decoded = @json_decode($rawVal, true);
                        if (!is_array($decoded)) {
                            $rawVal = ''; // Invalid JSON, reset
                        } else {
                            // Sanitize each entry
                            $clean = array();
                            foreach ($decoded as $entry) {
                                if (!is_array($entry)) continue;
                                $text = isset($entry['text']) ? trim($entry['text']) : '';
                                $url  = isset($entry['url'])  ? trim($entry['url'])  : '';
                                if ($text === '' && $url === '') continue;
                                $clean[] = array(
                                    'text' => $text,
                                    'url'  => $url,
                                    'icon' => isset($entry['icon']) ? preg_replace('/[^a-z0-9\-]/', '', $entry['icon']) : '',
                                );
                            }
                            $rawVal = !empty($clean) ? json_encode($clean) : '';
                        }
                    }
                }
                $values[$key] = $rawVal;
            }
        }

        $fmz->saveThemeOptionValues($slug, $values);
        flash_message('Theme options saved.', 'success');
    } else {
        flash_message('No options found for this theme.', 'error');
    }
    $redirect = $redirectTo ? $redirectTo : "index.php?module=fmzstudio-options";
    admin_redirect($redirect);
}

/* ====================================================================
   Global FMZ Options Page
   ==================================================================== */

if ($action === 'options') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Global FMZ Options");

    $page->output_header("FMZ Studio - Global FMZ Options");

    $activeSlug = $fmz->getActiveThemeSlug();

    if (!$activeSlug) {
        echo '<p>No active theme found.</p>';
        $page->output_footer();
        exit;
    }

    $allOptions = $fmz->getThemeOptions($activeSlug);
    if (!$allOptions) {
        echo '<div class="alert"><p>The active theme does not provide any configurable options.</p></div>';
        $page->output_footer();
        exit;
    }

    $values = $fmz->getMergedThemeOptions($activeSlug);

    // Filter options for global page
    $globalOpts = array();
    $groups = array();
    foreach ($allOptions as $key => $def) {
        $optPage = isset($def['page']) ? $def['page'] : '';
        if ($optPage !== 'global') continue;
        if (!empty($def['group'])) {
            $groups[$def['group']][$key] = $def;
        } else {
            $globalOpts[$key] = $def;
        }
    }

    $form = new Form("index.php?module=fmzstudio-manage&action=api_saveoptions", "post", "", 1);
    echo $form->generate_hidden_field('slug', $activeSlug);
    echo $form->generate_hidden_field('page_filter', 'global');
    echo $form->generate_hidden_field('redirect_to', 'index.php?module=fmzstudio-options');

    // ── Color Mode ──
    $form_container = new FormContainer("Global FMZ Options");
    foreach ($globalOpts as $key => $def) {
        // Render color_mode first, then layout/effects after palette
        if ($key !== 'color_mode') continue;
        fmz_render_option_row($form, $form_container, $key, $def, $values, $mybb);
    }
    $form_container->end();

    // ── Palette CSS ──
    echo '<style>
.fmz-pal-table{width:100%;border-collapse:collapse;font-size:13px}
.fmz-pal-table th{text-align:left;padding:6px 8px;background:#f5f5f5;border-bottom:2px solid #ddd;font-size:12px;text-transform:uppercase;color:#666}
.fmz-pal-table td{padding:5px 8px;border-bottom:1px solid #eee;vertical-align:middle}
.fmz-pal-table tr:hover{background:#fafafa}
.fmz-pal-cell{display:flex;align-items:center;gap:6px}
.fmz-pal-cell input[type=color]{width:26px;height:26px;border:1px solid rgba(0,0,0,.15);padding:0;cursor:pointer;border-radius:4px;flex-shrink:0}
.fmz-pal-cell code{font-size:11px;color:#666;min-width:62px}
.fmz-pal-cell .fmz-reset-btn{font-size:10px;background:none;border:1px solid #ccc;border-radius:3px;padding:1px 5px;cursor:pointer;color:#888}
</style>';

    // ── Quick Presets ──
    $presets = array(
        'teal' => array(
            'label' => 'Teal', 'swatch' => '#0d9488',
            'light' => array('accent'=>'#0d9488','accent_hover'=>'#0f766e','heading_bg'=>'#0d9488','border'=>'#ccfbf1','link'=>'#0d9488','link_hover'=>'#0f766e','btn_bg'=>'#0d9488','btn_hover'=>'#0f766e'),
            'dark'  => array('accent'=>'#2dd4bf','accent_hover'=>'#5eead4','heading_bg'=>'#0d9488','border'=>'#134e4a','link'=>'#2dd4bf','link_hover'=>'#5eead4','btn_bg'=>'#2dd4bf','btn_hover'=>'#5eead4'),
        ),
        'ocean' => array(
            'label' => 'Ocean', 'swatch' => '#0369a1',
            'light' => array('accent'=>'#0369a1','accent_hover'=>'#075985','heading_bg'=>'#0369a1','border'=>'#bae6fd','link'=>'#0369a1','link_hover'=>'#075985','btn_bg'=>'#0369a1','btn_hover'=>'#075985'),
            'dark'  => array('accent'=>'#38bdf8','accent_hover'=>'#7dd3fc','heading_bg'=>'#0369a1','border'=>'#0c4a6e','link'=>'#38bdf8','link_hover'=>'#7dd3fc','btn_bg'=>'#38bdf8','btn_hover'=>'#7dd3fc'),
        ),
        'indigo' => array(
            'label' => 'Indigo', 'swatch' => '#4338ca',
            'light' => array('accent'=>'#4338ca','accent_hover'=>'#3730a3','heading_bg'=>'#4338ca','border'=>'#c7d2fe','link'=>'#4338ca','link_hover'=>'#3730a3','btn_bg'=>'#4338ca','btn_hover'=>'#3730a3'),
            'dark'  => array('accent'=>'#818cf8','accent_hover'=>'#a5b4fc','heading_bg'=>'#4338ca','border'=>'#312e81','link'=>'#818cf8','link_hover'=>'#a5b4fc','btn_bg'=>'#818cf8','btn_hover'=>'#a5b4fc'),
        ),
        'purple' => array(
            'label' => 'Purple', 'swatch' => '#7e22ce',
            'light' => array('accent'=>'#7e22ce','accent_hover'=>'#6b21a8','heading_bg'=>'#7e22ce','border'=>'#e9d5ff','link'=>'#7e22ce','link_hover'=>'#6b21a8','btn_bg'=>'#7e22ce','btn_hover'=>'#6b21a8'),
            'dark'  => array('accent'=>'#c084fc','accent_hover'=>'#d8b4fe','heading_bg'=>'#7e22ce','border'=>'#581c87','link'=>'#c084fc','link_hover'=>'#d8b4fe','btn_bg'=>'#c084fc','btn_hover'=>'#d8b4fe'),
        ),
        'rose' => array(
            'label' => 'Rose', 'swatch' => '#be123c',
            'light' => array('accent'=>'#be123c','accent_hover'=>'#9f1239','heading_bg'=>'#be123c','border'=>'#fecdd3','link'=>'#be123c','link_hover'=>'#9f1239','btn_bg'=>'#be123c','btn_hover'=>'#9f1239'),
            'dark'  => array('accent'=>'#fb7185','accent_hover'=>'#fda4af','heading_bg'=>'#be123c','border'=>'#881337','link'=>'#fb7185','link_hover'=>'#fda4af','btn_bg'=>'#fb7185','btn_hover'=>'#fda4af'),
        ),
        'amber' => array(
            'label' => 'Amber', 'swatch' => '#b45309',
            'light' => array('accent'=>'#b45309','accent_hover'=>'#92400e','heading_bg'=>'#b45309','border'=>'#fde68a','link'=>'#b45309','link_hover'=>'#92400e','btn_bg'=>'#b45309','btn_hover'=>'#92400e'),
            'dark'  => array('accent'=>'#fbbf24','accent_hover'=>'#fcd34d','heading_bg'=>'#b45309','border'=>'#78350f','link'=>'#fbbf24','link_hover'=>'#fcd34d','btn_bg'=>'#fbbf24','btn_hover'=>'#fcd34d'),
        ),
        'emerald' => array(
            'label' => 'Emerald', 'swatch' => '#059669',
            'light' => array('accent'=>'#059669','accent_hover'=>'#047857','heading_bg'=>'#059669','border'=>'#a7f3d0','link'=>'#059669','link_hover'=>'#047857','btn_bg'=>'#059669','btn_hover'=>'#047857'),
            'dark'  => array('accent'=>'#34d399','accent_hover'=>'#6ee7b7','heading_bg'=>'#059669','border'=>'#064e3b','link'=>'#34d399','link_hover'=>'#6ee7b7','btn_bg'=>'#34d399','btn_hover'=>'#6ee7b7'),
        ),
        'crimson' => array(
            'label' => 'Crimson', 'swatch' => '#dc2626',
            'light' => array('accent'=>'#dc2626','accent_hover'=>'#b91c1c','heading_bg'=>'#dc2626','border'=>'#fecaca','link'=>'#dc2626','link_hover'=>'#b91c1c','btn_bg'=>'#dc2626','btn_hover'=>'#b91c1c'),
            'dark'  => array('accent'=>'#f87171','accent_hover'=>'#fca5a5','heading_bg'=>'#dc2626','border'=>'#7f1d1d','link'=>'#f87171','link_hover'=>'#fca5a5','btn_bg'=>'#f87171','btn_hover'=>'#fca5a5'),
        ),
        'sapphire' => array(
            'label' => 'Sapphire', 'swatch' => '#1d4ed8',
            'light' => array('accent'=>'#1d4ed8','accent_hover'=>'#1e40af','heading_bg'=>'#1d4ed8','border'=>'#bfdbfe','link'=>'#1d4ed8','link_hover'=>'#1e40af','btn_bg'=>'#1d4ed8','btn_hover'=>'#1e40af'),
            'dark'  => array('accent'=>'#60a5fa','accent_hover'=>'#93bbfd','heading_bg'=>'#1d4ed8','border'=>'#1e3a5f','link'=>'#60a5fa','link_hover'=>'#93bbfd','btn_bg'=>'#60a5fa','btn_hover'=>'#93bbfd'),
        ),
        'coral' => array(
            'label' => 'Coral', 'swatch' => '#c2410c',
            'light' => array('accent'=>'#c2410c','accent_hover'=>'#9a3412','heading_bg'=>'#c2410c','border'=>'#fed7aa','link'=>'#c2410c','link_hover'=>'#9a3412','btn_bg'=>'#c2410c','btn_hover'=>'#9a3412'),
            'dark'  => array('accent'=>'#fb923c','accent_hover'=>'#fdba74','heading_bg'=>'#c2410c','border'=>'#7c2d12','link'=>'#fb923c','link_hover'=>'#fdba74','btn_bg'=>'#fb923c','btn_hover'=>'#fdba74'),
        ),
        'slate' => array(
            'label' => 'Slate', 'swatch' => '#475569',
            'light' => array('accent'=>'#475569','accent_hover'=>'#334155','heading_bg'=>'#475569','border'=>'#cbd5e1','link'=>'#475569','link_hover'=>'#334155','btn_bg'=>'#475569','btn_hover'=>'#334155'),
            'dark'  => array('accent'=>'#94a3b8','accent_hover'=>'#cbd5e1','heading_bg'=>'#475569','border'=>'#334155','link'=>'#94a3b8','link_hover'=>'#cbd5e1','btn_bg'=>'#94a3b8','btn_hover'=>'#cbd5e1'),
        ),
        'pink' => array(
            'label' => 'Pink', 'swatch' => '#db2777',
            'light' => array('accent'=>'#db2777','accent_hover'=>'#be185d','heading_bg'=>'#db2777','border'=>'#fbcfe8','link'=>'#db2777','link_hover'=>'#be185d','btn_bg'=>'#db2777','btn_hover'=>'#be185d'),
            'dark'  => array('accent'=>'#f472b6','accent_hover'=>'#f9a8d4','heading_bg'=>'#db2777','border'=>'#831843','link'=>'#f472b6','link_hover'=>'#f9a8d4','btn_bg'=>'#f472b6','btn_hover'=>'#f9a8d4'),
        ),
    );
    $presetsJson = json_encode($presets);

    echo '<div style="margin:12px 0 16px;padding:14px 18px;background:#fff;border:1px solid #e5e7eb;border-radius:8px">';
    echo '<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">';
    echo '<strong style="font-size:13px;color:#374151"><i class="bi bi-palette me-1"></i> Quick Presets</strong>';
    echo '<span style="font-size:11px;color:#9ca3af">Click to apply a color scheme — all palette fields update instantly</span>';
    echo '</div>';
    echo '<div id="fmz-presets" style="display:flex;flex-wrap:wrap;gap:8px">';
    foreach ($presets as $id => $preset) {
        echo '<button type="button" class="fmz-preset-btn" data-preset="' . htmlspecialchars_uni($id) . '" '
           . 'style="display:flex;flex-direction:column;align-items:center;gap:3px;padding:6px 10px;border:2px solid transparent;border-radius:8px;background:#f9fafb;cursor:pointer;transition:all .15s" '
           . 'title="' . htmlspecialchars_uni($preset['label']) . '">'
           . '<span style="width:28px;height:28px;border-radius:50%;background:' . htmlspecialchars_uni($preset['swatch']) . ';border:2px solid rgba(0,0,0,.1);display:block"></span>'
           . '<span style="font-size:10px;color:#6b7280;font-weight:500">' . htmlspecialchars_uni($preset['label']) . '</span>'
           . '</button>';
    }
    echo '</div>';
    echo '</div>';

    // ── Render palette groups (no preview strip) ──
    $groupLabels = array(
        'palette_light' => 'Color Palette — Light Mode',
        'palette_dark'  => 'Color Palette — Dark Mode',
    );

    $activeMode = isset($values['color_mode']) ? $values['color_mode'] : 'light';

    foreach (array('palette_light', 'palette_dark') as $groupId) {
        if (empty($groups[$groupId])) continue;

        $mode = ($groupId === 'palette_dark') ? 'dark' : 'light';
        $hideStyle = ($mode !== $activeMode) ? ' style="display:none"' : '';
        echo '<div class="fmz-palette-group" data-palette-mode="' . $mode . '"' . $hideStyle . '>';

        $label = isset($groupLabels[$groupId]) ? $groupLabels[$groupId] : $groupId;
        $modeIcon = ($groupId === 'palette_dark') ? '&#x1F319;' : '&#x2600;&#xFE0F;';
        $form_container = new FormContainer($modeIcon . ' ' . $label);

        // Compact table — no preview strip
        $tableHtml = '<table class="fmz-pal-table"><thead><tr><th>Color</th><th>Value</th><th>CSS Variable</th></tr></thead><tbody>';
        foreach ($groups[$groupId] as $key => $def) {
            $val = isset($values[$key]) ? $values[$key] : (isset($def['default']) ? $def['default'] : '');
            $defaultVal = isset($def['default']) ? $def['default'] : '';
            $cssVar = isset($def['css_var']) ? $def['css_var'] : '';

            $resetBtn = '';
            if ($defaultVal && strtolower($val) !== strtolower($defaultVal)) {
                $resetBtn = ' <button type="button" class="fmz-reset-btn" data-default="' . htmlspecialchars_uni($defaultVal) . '" title="Reset to ' . htmlspecialchars_uni($defaultVal) . '">&#x21A9;</button>';
            }

            $tableHtml .= '<tr>'
                        . '<td><strong>' . htmlspecialchars_uni($def['title']) . '</strong></td>'
                        . '<td><div class="fmz-pal-cell">'
                        . '<input type="color" name="opt_' . htmlspecialchars_uni($key) . '" value="' . htmlspecialchars_uni($val) . '" class="fmz-palette-input" data-group="' . $groupId . '" data-default="' . htmlspecialchars_uni($defaultVal) . '" />'
                        . '<code class="fmz-hex-label">' . htmlspecialchars_uni($val) . '</code>'
                        . $resetBtn
                        . '</div></td>'
                        . '<td><code style="font-size:11px;color:#999">' . htmlspecialchars_uni($cssVar) . '</code></td>'
                        . '</tr>';
        }
        $tableHtml .= '</tbody></table>';
        $form_container->output_row('', '', $tableHtml);

        $form_container->end();
        echo '</div>';
    }

    // ── Layout & Effects ──
    $layoutOpts = array('show_sidebar', 'content_width', 'loading_bar');
    $hasLayout = false;
    foreach ($layoutOpts as $lk) {
        if (isset($globalOpts[$lk])) { $hasLayout = true; break; }
    }
    if ($hasLayout) {
        $form_container = new FormContainer("Layout & Effects");
        foreach ($layoutOpts as $lk) {
            if (isset($globalOpts[$lk])) {
                fmz_render_option_row($form, $form_container, $lk, $globalOpts[$lk], $values, $mybb);
            }
        }
        $form_container->end();
    }

    $buttons = array($form->generate_submit_button("Save Options"));
    $form->output_submit_wrapper($buttons);
    echo $form->end();

    echo '<script>
(function(){
  function initPaletteUI(){
    document.querySelectorAll(".fmz-palette-input").forEach(function(el){
      el.addEventListener("input",function(){
        var label=this.parentNode.querySelector(".fmz-hex-label");
        if(label) label.textContent=this.value;
        var defVal=this.getAttribute("data-default");
        var resetBtn=this.parentNode.querySelector(".fmz-reset-btn");
        if(resetBtn&&defVal){
          resetBtn.style.display=(this.value.toLowerCase()===defVal.toLowerCase())?"none":"inline";
        }
      });
    });
    document.querySelectorAll(".fmz-reset-btn").forEach(function(btn){
      btn.addEventListener("click",function(){
        var def=this.getAttribute("data-default");
        var cell=this.closest(".fmz-pal-cell");
        var input=cell?cell.querySelector("input[type=color]"):null;
        if(input&&def){input.value=def;input.dispatchEvent(new Event("input"));}
        this.style.display="none";
      });
    });
    document.querySelectorAll("[name=\'opt_color_mode\']").forEach(function(radio){
      radio.addEventListener("change",function(){
        var mode=this.value;
        document.querySelectorAll(".fmz-palette-group").forEach(function(g){
          g.style.display=(g.getAttribute("data-palette-mode")===mode)?"":"none";
        });
      });
    });
    var presets=' . $presetsJson . ';
    document.querySelectorAll(".fmz-preset-btn").forEach(function(btn){
      btn.addEventListener("click",function(){
        var id=this.getAttribute("data-preset");
        var preset=presets[id];
        if(!preset) return;
        ["light","dark"].forEach(function(mode){
          var colors=preset[mode];
          if(!colors) return;
          for(var key in colors){
            var input=document.querySelector("[name=\'opt_"+mode+"_"+key+"\']");
            if(input){
              input.value=colors[key];
              input.dispatchEvent(new Event("input"));
            }
          }
        });
        document.querySelectorAll(".fmz-preset-btn").forEach(function(b){
          b.style.borderColor="transparent";b.style.background="#f9fafb";
        });
        this.style.borderColor=preset.swatch;this.style.background="#f0fdf4";
      });
    });
  }
  document.addEventListener("DOMContentLoaded",function(){initPaletteUI();});
})();
</script>';

    echo fmz_help_box('Global FMZ Options', '
<p>Configure the color scheme, color palette, and layout options for the active theme.</p>
<p><strong>Quick Presets:</strong> Click a preset to instantly apply a matching color scheme to both Light and Dark palettes.</p>
<p><strong>Color Palette:</strong> Each mode (Light/Dark) has its own full palette. Only the active mode\'s palette is shown based on the Color Mode setting.</p>
<p>Changes take effect immediately on the frontend after saving.</p>
');

    $page->output_footer();
    exit;
}

/* ====================================================================
   Header & Footer Options Page
   ==================================================================== */

if ($action === 'options_header_footer') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Header & Footer");

    $page->output_header("FMZ Studio - Header & Footer");

    $activeSlug = $fmz->getActiveThemeSlug();

    if (!$activeSlug) {
        echo '<p>No active theme found.</p>';
        $page->output_footer();
        exit;
    }

    $allOptions = $fmz->getThemeOptions($activeSlug);
    if (!$allOptions) {
        echo '<div class="alert"><p>The active theme does not provide any configurable options.</p></div>';
        $page->output_footer();
        exit;
    }

    $values = $fmz->getMergedThemeOptions($activeSlug);

    // Filter options for header_footer page
    $hfOptions = array();
    foreach ($allOptions as $key => $def) {
        $optPage = isset($def['page']) ? $def['page'] : '';
        if ($optPage === 'header_footer') {
            $hfOptions[$key] = $def;
        }
    }

    if (empty($hfOptions)) {
        echo '<div class="alert"><p>No header/footer options available for this theme.</p></div>';
        $page->output_footer();
        exit;
    }

    echo '<style>
#fmz-nav-links-table{table-layout:fixed}
#fmz-nav-links-table th{font-size:11px;padding:5px 8px}
#fmz-nav-links-table td{padding:4px 6px}
#fmz-nav-links-table input[type=text]{width:100%;box-sizing:border-box;padding:5px 8px;font-size:12px;border:1px solid #ddd;border-radius:4px}
#fmz-nav-links-table .fmz-icon-pick-btn{width:100%;justify-content:center}
.fmz-pal-table{width:100%;border-collapse:collapse;font-size:13px}
.fmz-pal-table th{text-align:left;padding:6px 8px;background:#f5f5f5;border-bottom:2px solid #ddd;font-size:12px;text-transform:uppercase;color:#666}
.fmz-pal-table td{padding:5px 8px;border-bottom:1px solid #eee;vertical-align:middle}
</style>';

    $form = new Form("index.php?module=fmzstudio-manage&action=api_saveoptions", "post", "", 1);
    echo $form->generate_hidden_field('slug', $activeSlug);
    echo $form->generate_hidden_field('page_filter', 'header_footer');
    echo $form->generate_hidden_field('redirect_to', 'index.php?module=fmzstudio-options_header_footer');

    // ── Header Options ──
    $headerKeys = array('header_style', 'logo_icon', 'logo_text', 'site_logo', 'favicon');
    $form_container = new FormContainer("Header");
    foreach ($headerKeys as $hk) {
        if (isset($hfOptions[$hk])) {
            fmz_render_option_row($form, $form_container, $hk, $hfOptions[$hk], $values, $mybb);
        }
    }
    $form_container->end();

    // ── Navigation ──
    if (isset($hfOptions['custom_nav_links'])) {
        $form_container = new FormContainer("Navigation");
        $navDef = $hfOptions['custom_nav_links'];
        $navVal = isset($values['custom_nav_links']) ? $values['custom_nav_links'] : '';
        $navLinks = !empty($navVal) ? @json_decode($navVal, true) : array();
        if (!is_array($navLinks)) $navLinks = array();

        $navHtml = '<div id="fmz-nav-links-wrap">';
        $navHtml .= '<table class="fmz-pal-table" id="fmz-nav-links-table">'
                  . '<thead><tr><th style="width:25%">Link Text</th><th style="width:35%">URL</th><th style="width:25%">Icon</th><th style="width:15%"></th></tr></thead>'
                  . '<tbody>';
        foreach ($navLinks as $i => $link) {
            $navHtml .= fmz_nav_link_row($i, $link);
        }
        $navHtml .= '</tbody></table>';
        $navHtml .= '<button type="button" id="fmz-nav-add-btn" style="margin-top:8px;padding:4px 12px;font-size:12px;cursor:pointer;border:1px solid #0d9488;background:#f0fdfa;color:#0d9488;border-radius:4px">'
                  . '<i class="bi bi-plus-circle"></i> Add Link</button>';
        $navHtml .= '<input type="hidden" name="opt_custom_nav_links" id="fmz-nav-links-json" value="' . htmlspecialchars_uni($navVal) . '" />';
        $navHtml .= '</div>';

        $form_container->output_row(
            isset($navDef['title']) ? $navDef['title'] : 'custom_nav_links',
            isset($navDef['description']) ? $navDef['description'] : '',
            $navHtml
        );
        $form_container->end();
    }

    // ── Footer Options ──
    $footerKeys = array('footer_text', 'footer_about_text');
    $hasFooter = false;
    foreach ($footerKeys as $fk) {
        if (isset($hfOptions[$fk])) { $hasFooter = true; break; }
    }
    if ($hasFooter) {
        $form_container = new FormContainer("Footer");
        foreach ($footerKeys as $fk) {
            if (isset($hfOptions[$fk])) {
                fmz_render_option_row($form, $form_container, $fk, $hfOptions[$fk], $values, $mybb);
            }
        }
        $form_container->end();
    }

    $buttons = array($form->generate_submit_button("Save Options"));
    $form->output_submit_wrapper($buttons);
    echo $form->end();

    echo '<script>
(function(){
  // ── Nav Links Repeater ──
  function initNavLinks(){
    var wrap=document.getElementById("fmz-nav-links-wrap");
    if(!wrap) return;
    var tbody=document.querySelector("#fmz-nav-links-table tbody");
    var jsonInput=document.getElementById("fmz-nav-links-json");
    var addBtn=document.getElementById("fmz-nav-add-btn");

    function syncJson(){
      var rows=tbody.querySelectorAll(".fmz-nav-row");
      var links=[];
      rows.forEach(function(r){
        var t=r.querySelector(".fmz-nav-text").value.trim();
        var u=r.querySelector(".fmz-nav-url").value.trim();
        var ic=r.querySelector(".fmz-nav-icon").value;
        if(t||u) links.push({text:t,url:u,icon:ic});
      });
      jsonInput.value=links.length?JSON.stringify(links):"";
    }

    function bindRow(tr){
      tr.querySelector(".fmz-nav-text").addEventListener("input",syncJson);
      tr.querySelector(".fmz-nav-url").addEventListener("input",syncJson);
      var pickBtn=tr.querySelector(".fmz-icon-pick-btn");
      if(pickBtn) pickBtn.addEventListener("click",function(){
        var hiddenInput=tr.querySelector(".fmz-nav-icon");
        var preview=tr.querySelector(".fmz-nav-icon-preview");
        var label=tr.querySelector(".fmz-icon-label");
        FmzIconModal.open(hiddenInput.value,function(icon){
          hiddenInput.value=icon;
          preview.className="bi "+(icon||"bi-grid-3x3-gap")+" fmz-nav-icon-preview";
          if(label) label.textContent=icon||"Choose icon";
          syncJson();
        });
      });
      tr.querySelector(".fmz-nav-del").addEventListener("click",function(){
        tr.remove(); syncJson();
      });
    }

    tbody.querySelectorAll(".fmz-nav-row").forEach(bindRow);

    addBtn.addEventListener("click",function(){
      var tr=document.createElement("tr");
      tr.className="fmz-nav-row";
      tr.innerHTML=\'<td><input type="text" class="fmz-nav-text" value="" placeholder="Link text" style="width:100%;padding:4px 6px;font-size:12px;border:1px solid #ddd;border-radius:3px" /></td>\'
        +\'<td><input type="text" class="fmz-nav-url" value="" placeholder="https://..." style="width:100%;padding:4px 6px;font-size:12px;border:1px solid #ddd;border-radius:3px" /></td>\'
        +\'<td><input type="hidden" class="fmz-nav-icon" value="" />\'
        +\'<button type="button" class="fmz-icon-pick-btn" data-target-type="nav" style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;font-size:12px;border:1px solid #ddd;border-radius:4px;background:#fafafa;cursor:pointer">\'
        +\'<i class="bi bi-grid-3x3-gap fmz-nav-icon-preview"></i> <span class="fmz-icon-label">Choose icon</span></button></td>\'
        +\'<td style="text-align:center"><button type="button" class="fmz-nav-del" style="background:none;border:none;color:#e74c3c;cursor:pointer;font-size:14px" title="Remove"><i class="bi bi-trash"></i></button></td>\';
      tbody.appendChild(tr);
      bindRow(tr);
      tr.querySelector(".fmz-nav-text").focus();
    });
  }

  document.addEventListener("DOMContentLoaded",function(){initNavLinks();initIconPickers();});
})();
</script>';

    // ── Icon Picker Modal ──
    $iconListJson = json_encode(fmz_get_icon_list());
    echo '<div id="fmz-icon-modal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.5);backdrop-filter:blur(2px)">
<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.3);width:640px;max-width:95vw;max-height:85vh;display:flex;flex-direction:column;overflow:hidden">
  <div style="display:flex;align-items:center;justify-content:between;padding:14px 20px;border-bottom:1px solid #eee;gap:10px;flex-shrink:0">
    <i class="bi bi-grid-3x3-gap" style="font-size:18px;color:#0d9488"></i>
    <strong style="font-size:14px;flex:1">Choose Icon</strong>
    <input type="text" id="fmz-icon-search" placeholder="Search icons..." style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;width:220px" />
    <button type="button" id="fmz-icon-modal-close" style="background:none;border:none;font-size:20px;cursor:pointer;color:#888;padding:0 4px">&times;</button>
  </div>
  <div id="fmz-icon-grid" style="padding:12px 16px;overflow-y:auto;flex:1"></div>
  <div style="padding:10px 20px;border-top:1px solid #eee;display:flex;justify-content:space-between;align-items:center;flex-shrink:0">
    <span id="fmz-icon-count" style="font-size:12px;color:#888"></span>
    <button type="button" id="fmz-icon-clear-selected" style="font-size:12px;padding:4px 12px;border:1px solid #ddd;border-radius:4px;background:#fafafa;cursor:pointer;color:#888">&#x2715; No icon</button>
  </div>
</div>
</div>';

    echo '<style>
.fmz-ig{display:grid;grid-template-columns:repeat(auto-fill,minmax(64px,1fr));gap:6px}
.fmz-ig-item{display:flex;flex-direction:column;align-items:center;gap:3px;padding:8px 4px;border:2px solid transparent;border-radius:8px;cursor:pointer;transition:all .15s;background:#fafafa}
.fmz-ig-item:hover{background:#e0f2fe;border-color:#7dd3fc}
.fmz-ig-item.selected{background:#ccfbf1;border-color:#0d9488}
.fmz-ig-item i{font-size:22px;color:#333}
.fmz-ig-item span{font-size:9px;color:#888;text-align:center;line-height:1.1;word-break:break-word;max-width:60px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
</style>';

    echo '<script>
(function(){
  var icons=' . $iconListJson . ';
  var modal=document.getElementById("fmz-icon-modal");
  var grid=document.getElementById("fmz-icon-grid");
  var searchInput=document.getElementById("fmz-icon-search");
  var countEl=document.getElementById("fmz-icon-count");
  var closeBtn=document.getElementById("fmz-icon-modal-close");
  var clearBtn=document.getElementById("fmz-icon-clear-selected");
  var onSelect=null;
  var currentValue="";

  function renderGrid(filter){
    filter=(filter||"").toLowerCase();
    var html="";
    var count=0;
    for(var cls in icons){
      var label=icons[cls];
      if(filter && cls.indexOf(filter)===-1 && label.toLowerCase().indexOf(filter)===-1) continue;
      var sel=(cls===currentValue)?" selected":"";
      html+=\'<div class="fmz-ig-item\'+sel+\'" data-icon="\'+cls+\'"><i class="bi \'+cls+\'"></i><span>\'+label+\'</span></div>\';
      count++;
    }
    if(!count) html=\'<div style="text-align:center;padding:30px;color:#aaa;font-size:13px">No icons match your search</div>\';
    grid.innerHTML=\'<div class="fmz-ig">\'+html+\'</div>\';
    countEl.textContent=count+" icon"+(count!==1?"s":"");
    grid.querySelectorAll(".fmz-ig-item").forEach(function(item){
      item.addEventListener("click",function(){
        var icon=this.getAttribute("data-icon");
        if(onSelect) onSelect(icon);
        close();
      });
    });
  }

  function open(val,callback){
    currentValue=val||"";
    onSelect=callback;
    renderGrid("");
    searchInput.value="";
    modal.style.display="block";
    setTimeout(function(){searchInput.focus();},100);
  }

  function close(){
    modal.style.display="none";
    onSelect=null;
  }

  closeBtn.addEventListener("click",close);
  modal.addEventListener("click",function(e){if(e.target===modal) close();});
  document.addEventListener("keydown",function(e){if(e.key==="Escape"&&modal.style.display==="block") close();});
  searchInput.addEventListener("input",function(){renderGrid(this.value);});
  clearBtn.addEventListener("click",function(){if(onSelect) onSelect("");close();});

  window.FmzIconModal={open:open,close:close};

  window.initIconPickers=function(){
    document.querySelectorAll(".fmz-icon-pick-btn:not([data-target-type=nav])").forEach(function(btn){
      if(btn.dataset.bound) return;
      btn.dataset.bound="1";
      btn.addEventListener("click",function(){
        var inputId=btn.getAttribute("data-target-input");
        var previewId=btn.getAttribute("data-target-preview");
        var labelId=btn.getAttribute("data-target-label");
        var input=inputId?document.getElementById(inputId):null;
        FmzIconModal.open(input?input.value:"",function(icon){
          if(input) input.value=icon;
          var prev=previewId?document.getElementById(previewId):null;
          if(prev) prev.className="bi "+(icon||"bi-grid-3x3-gap");
          var lbl=labelId?document.getElementById(labelId):null;
          if(lbl) lbl.textContent=icon||"Choose icon\u2026";
        });
      });
    });
    document.querySelectorAll(".fmz-icon-clear-btn").forEach(function(btn){
      if(btn.dataset.bound) return;
      btn.dataset.bound="1";
      btn.addEventListener("click",function(){
        var inputId=btn.getAttribute("data-target-input");
        var previewId=btn.getAttribute("data-target-preview");
        var labelId=btn.getAttribute("data-target-label");
        var input=inputId?document.getElementById(inputId):null;
        if(input) input.value="";
        var prev=previewId?document.getElementById(previewId):null;
        if(prev) prev.className="bi bi-grid-3x3-gap";
        var lbl=labelId?document.getElementById(labelId):null;
        if(lbl) lbl.textContent="Choose icon\u2026";
        btn.style.display="none";
      });
    });
  };
})();
</script>';

    echo fmz_help_box('Header & Footer', '
<p>Configure the header layout, logo, favicon, navigation links, and footer text for the active theme.</p>
<p><strong>Navigation Links:</strong> Add custom links to the main navbar. Each link can have an optional Bootstrap icon.</p>
<p>Changes take effect immediately on the frontend after saving.</p>
');

    $page->output_footer();
    exit;
}

/* ====================================================================
   Save Mini Plugin Options (POST handler)
   ==================================================================== */

if ($action === 'api_save_plugin_options') {
    verify_post_check($mybb->get_input('my_post_key'));

    $activeSlug = $fmz->getActiveThemeSlug();
    $pluginId   = preg_replace('/[^a-z0-9\-_]/', '', $mybb->get_input('plugin_id'));

    if ($activeSlug && $pluginId) {
        $options = $fmz->getMiniPluginOptions($activeSlug, $pluginId);
        if ($options) {
            $values = array();
            foreach ($options as $def) {
                $id = isset($def['id']) ? $def['id'] : '';
                if (empty($id)) continue;
                $values[$id] = $mybb->get_input('opt_' . $id);
            }
            $fmz->saveMiniPluginOptionValues($activeSlug, $pluginId, $values);
            flash_message('Plugin options saved.', 'success');
        }
    }
    admin_redirect("index.php?module=fmzstudio-plugins&plugin=" . urlencode($pluginId));
}

/* ====================================================================
   Toggle Mini Plugin (POST handler)
   ==================================================================== */

if ($action === 'api_toggle_plugin') {
    verify_post_check($mybb->get_input('my_post_key'));

    $activeSlug = $fmz->getActiveThemeSlug();
    $pluginId   = preg_replace('/[^a-z0-9\-_]/', '', $mybb->get_input('plugin_id'));
    $enable     = $mybb->get_input('enable', MyBB::INPUT_INT);

    if ($activeSlug && $pluginId) {
        $states = $fmz->getMiniPluginStates($activeSlug);
        $states[$pluginId] = (bool) $enable;
        $fmz->saveMiniPluginStates($activeSlug, $states);
        flash_message($enable ? 'Plugin enabled.' : 'Plugin disabled.', 'success');
    }
    admin_redirect("index.php?module=fmzstudio-plugins");
}

/* ====================================================================
   Plugin Settings Page (individual plugin options via side nav)
   ==================================================================== */

if ($action === 'plugin_settings') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");

    $activeSlug = $fmz->getActiveThemeSlug();
    $selectedPlugin = preg_replace('/[^a-z0-9\-_]/', '', $mybb->get_input('plugin'));

    if (!$activeSlug || empty($selectedPlugin)) {
        flash_message('Invalid plugin.', 'error');
        admin_redirect("index.php?module=fmzstudio-plugins");
    }

    $allPlugins = $fmz->listMiniPlugins($activeSlug);
    $pluginInfo = null;
    foreach ($allPlugins as $p) {
        if ($p['id'] === $selectedPlugin) {
            $pluginInfo = $p;
            break;
        }
    }

    if (!$pluginInfo) {
        flash_message('Plugin not found.', 'error');
        admin_redirect("index.php?module=fmzstudio-plugins");
    }

    $page->add_breadcrumb_item(htmlspecialchars_uni($pluginInfo['name']));
    $page->output_header("FMZ Studio - " . htmlspecialchars_uni($pluginInfo['name']));

    echo $fmzBtnHoverCss;

    // Plugin info header
    echo '<div style="margin-bottom:15px;padding:12px 16px;background:#f5f5f5;border:1px solid #ddd;border-radius:4px">';
    echo '<h3 style="margin:0 0 4px">' . htmlspecialchars_uni($pluginInfo['name'])
       . ' <span style="font-weight:normal;color:#888;font-size:12px">v' . htmlspecialchars_uni($pluginInfo['version']) . '</span></h3>';
    if ($pluginInfo['description']) {
        echo '<p style="margin:0;color:#666;font-size:13px">' . htmlspecialchars_uni($pluginInfo['description']) . '</p>';
    }
    echo '</div>';

    // Plugin options
    $options = $fmz->getMiniPluginOptions($activeSlug, $selectedPlugin);
    if ($options) {
        $values = $fmz->getMergedMiniPluginOptions($activeSlug, $selectedPlugin);

        $form = new Form("index.php?module=fmzstudio-manage&action=api_save_plugin_options", "post");
        echo $form->generate_hidden_field('plugin_id', $selectedPlugin);

        $form_container = new FormContainer("Settings");

        foreach ($options as $def) {
            $id    = isset($def['id']) ? $def['id'] : '';
            if (empty($id)) continue;
            $title = isset($def['label']) ? $def['label'] : $id;
            $desc  = isset($def['description']) ? $def['description'] : '';
            $type  = isset($def['type']) ? $def['type'] : 'text';
            $val   = isset($values[$id]) ? $values[$id] : (isset($def['default']) ? $def['default'] : '');

            switch ($type) {
                case 'yesno':
                    $input = $form->generate_yes_no_radio('opt_' . $id, $val);
                    break;
                case 'select':
                    $opts = isset($def['options']) ? $def['options'] : array();
                    $input = $form->generate_select_box('opt_' . $id, $opts, $val);
                    break;
                case 'radio':
                    $opts = isset($def['options']) ? $def['options'] : array();
                    $radios = '';
                    foreach ($opts as $optVal => $optLabel) {
                        $checked = ($val === (string)$optVal) ? ' checked' : '';
                        $rid = 'opt_' . htmlspecialchars_uni($id) . '_' . htmlspecialchars_uni($optVal);
                        $radios .= '<label style="display:inline-flex;align-items:center;gap:5px;margin-right:16px;cursor:pointer;font-size:13px">' 
                                 . '<input type="radio" name="opt_' . htmlspecialchars_uni($id) . '" value="' . htmlspecialchars_uni($optVal) . '" id="' . $rid . '"' . $checked . ' />' 
                                 . $optLabel . '</label>';
                    }
                    $input = '<div style="display:flex;align-items:center;gap:4px">' . $radios . '</div>';
                    break;
                case 'color':
                    $input = '<input type="color" name="opt_' . htmlspecialchars_uni($id) . '" value="' . htmlspecialchars_uni($val) . '" />';
                    break;
                case 'numeric':
                    $input = $form->generate_numeric_field('opt_' . $id, $val, array('style' => 'width:150px'));
                    break;
                case 'textarea':
                    $input = $form->generate_text_area('opt_' . $id, $val, array('rows' => 4, 'style' => 'width:95%'));
                    break;
                case 'toolbar_builder':
                    $input = fmz_render_toolbar_builder($id, $val);
                    break;
                case 'preset_swatches':
                    $swatchOpts = isset($def['options']) ? $def['options'] : array();
                    $hiddenId = 'opt_' . htmlspecialchars_uni($id);
                    $input = '<input type="hidden" name="' . $hiddenId . '" id="' . $hiddenId . '" value="' . htmlspecialchars_uni($val) . '" />';
                    $input .= '<div style="display:flex;flex-wrap:wrap;gap:8px">';
                    foreach ($swatchOpts as $swKey => $swDef) {
                        $swLabel = isset($swDef['label']) ? $swDef['label'] : $swKey;
                        $swColor = isset($swDef['swatch']) ? $swDef['swatch'] : '#888';
                        $isActive = ($val === (string)$swKey);
                        $borderColor = $isActive ? htmlspecialchars_uni($swColor) : 'transparent';
                        $bgColor = $isActive ? '#eef2ff' : '#f9fafb';
                        $input .= '<button type="button" class="fmz-swatch-btn" data-value="' . htmlspecialchars_uni($swKey) . '" data-target="' . $hiddenId . '" '
                                . 'style="display:flex;flex-direction:column;align-items:center;gap:3px;padding:6px 10px;border:2px solid ' . $borderColor . ';border-radius:8px;background:' . $bgColor . ';cursor:pointer;transition:all .15s" '
                                . 'title="' . htmlspecialchars_uni($swLabel) . '">'
                                . '<span style="width:28px;height:28px;border-radius:50%;background:' . htmlspecialchars_uni($swColor) . ';border:2px solid rgba(0,0,0,.1);display:block"></span>'
                                . '<span style="font-size:10px;color:#6b7280;font-weight:500">' . htmlspecialchars_uni($swLabel) . '</span>'
                                . '</button>';
                    }
                    $input .= '</div>';
                    $input .= '<script>'
                            . 'document.addEventListener("click",function(e){'
                            . 'var btn=e.target.closest(".fmz-swatch-btn");'
                            . 'if(!btn)return;'
                            . 'e.preventDefault();'
                            . 'var target=btn.getAttribute("data-target");'
                            . 'var val=btn.getAttribute("data-value");'
                            . 'document.getElementById(target).value=val;'
                            . 'var wrap=btn.parentNode;'
                            . 'wrap.querySelectorAll(".fmz-swatch-btn").forEach(function(b){'
                            . 'b.style.borderColor="transparent";b.style.background="#f9fafb";'
                            . '});'
                            . 'btn.style.borderColor=btn.querySelector("span").style.background;'
                            . 'btn.style.background="#eef2ff";'
                            . '});'
                            . '</script>';
                    break;
                default:
                    $input = $form->generate_text_box('opt_' . $id, $val, array('style' => 'width:95%'));
                    break;
            }

            $form_container->output_row($title, $desc, $input);
        }

        $form_container->end();
        $buttons = array($form->generate_submit_button("Save Settings"));
        $form->output_submit_wrapper($buttons);
        echo $form->end();
    } else {
        echo '<p style="color:#888">This plugin has no configurable options.</p>';
    }

    // Include admin.php if it exists (custom admin content)
    if ($pluginInfo['has_admin']) {
        echo '<div style="margin-top:20px">';
        include $pluginInfo['dir'] . '/admin.php';
        echo '</div>';
    }

    $page->output_footer();
    exit;
}

/* ====================================================================
   Plugins Page
   ==================================================================== */

if ($action === 'plugins') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Manage Plugins");

    $activeSlug = $fmz->getActiveThemeSlug();

    // Main plugins list
    $page->output_header("FMZ Studio - Manage Plugins");

    echo $fmzBtnHoverCss;

    if (!$activeSlug) {
        echo '<p>No active theme found. Activate a theme on the Manage page first.</p>';
        $page->output_footer();
        exit;
    }

    $allPlugins = $fmz->listMiniPlugins($activeSlug);
    $states     = $fmz->getMiniPluginStates($activeSlug);
    $post_key   = $mybb->post_code;

    if (empty($allPlugins)) {
        echo '<div style="padding:20px;background:#f5f5f5;border:1px solid #ddd;border-radius:4px;text-align:center">';
        echo '<p style="color:#888;margin:0 0 8px">No mini plugins found for the active theme (<strong>' . htmlspecialchars_uni($activeSlug) . '</strong>).</p>';
        echo '<p style="color:#aaa;font-size:12px;margin:0">Theme developers can add mini plugins by creating directories under '
           . '<code>themes/' . htmlspecialchars_uni($activeSlug) . '/functions/plugins/{plugin-name}/</code> with a <code>plugin.json</code> manifest.</p>';
        echo '</div>';
        $page->output_footer();
        exit;
    }

    $table = new Table;
    $table->construct_header("Plugin", array('width' => '35%'));
    $table->construct_header("Version", array('width' => '10%', 'class' => 'align_center'));
    $table->construct_header("Author", array('width' => '15%', 'class' => 'align_center'));
    $table->construct_header("Features", array('width' => '15%', 'class' => 'align_center'));
    $table->construct_header("Actions", array('class' => 'align_center'));

    foreach ($allPlugins as $p) {
        $isEnabled = !isset($states[$p['id']]) || $states[$p['id']]; // default enabled

        // Name + description
        $nameCell = '<strong>' . htmlspecialchars_uni($p['name']) . '</strong>';
        if ($isEnabled) {
            $nameCell .= ' <span style="background:#0d9488;color:#fff;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:bold">ON</span>';
        } else {
            $nameCell .= ' <span style="background:#888;color:#fff;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:bold">OFF</span>';
        }
        if ($p['description']) {
            $nameCell .= '<br><span style="color:#888;font-size:12px">' . htmlspecialchars_uni($p['description']) . '</span>';
        }
        $table->construct_cell($nameCell);

        // Version
        $table->construct_cell(htmlspecialchars_uni($p['version']), array('class' => 'align_center'));

        // Author
        $table->construct_cell(htmlspecialchars_uni($p['author']), array('class' => 'align_center'));

        // Features
        $features = array();
        if ($p['has_init']) $features[] = '<span title="Registers hooks">Hooks</span>';
        if ($p['has_options']) $features[] = '<span title="Has configurable options">Options</span>';
        if ($p['has_js']) $features[] = '<span title="Includes JavaScript">JS</span>';
        if ($p['has_css']) $features[] = '<span title="Includes CSS">CSS</span>';
        if ($p['has_admin']) $features[] = '<span title="Has admin page">Admin</span>';
        $table->construct_cell(implode(' &middot; ', $features), array('class' => 'align_center', 'style' => 'font-size:11px;color:#666'));

        // Actions
        $actions = array();

        if ($isEnabled) {
            $actions[] = '<a class="fmz-btn" href="index.php?module=fmzstudio-manage&action=api_toggle_plugin&plugin_id='
                       . htmlspecialchars_uni($p['id']) . '&enable=0&my_post_key=' . $post_key
                       . '" style="' . $btnStyleDanger . '" onclick="return confirm(\'Disable this plugin?\')"><i class=\'bi bi-power\'></i> Disable</a>';
        } else {
            $actions[] = '<a class="fmz-btn" href="index.php?module=fmzstudio-manage&action=api_toggle_plugin&plugin_id='
                       . htmlspecialchars_uni($p['id']) . '&enable=1&my_post_key=' . $post_key
                       . '" style="' . $btnStyleSuccess . '"><i class=\'bi bi-power\'></i> Enable</a>';
        }

        if ($p['has_options'] || $p['has_admin']) {
            $actions[] = '<a class="fmz-btn" href="index.php?module=fmzstudio-plugin_settings&plugin='
                       . htmlspecialchars_uni($p['id'])
                       . '" style="' . $btnStyle . '"><i class=\'bi bi-gear\'></i> Settings</a>';
        }

        $table->construct_cell('<div style="display:flex;gap:6px;justify-content:flex-end">' . implode('', $actions) . '</div>');
        $table->construct_row();
    }

    $table->output("Theme Plugins: " . htmlspecialchars_uni($activeSlug));

    echo fmz_help_box('Mini Plugins', '
<p>Mini plugins are self-contained extensions bundled inside themes. They live under <code>themes/{slug}/functions/plugins/{plugin-name}/</code>.</p>
<p>Each mini plugin requires a <code>plugin.json</code> manifest and an <code>init.php</code> entry point. Optionally, plugins can include <code>options.php</code> for configurable settings, plus <code>css/</code> and <code>js/</code> folders for auto-loaded assets.</p>

<h4 style="margin:10px 0 6px;font-size:13px">Features Column</h4>
<table style="width:100%;font-size:12px;border-collapse:collapse">
<tr><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0;width:90px"><strong>Hooks</strong></td><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0">Plugin has an <code>init.php</code> that registers MyBB hooks — it can modify forum behaviour.</td></tr>
<tr><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0"><strong>Options</strong></td><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0">Plugin has an <code>options.php</code> with configurable settings accessible via the Settings button.</td></tr>
<tr><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0"><strong>JS</strong></td><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0">Plugin includes JavaScript files in its <code>js/</code> folder that are auto-loaded on every page.</td></tr>
<tr><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0"><strong>CSS</strong></td><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0">Plugin includes stylesheets in its <code>css/</code> folder that are auto-injected into the page head.</td></tr>
<tr><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0"><strong>Admin</strong></td><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0">Plugin has a custom <code>admin.php</code> that renders additional admin content on its settings page.</td></tr>
</table>

<h4 style="margin:10px 0 6px;font-size:13px">For Developers</h4>
<ul style="margin:6px 0 6px 20px">
<li>Create a new plugin by adding a folder under <code>themes/{slug}/functions/plugins/{your-plugin}/</code>.</li>
<li>The folder must contain a <code>plugin.json</code> with <code>name</code>, <code>version</code>, <code>author</code>, and <code>description</code> fields.</li>
<li>Add <code>init.php</code> to register hooks, <code>options.php</code> to define settings, and <code>css/</code>/<code>js/</code> folders for assets.</li>
<li>Plugins are enabled by default. Users can toggle them on/off without deleting files.</li>
<li>See <a href="index.php?module=fmzstudio-docs&tab=plugin_structure">Plugin Structure docs</a> for the full specification.</li>
</ul>
');

    $page->output_footer();
    exit;
}

/* ====================================================================
   Settings Page — Plugin-level settings (moved from Configuration)
   ==================================================================== */

if ($action === 'settings') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Settings");

    // Ensure new settings exist (migration for existing installations)
    $newSettings = array(
        'fmz_dev_auto_sync'     => array('title' => 'Auto Sync (Dev Mode)', 'description' => 'Automatically sync theme files to the database when changes are detected.', 'optionscode' => 'yesno', 'value' => '0', 'disporder' => 3, 'gid' => 0),
        'fmz_dev_sync_interval' => array('title' => 'Auto Sync Interval (seconds)', 'description' => 'How often to check for file changes.', 'optionscode' => 'numeric', 'value' => '2', 'disporder' => 4, 'gid' => 0),
    );
    foreach ($newSettings as $name => $def) {
        $check = $db->simple_select('settings', 'name', "name='" . $db->escape_string($name) . "'");
        if (!$db->num_rows($check)) {
            $def['name'] = $name;
            $db->insert_query('settings', $def);
        }
    }
    rebuild_settings();

    // Handle save
    if ($mybb->request_method === 'post') {
        verify_post_check($mybb->get_input('my_post_key'));

        // Define our settings and their sanitisation
        $settingsToSave = array(
            'fmz_enabled'           => intval($mybb->get_input('fmz_enabled')),
            'fmz_max_upload_mb'     => max(1, intval($mybb->get_input('fmz_max_upload_mb'))),
            'fmz_dev_auto_sync'     => intval($mybb->get_input('fmz_dev_auto_sync')),
            'fmz_dev_sync_interval' => max(1, intval($mybb->get_input('fmz_dev_sync_interval'))),
        );

        foreach ($settingsToSave as $name => $value) {
            $db->update_query('settings', array('value' => $db->escape_string($value)), "name='" . $db->escape_string($name) . "'");
        }

        rebuild_settings();

        flash_message('Settings saved successfully.', 'success');
        admin_redirect("index.php?module=fmzstudio-settings");
    }

    // Read current values
    $currentSettings = array();
    $query = $db->simple_select('settings', 'name, value', "name LIKE 'fmz_%'");
    while ($row = $db->fetch_array($query)) {
        $currentSettings[$row['name']] = $row['value'];
    }

    $page->output_header("FMZ Studio - Studio Settings");

    $form = new Form("index.php?module=fmzstudio-settings", "post");

    $form_container = new FormContainer("Plugin Settings");

    $form_container->output_row(
        "Enable FMZ Studio",
        "Master switch to enable or disable FMZ Studio on the frontend. When disabled, themes will still be manageable from the admin panel but no FMZ features will load on the forum.",
        $form->generate_yes_no_radio('fmz_enabled', isset($currentSettings['fmz_enabled']) ? $currentSettings['fmz_enabled'] : 1)
    );

    $form_container->output_row(
        "Max Upload Size (MB)",
        "Maximum allowed ZIP file size in megabytes for theme imports.",
        $form->generate_numeric_field('fmz_max_upload_mb', isset($currentSettings['fmz_max_upload_mb']) ? $currentSettings['fmz_max_upload_mb'] : 20, array('min' => 1, 'max' => 500, 'style' => 'width:80px'))
    );

    $form_container->end();

    $form_container = new FormContainer("Developer Settings");

    $form_container->output_row(
        "Auto Sync (Dev Mode)",
        "When enabled, the forum will poll for file changes in the active theme directory every few seconds and automatically sync to the database. Only runs for admin users browsing the frontend. <strong>Disable in production.</strong>",
        $form->generate_yes_no_radio('fmz_dev_auto_sync', isset($currentSettings['fmz_dev_auto_sync']) ? $currentSettings['fmz_dev_auto_sync'] : 0)
    );

    $form_container->output_row(
        "Auto Sync Interval (seconds)",
        "How often to check for file changes. Lower = faster feedback, higher = less server load. Recommended: 2–5 seconds.",
        $form->generate_numeric_field('fmz_dev_sync_interval', isset($currentSettings['fmz_dev_sync_interval']) ? $currentSettings['fmz_dev_sync_interval'] : 2, array('min' => 1, 'max' => 60, 'style' => 'width:80px'))
    );

    $form_container->end();

    $buttons = array($form->generate_submit_button("Save Settings"));
    $form->output_submit_wrapper($buttons);

    $form->end();

    echo fmz_help_box('Studio Settings', '
<p>Global settings for the FMZ Studio plugin. These affect the entire installation.</p>
<ul style="margin:6px 0 6px 20px">
<li><strong>Enable FMZ Studio</strong> — Master switch. When off, themes are still manageable in the Admin CP but no FMZ features (mini plugins, hooks, auto-sync) load on the frontend.</li>
<li><strong>Max Upload Size</strong> — Maximum ZIP file size allowed when importing themes.</li>
<li><strong>Auto Sync (Dev Mode)</strong> — When enabled, theme files are automatically synced to the database whenever changes are detected. Only operates for users with admin privileges. The page will auto-reload when a sync occurs.</li>
<li><strong>Auto Sync Interval</strong> — How frequently (in seconds) to check for file changes. Lower values give faster feedback but increased server load.</li>
</ul>
');

    $page->output_footer();
    exit;
}

/* ====================================================================
   Manage Page (default)
   ==================================================================== */

$page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");

$page->output_header("FMZ Studio - Manage");

$post_key = $mybb->post_code;

echo $fmzBtnHoverCss;

// -- Gather theme data --
$dbThemes   = $fmz->listDbThemes();
$diskThemes = $fmz->listThemesOnDisk();

// Build lookup maps
$dbByName = array();
foreach ($dbThemes as $t) {
    $dbByName[strtolower(trim($t['name']))] = $t;
}
$diskBySlug = array();
foreach ($diskThemes as $t) {
    $diskBySlug[$t['slug']] = $t;
}

/* ----------------------------------------------------------------
   Broken Theme Detection
   ---------------------------------------------------------------- */

$brokenThemes = array();
$baseDir = MYBB_ROOT . 'themes';

if (is_dir($baseDir)) {
    foreach (scandir($baseDir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $dir = $baseDir . '/' . $entry;
        if (!is_dir($dir)) continue;

        $issues = array();
        $themeName = $entry; // fallback display name

        // Check theme.json
        $jsonPath = $dir . '/theme.json';
        if (!file_exists($jsonPath)) {
            $issues[] = 'Missing <strong>theme.json</strong> manifest file.';
        } else {
            $raw = @file_get_contents($jsonPath);
            $cfg = @json_decode($raw, true);

            if ($raw === false || $raw === '') {
                $issues[] = '<strong>theme.json</strong> is empty or unreadable.';
            } elseif ($cfg === null && json_last_error() !== JSON_ERROR_NONE) {
                $issues[] = '<strong>theme.json</strong> contains invalid JSON: '
                          . htmlspecialchars_uni(json_last_error_msg());
            } else {
                if (empty($cfg['name'])) {
                    $issues[] = '<strong>theme.json</strong> is missing the required <code>"name"</code> field.';
                } else {
                    $themeName = $cfg['name'];
                }

                // Check if name conflicts with a different slug
                if (!empty($cfg['name'])) {
                    $expectedSlug = $fmz->slug($cfg['name']);
                    if ($expectedSlug !== $entry) {
                        $issues[] = 'Directory name <code>' . htmlspecialchars_uni($entry)
                                  . '</code> does not match the expected slug <code>'
                                  . htmlspecialchars_uni($expectedSlug)
                                  . '</code> (from theme name "' . htmlspecialchars_uni($cfg['name']) . '").';
                    }
                }

                // Validate stylesheets references
                if (!empty($cfg['stylesheets']) && is_array($cfg['stylesheets'])) {
                    foreach ($cfg['stylesheets'] as $ss) {
                        if (!empty($ss['name'])) {
                            $cssFile = $dir . '/css/' . $ss['name'];
                            if (!file_exists($cssFile)) {
                                $issues[] = 'Stylesheet <code>css/' . htmlspecialchars_uni($ss['name'])
                                          . '</code> referenced in theme.json but file is missing.';
                            }
                        }
                    }
                }

                // Validate JS references
                if (!empty($cfg['js']) && is_array($cfg['js'])) {
                    foreach ($cfg['js'] as $jsFile) {
                        $jsPath = $dir . '/js/' . $jsFile;
                        if (!file_exists($jsPath)) {
                            $issues[] = 'JavaScript file <code>js/' . htmlspecialchars_uni($jsFile)
                                      . '</code> referenced in theme.json but file is missing.';
                        }
                    }
                }
            }
        }

        // Check templates directory
        $tmplDir = $dir . '/templates';
        if (!is_dir($tmplDir)) {
            $issues[] = 'Missing <strong>templates/</strong> directory.';
        } else {
            // Check for at least one .html template file
            $hasHtml = false;
            $scan = function ($d) use (&$scan, &$hasHtml) {
                foreach (scandir($d) as $e) {
                    if ($e === '.' || $e === '..') continue;
                    $p = $d . '/' . $e;
                    if (is_dir($p)) {
                        $scan($p);
                    } elseif (pathinfo($e, PATHINFO_EXTENSION) === 'html') {
                        $hasHtml = true;
                        return;
                    }
                    if ($hasHtml) return;
                }
            };
            $scan($tmplDir);
            if (!$hasHtml) {
                $issues[] = '<strong>templates/</strong> directory contains no <code>.html</code> template files.';
            }
        }

        // Check CSS directory (optional but warn if referenced)
        if (file_exists($jsonPath) && isset($cfg['stylesheets']) && !empty($cfg['stylesheets'])) {
            if (!is_dir($dir . '/css')) {
                $issues[] = 'theme.json references stylesheets but the <strong>css/</strong> directory is missing.';
            }
        }

        // Check DB sync status
        $nameKey = strtolower(trim($themeName));
        if (!isset($dbByName[$nameKey]) && file_exists($jsonPath) && isset($cfg) && is_array($cfg) && !empty($cfg['name'])) {
            $issues[] = 'Theme exists on disk but is <strong>not synced</strong> to the database. Click "Sync" to import.';
        }

        // Check file permissions
        if (!is_readable($dir)) {
            $issues[] = 'Theme directory is not readable (permission issue).';
        }
        if (file_exists($jsonPath) && !is_readable($jsonPath)) {
            $issues[] = '<strong>theme.json</strong> is not readable (permission issue).';
        }

        // Only report if there are actual structural issues (not just unsynced)
        if (!empty($issues)) {
            // Skip directories already listed as valid disk themes with no real issues
            // (an "unsynced" warning alone is informational, not broken)
            $realIssues = array_filter($issues, function ($msg) {
                return strpos($msg, 'not synced') === false;
            });

            $brokenThemes[] = array(
                'slug'       => $entry,
                'name'       => $themeName,
                'issues'     => $issues,
                'is_broken'  => !empty($realIssues),
            );
        }
    }
}

// Display broken themes warning if any have real issues
$reallyBroken = array_filter($brokenThemes, function ($t) { return $t['is_broken']; });
if (!empty($reallyBroken)) {
    echo '<div style="background:#2c1b1b;border:1px solid #5a2d2d;border-radius:6px;padding:16px 20px;margin-bottom:20px;color:#e8a0a0">';
    echo '<h3 style="margin:0 0 10px;color:#f48771;font-size:15px">&#x26A0; Broken Themes Detected</h3>';
    echo '<p style="margin:0 0 12px;color:#caa;font-size:13px">The following theme directories have issues that prevent them from working correctly:</p>';

    foreach ($reallyBroken as $bt) {
        echo '<div style="background:#1e1212;border:1px solid #4a2020;border-radius:4px;padding:12px 16px;margin-bottom:10px">';
        echo '<strong style="color:#f0b0b0;font-size:14px">'
           . htmlspecialchars_uni($bt['name'])
           . '</strong> <span style="color:#888;font-size:12px">(themes/'
           . htmlspecialchars_uni($bt['slug']) . '/)</span>';
        echo '<ul style="margin:8px 0 0;padding-left:20px;color:#d4a0a0;font-size:12px;line-height:1.8">';
        foreach ($bt['issues'] as $issue) {
            $icon = (strpos($issue, 'not synced') !== false) ? '&#x2139;' : '&#x2717;';
            $color = (strpos($issue, 'not synced') !== false) ? '#e2b340' : '#f48771';
            echo '<li style="color:' . $color . '">' . $icon . ' ' . $issue . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    echo '</div>';
}

/* ----------------------------------------------------------------
   Theme Table
   ---------------------------------------------------------------- */

$table = new Table;
$table->construct_header("Theme", array('width' => '30%'));
$table->construct_header("Status", array('width' => '15%', 'class' => 'align_center'));
$table->construct_header("Disk", array('width' => '10%', 'class' => 'align_center'));
$table->construct_header("Actions", array('width' => '35%', 'class' => 'align_center'));

if (empty($dbThemes) && empty($diskThemes)) {
    $table->construct_cell("No themes found. Import a theme to get started.", array('colspan' => 4));
    $table->construct_row();
} else {
    // Show DB themes
    foreach ($dbThemes as $t) {
        // Theme name
        $nameCell = '<strong>' . htmlspecialchars_uni($t['name']) . '</strong>';
        if ($t['is_default']) {
            $nameCell .= ' <span style="background:#0d9488;color:#fff;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:bold">DEFAULT</span>';
        }
        $table->construct_cell($nameCell);

        // Status
        if ($t['is_default']) {
            $table->construct_cell('<span style="color:#0d9488;font-weight:bold">Active</span>', array('class' => 'align_center'));
        } else {
            $table->construct_cell('<span style="color:#888">Inactive</span>', array('class' => 'align_center'));
        }

        // Disk
        if ($t['has_disk']) {
            $table->construct_cell('<span style="color:#0d9488" title="Theme files exist on disk">&#x2713;</span>', array('class' => 'align_center'));
        } else {
            $table->construct_cell('<span style="color:#888" title="Database only">&#x2717;</span>', array('class' => 'align_center'));
        }

        // Actions
        $actions = array();

        if ($t['has_disk'] && $t['slug']) {
            $actions[] = '<a class="fmz-btn" href="index.php?module=fmzstudio-manage&action=editor&slug='
                       . htmlspecialchars_uni($t['slug'])
                       . '" style="' . $btnStyle . '"><i class=\'bi bi-pencil-square\'></i> Edit</a>';
            $actions[] = '<a class="fmz-btn" href="index.php?module=fmzstudio-manage&action=sync_theme&slug='
                       . htmlspecialchars_uni($t['slug'])
                       . '&my_post_key=' . $post_key
                       . '" style="' . $btnStyleWarn . '" onclick="return confirm(\'Sync this theme from disk to database?\')"><i class=\'bi bi-arrow-repeat\'></i> Sync</a>';
        } else {
            $actions[] = '<a class="fmz-btn" href="index.php?module=fmzstudio-manage&action=convert&tid='
                       . $t['tid'] . '&my_post_key=' . $post_key
                       . '" style="' . $btnStyleWarn . '" onclick="return confirm(\'Extract this theme to disk?\')"><i class=\'bi bi-hdd\'></i> Convert to Disk</a>';
        }

        if ($t['is_default']) {
            $actions[] = '<a class="fmz-btn" href="index.php?module=fmzstudio-manage&action=deactivate&tid='
                       . $t['tid'] . '&my_post_key=' . $post_key
                       . '" style="' . $btnStyleDanger . '" onclick="return confirm(\'Deactivate this theme?\')"><i class=\'bi bi-x-circle\'></i> Deactivate</a>';
        } else {
            $actions[] = '<a class="fmz-btn" href="index.php?module=fmzstudio-manage&action=activate&tid='
                       . $t['tid'] . '&my_post_key=' . $post_key
                       . '" style="' . $btnStyleSuccess . '"><i class=\'bi bi-check-circle\'></i> Set Default</a>';

            // Delete button (not shown for active/default theme)
            $deleteMsg = $t['has_disk']
                ? 'Delete this theme? This will remove it from the database AND delete all files from disk. This cannot be undone.'
                : 'Delete this theme from the database? This cannot be undone.';
            $actions[] = '<a class="fmz-btn" href="index.php?module=fmzstudio-manage&action=delete_theme&tid='
                       . $t['tid'] . '&disk=' . ($t['has_disk'] ? '1' : '0')
                       . '&my_post_key=' . $post_key
                       . '" style="' . $btnStyleDanger . '" onclick="return confirm(\'' . $deleteMsg . '\')"><i class=\'bi bi-trash3\'></i> Delete</a>';
        }

        $table->construct_cell('<div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">' . implode('', $actions) . '</div>');
        $table->construct_row();
    }

    // Show disk-only themes (not in DB)
    foreach ($diskThemes as $dt) {
        if ($dt['has_db']) continue; // already shown above

        $nameCell = '<strong>' . htmlspecialchars_uni($dt['name']) . '</strong>'
                  . ' <span style="background:#e2b340;color:#1e1e1e;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:bold">DISK ONLY</span>';
        $table->construct_cell($nameCell);

        $table->construct_cell('<span style="color:#e2b340">Not Synced</span>', array('class' => 'align_center'));
        $table->construct_cell('<span style="color:#0d9488">&#x2713;</span>', array('class' => 'align_center'));

        $actions = array();
        $actions[] = '<a class="fmz-btn" href="index.php?module=fmzstudio-manage&action=editor&slug='
                   . htmlspecialchars_uni($dt['slug'])
                   . '" style="' . $btnStyle . '"><i class=\'bi bi-pencil-square\'></i> Edit</a>';
        $actions[] = '<a class="fmz-btn" href="index.php?module=fmzstudio-manage&action=sync_theme&slug='
                   . htmlspecialchars_uni($dt['slug'])
                   . '&my_post_key=' . $post_key
                   . '" style="' . $btnStyleWarn . '" onclick="return confirm(\'Sync this theme from disk to database?\')"><i class=\'bi bi-arrow-repeat\'></i> Sync</a>';

        // Delete button for disk-only themes
        $actions[] = '<a class="fmz-btn" href="index.php?module=fmzstudio-manage&action=delete_theme&tid=0&disk=1&slug='
                   . htmlspecialchars_uni($dt['slug'])
                   . '&my_post_key=' . $post_key
                   . '" style="' . $btnStyleDanger . '" onclick="return confirm(\'Delete this theme from disk? All files in themes/' . htmlspecialchars_uni($dt['slug']) . '/ will be removed. This cannot be undone.\')"><i class=\'bi bi-trash3\'></i> Delete</a>';

        $table->construct_cell('<div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">' . implode('', $actions) . '</div>');
        $table->construct_row();
    }
}

$table->output("Themes");

echo fmz_help_box('Theme Management', '
<p>This page lists all themes found in the database and on disk. From here you can:</p>
<ul style="margin:6px 0 6px 20px">
<li><strong>Set Default</strong> — Make a theme the active board theme visible to all users.</li>
<li><strong>Sync</strong> — Push file changes from <code>themes/{slug}/</code> into the database. Use this after editing files on disk or via the code editor.</li>
<li><strong>Edit</strong> — Open the built-in Monaco code editor to edit theme files directly in the browser.</li>
<li><strong>Convert to Disk</strong> — Extract a database-only theme into a <code>themes/{slug}/</code> directory for file-based editing.</li>
<li><strong>Delete</strong> — Remove a theme from both the database and disk. Cannot delete the currently active default theme.</li>
</ul>

<h4 style="margin:12px 0 6px;font-size:13px">Status Column Meanings</h4>
<table style="width:100%;font-size:12px;border-collapse:collapse">
<tr><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0;width:130px"><strong style="color:#0d9488">Active</strong></td><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0">This is the board\'s default theme — what visitors see. Only one theme can be active at a time.</td></tr>
<tr><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0"><span style="color:#888">Inactive</span></td><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0">Theme exists in the database but is not the default. Users can still select it if user theme switching is enabled in MyBB settings.</td></tr>
<tr><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0"><span style="color:#e2b340">Not Synced</span></td><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0">Theme files exist on disk but have not been imported into the database yet. Click <strong>Sync</strong> to import them.</td></tr>
</table>

<h4 style="margin:12px 0 6px;font-size:13px">Disk Column Meanings</h4>
<table style="width:100%;font-size:12px;border-collapse:collapse">
<tr><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0;width:130px"><span style="color:#0d9488">&#x2713; (On Disk)</span></td><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0">Theme files exist in the <code>themes/{slug}/</code> directory on the server. You can edit files directly, use the code editor, and sync changes to the database.</td></tr>
<tr><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0"><span style="color:#888">&#x2717; (DB Only)</span></td><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0">Theme exists only in the database (e.g. a native MyBB theme). Use <strong>Convert to Disk</strong> to extract it into a <code>themes/</code> folder so it can be managed by FMZ Studio.</td></tr>
<tr><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0"><span style="background:#e2b340;color:#1e1e1e;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:bold">DISK ONLY</span></td><td style="padding:4px 8px;border-bottom:1px solid #e0e0e0">Theme files are on disk but there is <em>no corresponding database entry</em>. The theme cannot be used until you click <strong>Sync</strong> to import it. This is normal for freshly created or uploaded themes.</td></tr>
</table>

<h4 style="margin:12px 0 6px;font-size:13px">Developer Tips</h4>
<ul style="margin:6px 0 6px 20px">
<li><strong>Dev Mode Auto-Sync:</strong> Enable in <a href="index.php?module=fmzstudio-settings">Studio Settings</a> to automatically sync file changes to the database whenever you save a file. No more manual syncing!</li>
<li><strong>File Editing Workflow:</strong> Edit files in your IDE or code editor of choice, then sync (or let auto-sync handle it). Changes to CSS, templates, and JS are reflected immediately after sync.</li>
<li><strong>Theme Structure:</strong> Each theme lives in <code>themes/{slug}/</code> with <code>theme.json</code> (manifest), <code>templates/</code>, <code>css/</code>, <code>js/</code>, and optional <code>functions/</code> and <code>images/</code> directories.</li>
</ul>
');

$page->output_footer();
