<?php
/**
 * Plugin Name: Sensi Merch Forge (Persona Shop + Paid Merch Unlock)
 * Description: Persona art selection → checkout → paid-only merch unlock page (no label system).
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

class Sensi_Merch_Forge_Plugin {

  // ====== SET THESE (YOUR WOO PRODUCT IDS) ======
  private $products = [
    'mug_11oz'        => 7960,
    'tumbler_20oz'    => 7992,
    'tumbler_40oz'    => 7992,
    'mousepad'        => 7995,
    'tote_bag'        => 7988,
    'shoulder_bag'    => 7977,
    'canvas'          => 7965,
    'hoodie'          => 11009,
  ];

  // Optional: send them here after payment
  private $merch_forge_page_url = '/sensi-forged-merch-generator/';

  // Demo images (replace later per persona)
  private $demo_images = [
    'demo_01' => 'https://sensianduniq.com/wp-content/uploads/2025/12/Gemini_Generated_Image_pkeb14pkeb14pkeb-1-1.png',
    'demo_02' => 'https://sensianduniq.com/wp-content/uploads/2025/12/Gemini_Generated_Image_w3ao5ww3ao5ww3ao.png',
    'demo_03' => 'https://sensianduniq.com/wp-content/uploads/2025/12/Gemini_Generated_Image_9449kh9449kh9449-1.png',
  ];

  public function __construct() {
    add_shortcode('sensi_merch_shop', [$this, 'shortcode_merch_shop']);
    add_shortcode('sensi_merch_unlock', [$this, 'shortcode_merch_unlock']);
    add_shortcode('sensi_label_forge_v2', [$this, 'shortcode_label_forge_v2']);

    // capture selection on add-to-cart
    add_filter('woocommerce_add_cart_item_data', [$this, 'capture_cart_meta'], 10, 2);
    add_filter('woocommerce_get_item_data', [$this, 'show_cart_meta'], 10, 2);
    add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_order_meta'], 10, 4);

    // generate code + redirect link after payment
    add_action('woocommerce_order_status_processing', [$this, 'generate_code_on_paid']);
    add_action('woocommerce_order_status_completed',  [$this, 'generate_code_on_paid']);

    // Optional: redirect after successful checkout
    add_action('woocommerce_thankyou', [$this, 'thankyou_redirect'], 20, 1);
  }

  public function shortcode_merch_shop($atts) {
    if (!function_exists('wc_get_checkout_url')) return '<p><strong>WooCommerce required.</strong></p>';

    $atts = shortcode_atts([
      'collection' => 'SENSI',
      'persona'    => 'DEFAULT-PERSONA',
      'images'     => '',
    ], $atts);

    $collection_id = sanitize_text_field($atts['collection']);
    $persona_id    = sanitize_text_field($atts['persona']);

    $images = $this->demo_images;
    if (!empty($atts['images'])) {
      $parsed = $this->parse_images_attr($atts['images']);
      if (!empty($parsed)) $images = $parsed;
    }

    $product_choices = [
      ['key'=>'mug_11oz',     'label'=>'Mug (11oz)'],
      ['key'=>'tumbler_20oz', 'label'=>'Tumbler (20oz)'],
      ['key'=>'tumbler_40oz', 'label'=>'Tumbler (40oz)'],
      ['key'=>'mousepad',     'label'=>'Mousepad'],
      ['key'=>'tote_bag',     'label'=>'Tote Bag'],
      ['key'=>'shoulder_bag', 'label'=>'Shoulder Bag'],
      ['key'=>'canvas',       'label'=>'Canvas'],
      ['key'=>'hoodie',       'label'=>'Hoodie'],
    ];

    ob_start(); ?>
    <style>
      .smf-wrap{max-width:1180px;margin:0 auto;padding:28px 16px;font-family:system-ui,-apple-system,Segoe UI,Roboto;background:#050505;color:#fff}
      .smf-title{font-size:34px;font-weight:900;margin:0}
      .smf-sub{opacity:.8;margin:10px 0 18px;line-height:1.35}
      .smf-panel{background:#0f0f12;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:16px;margin-top:14px}
      .smf-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-top:12px}
      .smf-card{position:relative;border-radius:14px;overflow:hidden;border:2px solid rgba(255,255,255,.08);cursor:pointer;background:#0a0a0a}
      .smf-card img{width:100%;height:250px;object-fit:cover;display:block}
      .smf-card.sel{border-color:#ff4fd8;box-shadow:0 0 0 3px rgba(255,79,216,.25)}
      .smf-badge{position:absolute;top:10px;left:10px;background:rgba(0,0,0,.6);padding:6px 10px;border-radius:999px;font-size:12px}
      .smf-products{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-top:10px}
      .smf-pick{border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:12px;cursor:pointer}
      .smf-pick.sel{border-color:#00d4ff;box-shadow:0 0 0 3px rgba(0,212,255,.18)}
      .smf-cta{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:14px}
      .smf-btn{display:inline-block;background:linear-gradient(90deg,#ff4fd8,#00d4ff);color:#050505;
        padding:14px 20px;border-radius:999px;text-decoration:none;font-weight:900}
      .smf-btn[aria-disabled="true"]{opacity:.55;pointer-events:none}
      .smf-note{opacity:.75;font-size:13px}
    </style>

    <div class="smf-wrap">
      <div class="smf-title">Pick Your Art → Pick Your Merch</div>
      <div class="smf-sub">
        Choose the image you like, choose the merch item, checkout. After payment you unlock the merch generator flow.
      </div>

      <div class="smf-panel">
        <div style="font-weight:900">1) Select Artwork</div>
        <div class="smf-grid" id="smfImageGrid">
          <?php foreach ($images as $img_id => $url): ?>
            <div class="smf-card" data-image-id="<?php echo esc_attr($img_id); ?>" data-image-url="<?php echo esc_url($url); ?>">
              <span class="smf-badge"><?php echo esc_html($img_id); ?></span>
              <img src="<?php echo esc_url($url); ?>" alt="">
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="smf-panel">
        <div style="font-weight:900">2) Select Merch Item</div>
        <div class="smf-products" id="smfProductGrid">
          <?php foreach ($product_choices as $p): ?>
            <div class="smf-pick" data-product-key="<?php echo esc_attr($p['key']); ?>">
              <div style="font-weight:900"><?php echo esc_html($p['label']); ?></div>
              <div class="smf-note">Goes to checkout immediately.</div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="smf-cta">
          <a href="#" id="smfBuyBtn" class="smf-btn" aria-disabled="true">Continue to Checkout</a>
          <div class="smf-note">After payment, we’ll unlock your merch generator page.</div>
        </div>
      </div>
    </div>

    <script>
      (function(){
        const collId = <?php echo json_encode($collection_id); ?>;
        const personaId = <?php echo json_encode($persona_id); ?>;
        const productMap = <?php echo json_encode($this->products); ?>;

        let selectedImageId = null;
        let selectedImageUrl = null;
        let selectedProductKey = null;

        const imgCards = document.querySelectorAll('.smf-card');
        imgCards.forEach(c => c.addEventListener('click', () => {
          imgCards.forEach(x => x.classList.remove('sel'));
          c.classList.add('sel');
          selectedImageId = c.dataset.imageId;
          selectedImageUrl = c.dataset.imageUrl;
          updateBuyLink();
        }));

        const prodCards = document.querySelectorAll('.smf-pick');
        prodCards.forEach(p => p.addEventListener('click', () => {
          prodCards.forEach(x => x.classList.remove('sel'));
          p.classList.add('sel');
          selectedProductKey = p.dataset.productKey;
          updateBuyLink();
        }));

        function updateBuyLink(){
          const btn = document.getElementById('smfBuyBtn');

          if(!selectedImageId || !selectedProductKey){
            btn.href = '#';
            btn.setAttribute('aria-disabled','true');
            return;
          }

          const wooProductId = productMap[selectedProductKey];
          if(!wooProductId){
            btn.href = '#';
            btn.setAttribute('aria-disabled','true');
            return;
          }

          const url = new URL('<?php echo esc_url(wc_get_checkout_url()); ?>', window.location.origin);
          url.searchParams.set('add-to-cart', wooProductId);

          // pack selection into query so we can capture it into cart/order
          url.searchParams.set('sf_collection', collId);
          url.searchParams.set('sf_persona', personaId);
          url.searchParams.set('sf_image_id', selectedImageId);
          url.searchParams.set('sf_image_url', selectedImageUrl);
          url.searchParams.set('sf_product_key', selectedProductKey);

          btn.href = url.toString();
          btn.setAttribute('aria-disabled','false');
        }
      })();
    </script>
    <?php
    return ob_get_clean();
  }

  public function capture_cart_meta($cart_item_data, $product_id) {
    if (isset($_GET['sf_image_id'], $_GET['sf_image_url'], $_GET['sf_product_key'])) {
      $cart_item_data['sf_selection'] = [
        'collection'  => sanitize_text_field($_GET['sf_collection'] ?? ''),
        'persona'     => sanitize_text_field($_GET['sf_persona'] ?? ''),
        'image_id'    => sanitize_text_field($_GET['sf_image_id']),
        'image_url'   => esc_url_raw($_GET['sf_image_url']),
        'product_key' => sanitize_text_field($_GET['sf_product_key']),
      ];
      $cart_item_data['unique_key'] = md5(microtime(true).rand());
    }
    return $cart_item_data;
  }

  public function show_cart_meta($item_data, $cart_item) {
    if (!empty($cart_item['sf_selection'])) {
      $sel = $cart_item['sf_selection'];
      $item_data[] = ['name'=>'Design', 'value'=>esc_html($sel['image_id'])];
      $item_data[] = ['name'=>'Persona', 'value'=>esc_html($sel['persona'])];
    }
    return $item_data;
  }

  public function save_order_meta($item, $cart_item_key, $values, $order) {
    if (!empty($values['sf_selection'])) {
      $item->add_meta_data('sf_selection', wp_json_encode($values['sf_selection']));
    }
  }

  public function generate_code_on_paid($order_id) {
    if (!function_exists('wc_get_order')) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    if ($order->get_meta('sf_code')) return;

    $selection = null;
    foreach ($order->get_items() as $item) {
      $raw = $item->get_meta('sf_selection');
      if ($raw) { $selection = json_decode($raw, true); break; }
    }
    if (!$selection) return;

    $coll = strtoupper(preg_replace('/[^A-Z0-9]+/i','', $selection['collection'] ?: 'GEN'));
    $pid  = strtoupper(preg_replace('/[^A-Z0-9]+/i','', $selection['persona'] ?: 'P'));
    $img  = strtoupper(preg_replace('/[^A-Z0-9]+/i','', $selection['image_id'] ?: 'I'));
    $prod = strtoupper(preg_replace('/[^A-Z0-9]+/i','', $selection['product_key'] ?: 'PROD'));
    $ord  = (string)$order_id;

    $chk = substr(strtoupper(hash('sha256', $order_id.'|'.$img.'|'.$prod.'|'.wp_salt('auth'))), 0, 4);
    $code = "SM-{$coll}-{$pid}-{$img}-{$prod}-{$ord}-{$chk}";

    $order->update_meta_data('sf_code', $code);
    $order->save();
  }

  public function thankyou_redirect($order_id) {
    if (!$order_id) return;
    $order = wc_get_order($order_id);
    if (!$order) return;

    if (!in_array($order->get_status(), ['processing','completed'], true)) return;

    $code = $order->get_meta('sf_code');
    if (!$code) {
      $this->generate_code_on_paid($order_id);
      $code = $order->get_meta('sf_code');
    }

    $unlock_url = home_url(trailingslashit(ltrim($this->merch_forge_page_url, '/')));
    $unlock_url = add_query_arg([
      'order' => $order_id,
      'key'   => $order->get_order_key(),
    ], $unlock_url);

    $current = (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
    if (strpos($current, $this->merch_forge_page_url) !== false) return;

    wp_safe_redirect($unlock_url);
    exit;
  }

  public function shortcode_merch_unlock() {
    if (!function_exists('wc_get_order')) return '<p><strong>WooCommerce required.</strong></p>';

    $order_id = absint($_GET['order'] ?? 0);
    $key      = sanitize_text_field($_GET['key'] ?? '');

    if (!$order_id || !$key) {
      return '<div style="max-width:820px;margin:0 auto;padding:24px;font-family:system-ui"><h2>Merch Unlock</h2><p>Open this page from your paid order link (it includes order + key).</p></div>';
    }

    $order = wc_get_order($order_id);
    if (!$order) return '<p>Order not found.</p>';
    if ($order->get_order_key() !== $key) return '<p>Invalid order key.</p>';

    if (!in_array($order->get_status(), ['processing','completed'], true)) {
      return '<p>This unlock page is available after payment.</p>';
    }

    $code = $order->get_meta('sf_code');
    if (!$code) {
      $this->generate_code_on_paid($order_id);
      $code = $order->get_meta('sf_code');
    }

    $selection = null;
    foreach ($order->get_items() as $item) {
      $raw = $item->get_meta('sf_selection');
      if ($raw) { $selection = json_decode($raw, true); break; }
    }
    if (!$selection) return '<p>Selection not found on order.</p>';

    $go_url = home_url(trailingslashit(ltrim($this->merch_forge_page_url, '/')));
    $go_url = add_query_arg([
      'art'  => $selection['image_url'],
      'size' => '9oz',
      'w'    => 900,
      'h'    => 700,
    ], $go_url);

    ob_start(); ?>
    <style>
      .smu-wrap{max-width:980px;margin:0 auto;padding:28px 16px;font-family:system-ui,-apple-system,Segoe UI,Roboto;background:#050505;color:#fff}
      .smu-box{background:#0f0f12;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:16px;margin-top:14px}
      .smu-row{display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start}
      .smu-img{width:240px;height:240px;border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,.1)}
      .smu-img img{width:100%;height:100%;object-fit:cover}
      .smu-btn{display:inline-block;background:linear-gradient(90deg,#ff4fd8,#00d4ff);color:#050505;
        padding:14px 18px;border-radius:999px;text-decoration:none;font-weight:900}
      .smu-note{opacity:.75;font-size:13px;line-height:1.35}
      .smu-code{font-weight:900;font-size:16px;letter-spacing:1px}
    </style>

    <div class="smu-wrap">
      <h1 style="margin:0">Merch Unlocked ✅</h1>
      <div class="smu-note" style="margin-top:6px">This page confirms the design + generates your unlock code.</div>

      <div class="smu-box">
        <div class="smu-row">
          <div class="smu-img"><img src="<?php echo esc_url($selection['image_url']); ?>" alt=""></div>
          <div style="flex:1;min-width:260px">
            <div style="font-weight:900;font-size:18px">Order #<?php echo esc_html($order_id); ?></div>
            <div class="smu-note">Persona: <strong><?php echo esc_html($selection['persona']); ?></strong></div>
            <div class="smu-note">Product Key: <strong><?php echo esc_html($selection['product_key']); ?></strong></div>
            <div style="margin-top:10px">Unlock Code:</div>
            <div class="smu-code"><?php echo esc_html($code); ?></div>

            <div style="margin-top:14px">
              <a class="smu-btn" href="<?php echo esc_url($go_url); ?>">Go to Merch Generator</a>
            </div>

            <div class="smu-note" style="margin-top:10px">Next step is your generator page where you’ll create mockups and export print files for your vendor.</div>
          </div>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
  }

  private function parse_images_attr($raw) {
    $out = [];
    $pairs = array_filter(array_map('trim', explode(',', $raw)));
    foreach ($pairs as $pair) {
      $bits = array_map('trim', explode('|', $pair, 2));
      if (count($bits) !== 2) continue;
      $id = preg_replace('/[^A-Za-z0-9_\-]/', '', $bits[0]);
      $url = esc_url_raw($bits[1]);
      if ($id && $url) $out[$id] = $url;
    }
    return $out;
  }

  public function shortcode_label_forge_v2() {
    ob_start(); ?>
    <div class="sensi-forge">
      <div class="sensi-forge-card">
        <h2>🕯️ Label Forge</h2>
        <p class="sub">You picked the art. Now we forge the label text + brand. No AI roulette.</p>

        <div class="row">
          <div class="col">
            <label>Tagline</label>
            <input id="tagline" type="text" value="Ignite Your Essence" maxlength="60">
          </div>
          <div class="col">
            <label>Scent</label>
            <input id="scent" type="text" value="Vanilla Vice" maxlength="40">
          </div>
        </div>

        <label>Personal Message (optional)</label>
        <input id="message" type="text" placeholder="Happy Birthday Luke, my Superhero 💖" maxlength="60">

        <div class="row">
          <div class="col">
            <label>Branding</label>
            <select id="brand">
              <option value="SENSI CANDLE CO.">SENSI CANDLE CO.</option>
              <option value="Sensi Superhero Collection">Sensi Superhero Collection</option>
              <option value="Sensi / Uniq">Sensi / Uniq</option>
            </select>
          </div>
          <div class="col">
            <label>Text Style</label>
            <select id="style">
              <option value="clean">Clean</option>
              <option value="neon">Neon</option>
              <option value="rose">Rose Gold</option>
            </select>
          </div>
        </div>

        <button id="forgeBtn">Forge My Label (Flat File)</button>

        <div class="out">
          <div class="meta" id="meta"></div>
          <canvas id="c" style="display:none;"></canvas>
          <img id="preview" alt="Label preview" />
          <a id="download" class="dl" download="sensi-label.png" style="display:none;">Download Flat PNG</a>
        </div>
      </div>
    </div>

    <style>
      .sensi-forge{
        padding:70px 18px;
        background: radial-gradient(circle at top, #150813 0%, #07040a 60%, #000 100%);
        color:#fff;
        font-family:Poppins, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      }
      .sensi-forge-card{
        max-width:860px; margin:0 auto;
        background:rgba(255,255,255,.04);
        border:1px solid rgba(255,182,193,.18);
        border-radius:18px;
        padding:22px;
        box-shadow:0 0 34px rgba(255,102,204,.10);
      }
      .sensi-forge-card h2{margin:0 0 6px; font-size:34px; text-shadow:0 0 18px rgba(255,102,204,.35);}
      .sub{opacity:.85; margin:0 0 18px;}
      label{display:block; margin:12px 0 6px; font-weight:800; opacity:.92;}
      input, select{
        width:100%;
        padding:11px 12px;
        border-radius:12px;
        border:1px solid rgba(255,255,255,.14);
        background:#0d0710;
        color:#fff;
        outline:none;
      }
      .row{display:flex; gap:12px; flex-wrap:wrap;}
      .col{flex:1; min-width:220px;}
      #forgeBtn{
        margin-top:16px;
        width:100%;
        padding:14px 18px;
        border:0;
        border-radius:999px;
        font-weight:900;
        background: linear-gradient(90deg, #ffb6c1, #ff66cc, #b76e79);
        color:#120010;
        cursor:pointer;
      }
      .out{margin-top:18px;}
      .meta{opacity:.85; font-size:14px; margin-bottom:10px;}
      #preview{
        width:100%;
        max-width:720px;
        border-radius:16px;
        border:1px solid rgba(255,255,255,.10);
        box-shadow:0 0 28px rgba(255,102,204,.12);
        display:block;
      }
      .dl{
        display:inline-block;
        margin-top:12px;
        padding:10px 18px;
        border-radius:999px;
        border:1px solid rgba(255,255,255,.18);
        color:#fff; text-decoration:none;
      }
    </style>

    <script>
      (function(){
        const qs = new URLSearchParams(window.location.search);
        const art = qs.get('art');
        const size = qs.get('size') || '9oz';
        const W = parseInt(qs.get('w') || '900', 10);
        const H = parseInt(qs.get('h') || '700', 10);

        const meta = document.getElementById('meta');
        const btn = document.getElementById('forgeBtn');
        const canvas = document.getElementById('c');
        const ctx = canvas.getContext('2d');
        const preview = document.getElementById('preview');
        const dl = document.getElementById('download');

        if(!art){
          meta.innerText = "Missing art. Go back and select an image first.";
          btn.disabled = true;
          btn.style.opacity = .6;
          return;
        }

        meta.innerText = `Selected size: ${size} | Output: ${W}×${H}px`;

        function drawBadge(style){
          ctx.save();
          const plateH = Math.round(H * 0.22);
          const y = H - plateH;

          ctx.globalAlpha = 0.92;
          ctx.fillStyle = "rgba(0,0,0,0.55)";
          ctx.fillRect(0, y, W, plateH);

          ctx.globalAlpha = 1;
          if(style === 'neon'){
            ctx.strokeStyle = "rgba(255,102,204,0.95)";
            ctx.lineWidth = Math.max(4, Math.round(W * 0.01));
            ctx.shadowColor = "rgba(255,102,204,0.9)";
            ctx.shadowBlur = Math.round(W * 0.04);
          } else if(style === 'rose'){
            ctx.strokeStyle = "rgba(183,110,121,0.95)";
            ctx.lineWidth = Math.max(4, Math.round(W * 0.01));
            ctx.shadowColor = "rgba(255,182,193,0.55)";
            ctx.shadowBlur = Math.round(W * 0.03);
          } else {
            ctx.strokeStyle = "rgba(255,255,255,0.22)";
            ctx.lineWidth = Math.max(3, Math.round(W * 0.008));
            ctx.shadowBlur = 0;
          }
          ctx.strokeRect(Math.round(W*0.03), y + Math.round(plateH*0.12), Math.round(W*0.94), Math.round(plateH*0.76));
          ctx.restore();
        }

        function drawText(tagline, scent, message, brand, style){
          ctx.save();

          const plateH = Math.round(H * 0.22);
          const baseY = H - plateH;

          const big = Math.max(28, Math.round(W * 0.06));
          const med = Math.max(18, Math.round(W * 0.035));
          const small = Math.max(14, Math.round(W * 0.026));

          let color = "#ffffff";
          if(style === "neon") color = "#ff66cc";
          if(style === "rose") color = "#ffd1dc";

          ctx.fillStyle = color;
          ctx.textAlign = "center";

          ctx.font = `800 ${big}px Poppins, Arial`;
          ctx.shadowColor = "rgba(0,0,0,0.85)";
          ctx.shadowBlur = 10;
          ctx.fillText(tagline, W/2, baseY + Math.round(plateH*0.42));

          ctx.font = `700 ${med}px Poppins, Arial`;
          ctx.shadowBlur = 8;
          ctx.fillText(scent, W/2, baseY + Math.round(plateH*0.70));

          ctx.font = `700 ${small}px Poppins, Arial`;
          ctx.shadowBlur = 6;

          const bottomLine = message ? `${brand} • ${message}` : brand;
          ctx.fillStyle = "rgba(255,255,255,0.85)";
          if(style === "neon") ctx.fillStyle = "rgba(255,182,193,0.9)";
          if(style === "rose") ctx.fillStyle = "rgba(255,209,220,0.95)";
          ctx.fillText(bottomLine, W/2, baseY + Math.round(plateH*0.90));

          ctx.restore();
        }

        btn.addEventListener('click', () => {
          const tagline = document.getElementById('tagline').value.trim() || "Ignite Your Essence";
          const scent = document.getElementById('scent').value.trim() || "Vanilla Vice";
          const message = document.getElementById('message').value.trim();
          const brand = document.getElementById('brand').value;
          const style = document.getElementById('style').value;

          canvas.width = W;
          canvas.height = H;

          const img = new Image();
          img.crossOrigin = "anonymous";
          img.src = art;

          img.onload = () => {
            const ir = img.width / img.height;
            const cr = W / H;
            let dw, dh, dx, dy;
            if(ir > cr){
              dh = H; dw = H * ir; dx = (W - dw)/2; dy = 0;
            } else {
              dw = W; dh = W / ir; dx = 0; dy = (H - dh)/2;
            }
            ctx.drawImage(img, dx, dy, dw, dh);

            drawBadge(style);
            drawText(tagline, scent, message, brand, style);

            const png = canvas.toDataURL("image/png");
            preview.src = png;
            dl.href = png;
            dl.style.display = "inline-block";
          };

          img.onerror = () => {
            meta.innerText = "Couldn’t load the selected art image (CORS / blocked URL). Upload to your media library and use that URL.";
          };
        });
      })();
    </script>
    <?php
    return ob_get_clean();
  }
}

new Sensi_Merch_Forge_Plugin();
