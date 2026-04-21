<?php
// *************************************************************************
// * DEPRIXA PRO — Public Shipping Rate Calculator (no login required)
// *************************************************************************
require_once("loader.php");
require_once("helpers/querys.php");
$core = new Core();
$site = htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8');
$logo_html = $core->logo_web
    ? '<img src="assets/'.htmlspecialchars((string)$core->logo_web,ENT_QUOTES,'UTF-8').'" alt="'.$site.'" style="height:38px;width:auto">'
    : '<span class="fw-bold fs-5">'.$site.'</span>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo isset($lang['cotizador_title'])?$lang['cotizador_title']:'Shipping Rate Calculator'; ?> | <?php echo $site; ?></title>
<link rel="icon" type="image/png" href="assets/<?php echo $core->favicon; ?>">

<!-- Bootstrap 4 (local) -->
<link href="assets/css_main_deprixa/css/bootstrap.min.css" rel="stylesheet">
<!-- MDI Icons (local — always works) -->
<link href="assets/css_main_deprixa/css/materialdesignicons.min.css" rel="stylesheet">
<!-- Select2 -->
<link href="assets/select2/dist/css/select2.min.css" rel="stylesheet">

<style>
:root{
  --brand:#1e3c72;
  --brand2:#2a5298;
  --accent:#e63946;
  --radius:12px;
}
*{box-sizing:border-box}
body{background:#f4f6fb;font-family:'Segoe UI',system-ui,sans-serif;font-size:15px}

/* ── NAVBAR ─────────────────────────────────────────── */
.pub-nav{
  background:#fff;
  box-shadow:0 2px 12px rgba(0,0,0,.08);
  position:sticky;top:0;z-index:1030;
  padding:0 0;
}
.pub-nav .container{height:62px;display:flex;align-items:center;justify-content:space-between}
.pub-nav .nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.pub-nav .nav-links{display:flex;align-items:center;gap:4px}
.pub-nav .nav-link-item{
  display:flex;align-items:center;gap:6px;
  padding:7px 14px;border-radius:8px;
  color:#444;font-weight:500;font-size:.88rem;
  text-decoration:none;transition:background .15s,color .15s;
}
.pub-nav .nav-link-item:hover{background:#f0f4ff;color:var(--brand);text-decoration:none}
.pub-nav .nav-link-item.active{background:#f0f4ff;color:var(--brand);font-weight:600}
.pub-nav .nav-link-item i{font-size:17px;color:var(--brand2)}
.pub-nav .btn-home{
  background:linear-gradient(135deg,var(--brand),var(--brand2));
  color:#fff;border-radius:8px;padding:8px 18px;
  font-weight:600;font-size:.88rem;text-decoration:none;
  display:flex;align-items:center;gap:6px;
  transition:opacity .15s,transform .1s;
}
.pub-nav .btn-home:hover{opacity:.9;transform:translateY(-1px);color:#fff;text-decoration:none}
.pub-nav .btn-home i{font-size:16px}

/* ── HERO ───────────────────────────────────────────── */
.hero{
  background:linear-gradient(135deg,#0d1f4e 0%,var(--brand) 55%,var(--brand2) 100%);
  padding:60px 0 50px;color:#fff;position:relative;overflow:hidden;
}
.hero::before{
  content:'';position:absolute;top:-100px;right:-100px;
  width:380px;height:380px;border-radius:50%;
  background:rgba(255,255,255,.04);pointer-events:none;
}
.hero::after{
  content:'';position:absolute;bottom:-80px;left:-80px;
  width:300px;height:300px;border-radius:50%;
  background:rgba(255,255,255,.03);pointer-events:none;
}
.hero-icon{
  width:72px;height:72px;border-radius:18px;
  background:rgba(255,255,255,.12);
  backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,.2);
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 20px;
}
.hero-icon i{font-size:36px;color:#fff}
.hero h1{font-size:2rem;font-weight:800;margin-bottom:10px;
  text-shadow:0 2px 8px rgba(0,0,0,.2)}
.hero p{font-size:1rem;opacity:.85;margin-bottom:20px}
.hero-badge{
  display:inline-flex;align-items:center;gap:6px;
  background:rgba(255,255,255,.15);backdrop-filter:blur(4px);
  border:1px solid rgba(255,255,255,.25);
  border-radius:30px;padding:5px 16px;font-size:.82rem;color:#fff;
}
.hero-badge i{font-size:14px;color:#4cff91}

/* ── MAIN CARD AREA ─────────────────────────────────── */
.quote-wrap{padding:40px 0 60px}
.card-quote{
  background:#fff;border-radius:var(--radius);
  box-shadow:0 4px 24px rgba(0,0,0,.07);
  border:none;overflow:hidden;
}

/* ── SECTION HEADERS ────────────────────────────────── */
.section-head{
  display:flex;align-items:center;gap:8px;
  font-size:.72rem;font-weight:700;color:var(--brand);
  text-transform:uppercase;letter-spacing:.9px;
  margin-bottom:20px;
}
.section-head::after{
  content:'';flex:1;height:1px;background:#e9ecef;margin-left:4px;
}
.section-head i{font-size:17px}

/* ── FORM CONTROLS ──────────────────────────────────── */
.form-label{font-weight:600;font-size:.83rem;color:#374151;margin-bottom:5px}
.form-label .req{color:var(--accent)}
.form-control,.select2-container--default .select2-selection--single{
  border-radius:8px !important;border-color:#d1d5db;
  font-size:.9rem;height:42px !important;
}
.form-control:focus{border-color:var(--brand2);box-shadow:0 0 0 3px rgba(42,82,152,.12)}
.select2-container--default .select2-selection--single .select2-selection__rendered{
  line-height:42px !important;padding-left:12px !important;color:#374151;
}
.select2-container--default .select2-selection--single .select2-selection__arrow{
  height:42px !important;
}
.select2-container--default .select2-selection--single.select2-selection--disabled{
  background:#f8f9fa !important;
}
.input-group-text{border-radius:0 8px 8px 0 !important;background:#f3f4f6;
  border-color:#d1d5db;color:#6b7280;font-size:.85rem;font-weight:600}
.dim-sep{
  display:flex;align-items:center;justify-content:center;
  font-size:1.1rem;color:#9ca3af;padding-top:26px;
}
.vol-hint{
  background:#f0f4ff;border-left:3px solid var(--brand2);
  border-radius:0 8px 8px 0;padding:8px 12px;
  font-size:.8rem;color:#4b5563;margin-top:2px;
}
.vol-hint i{color:var(--brand2);font-size:14px}

/* ── SUBMIT BUTTON ──────────────────────────────────── */
.btn-calc{
  background:linear-gradient(135deg,var(--brand),var(--brand2));
  border:none;border-radius:10px;color:#fff;
  font-weight:700;font-size:1rem;padding:13px 30px;
  width:100%;transition:opacity .2s,transform .1s;
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.btn-calc:hover{opacity:.9;transform:translateY(-1px);color:#fff}
.btn-calc:active{transform:translateY(0)}
.btn-calc i{font-size:20px}

/* ── ERROR ALERT ─────────────────────────────────────── */
#quote-error{
  display:none;border-radius:8px;font-size:.875rem;
  border-left:4px solid #dc3545;
}

/* ── PLACEHOLDER PANEL ──────────────────────────────── */
.placeholder-panel{
  text-align:center;padding:40px 24px;
}
.placeholder-icon-wrap{
  width:80px;height:80px;border-radius:20px;
  background:linear-gradient(135deg,#e8eeff,#d0d9ff);
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 20px;
}
.placeholder-icon-wrap i{font-size:40px;color:var(--brand)}
.placeholder-panel h5{color:var(--brand);font-weight:700;margin-bottom:8px}
.placeholder-panel p{color:#6b7280;font-size:.88rem;margin-bottom:28px}
.feature-pills{display:flex;justify-content:center;gap:10px;flex-wrap:wrap}
.feature-pill{
  display:flex;align-items:center;gap:5px;
  background:#f8faff;border:1px solid #dde5ff;
  border-radius:20px;padding:6px 14px;
  font-size:.8rem;font-weight:600;color:#4b5563;
}
.feature-pill i{font-size:15px;color:var(--brand2)}

/* ── RESULT PANEL ────────────────────────────────────── */
.result-panel{display:none}
.result-header{
  background:linear-gradient(135deg,var(--brand),var(--brand2));
  border-radius:var(--radius) var(--radius) 0 0;
  padding:28px 24px 24px;text-align:center;color:#fff;
}
.result-label-sm{font-size:.75rem;opacity:.75;text-transform:uppercase;
  letter-spacing:.8px;margin-bottom:6px}
.result-price-main{font-size:3rem;font-weight:800;line-height:1;margin-bottom:6px}
.result-price-main sup{font-size:1.4rem;vertical-align:top;margin-top:.5rem;margin-right:2px}
.result-service-badge{
  display:inline-flex;align-items:center;gap:5px;
  background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);
  border-radius:20px;padding:4px 14px;font-size:.8rem;margin-top:6px;
}
.result-body{padding:20px 24px 24px}
.stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px}
.stat-box{
  background:#f8faff;border:1px solid #e8eeff;border-radius:10px;
  padding:12px 8px;text-align:center;
}
.stat-box .stat-val{font-size:1.15rem;font-weight:700;color:var(--brand);line-height:1.2}
.stat-box .stat-lbl{font-size:.72rem;color:#6b7280;margin-top:3px}
.result-detail-row{
  background:#f9fafb;border-radius:8px;padding:8px 14px;
  font-size:.8rem;color:#6b7280;display:flex;justify-content:space-between;
  margin-bottom:16px;
}
.result-detail-row span{font-weight:600;color:#374151}
.result-disclaimer{
  background:#fffbeb;border:1px solid #fde68a;border-radius:8px;
  padding:9px 13px;font-size:.78rem;color:#92400e;
  display:flex;align-items:flex-start;gap:7px;margin-bottom:18px;
}
.result-disclaimer i{font-size:15px;color:#d97706;flex-shrink:0;margin-top:1px}
.btn-cta{
  background:linear-gradient(135deg,#059669,#10b981);
  border:none;border-radius:10px;color:#fff;font-weight:700;
  font-size:.92rem;padding:12px;width:100%;
  display:flex;align-items:center;justify-content:center;gap:7px;
  text-decoration:none;transition:opacity .15s,transform .1s;margin-bottom:10px;
}
.btn-cta:hover{opacity:.9;transform:translateY(-1px);color:#fff;text-decoration:none}
.btn-reset{
  background:#f3f4f6;border:1px solid #e5e7eb;border-radius:10px;color:#374151;
  font-weight:600;font-size:.88rem;padding:10px;width:100%;
  display:flex;align-items:center;justify-content:center;gap:6px;
  cursor:pointer;transition:background .15s;
}
.btn-reset:hover{background:#e9ecef}

/* ── STICKY SIDE PANEL ───────────────────────────────── */
@media(min-width:992px){
  .side-sticky{position:sticky;top:82px}
}

/* ── FOOTER ──────────────────────────────────────────── */
.pub-footer{
  background:#fff;border-top:1px solid #e9ecef;
  padding:18px 0;text-align:center;font-size:.82rem;color:#9ca3af;
}
.pub-footer a{color:#6b7280;text-decoration:none}
.pub-footer a:hover{color:var(--brand)}
</style>
</head>
<body>

<!-- ═══════════ NAVBAR ═══════════ -->
<nav class="pub-nav">
  <div class="container">
    <a href="index.php" class="nav-logo">
      <?php echo $logo_html; ?>
    </a>

    <div class="nav-links">
      <a href="tracking.php" class="nav-link-item">
        <i class="mdi mdi-map-marker-path"></i>
        <span>Track &amp; Trace</span>
      </a>
      <a href="cotizar.php" class="nav-link-item active">
        <i class="mdi mdi-calculator-variant"></i>
        <span>Get a Quote</span>
      </a>
      <a href="index.php" class="btn-home ml-2">
        <i class="mdi mdi-home-outline"></i>
        <span>Login</span>
      </a>
    </div>
  </div>
</nav>

<!-- ═══════════ HERO ═══════════ -->
<section class="hero">
  <div class="container text-center position-relative" style="z-index:1">
    <div class="hero-icon">
      <i class="mdi mdi-truck-fast"></i>
    </div>
    <h1><?php echo isset($lang['cotizador_h1'])?$lang['cotizador_h1']:'Shipping Rate Calculator'; ?></h1>
    <p><?php echo isset($lang['cotizador_sub'])?$lang['cotizador_sub']:'Get an instant estimate for your shipment — no account required.'; ?></p>
    <span class="hero-badge">
      <i class="mdi mdi-check-circle"></i>
      Free &amp; No Account Required
    </span>
  </div>
</section>

<!-- ═══════════ MAIN ═══════════ -->
<div class="quote-wrap">
  <div class="container">
    <div class="row">

      <!-- ── LEFT: Form ── -->
      <div class="col-lg-7 mb-4 mb-lg-0">
        <div class="card-quote p-4">
          <form id="quote-form" novalidate>

            <!-- ROUTE -->
            <div class="section-head">
              <i class="mdi mdi-map-marker-distance"></i>
              Route
            </div>
            <div class="row">
              <div class="col-md-6 form-group">
                <label class="form-label">Origin Country <span class="req">*</span></label>
                <select id="origin_country" name="origin_country"
                        class="form-control select2-pub" data-placeholder="Select country…" required></select>
              </div>
              <div class="col-md-6 form-group">
                <label class="form-label">Destination Country <span class="req">*</span></label>
                <select id="dest_country" name="dest_country"
                        class="form-control select2-pub" data-placeholder="Select country…" required></select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 form-group">
                <label class="form-label">Destination State</label>
                <select id="dest_state" name="dest_state"
                        class="form-control select2-state" data-placeholder="Select state…" disabled></select>
              </div>
              <div class="col-md-6 form-group">
                <label class="form-label">Destination City</label>
                <select id="dest_city" name="dest_city"
                        class="form-control select2-city" data-placeholder="Select city…" disabled></select>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Shipping Service</label>
              <select id="service_mode" name="service_mode"
                      class="form-control select2-svc" data-placeholder="Any service…"></select>
            </div>

            <!-- PACKAGE -->
            <div class="section-head mt-2">
              <i class="mdi mdi-package-variant-closed"></i>
              Package Details
            </div>
            <div class="row">
              <div class="col-md-5 form-group">
                <label class="form-label">Weight <span class="req">*</span></label>
                <div class="input-group">
                  <input type="number" id="weight" name="weight"
                         class="form-control" min="0.01" step="0.01"
                         placeholder="0.00" required style="border-radius:8px 0 0 8px !important">
                  <div class="input-group-append">
                    <span class="input-group-text">lbs</span>
                  </div>
                </div>
              </div>
              <div class="col-md-3 form-group">
                <label class="form-label">Pieces</label>
                <input type="number" id="qty" name="qty"
                       class="form-control" min="1" step="1" value="1">
              </div>
            </div>

            <div class="vol-hint mb-3">
              <i class="mdi mdi-information-outline"></i>
              Optional: enter dimensions for air services (volumetric weight calculation)
            </div>
            <div class="row align-items-end">
              <div class="col form-group mb-0">
                <label class="form-label">Length (in)</label>
                <input type="number" id="length" name="length" class="form-control" min="0" step="0.01" placeholder="0">
              </div>
              <div class="dim-sep">×</div>
              <div class="col form-group mb-0">
                <label class="form-label">Width (in)</label>
                <input type="number" id="width" name="width" class="form-control" min="0" step="0.01" placeholder="0">
              </div>
              <div class="dim-sep">×</div>
              <div class="col form-group mb-0">
                <label class="form-label">Height (in)</label>
                <input type="number" id="height" name="height" class="form-control" min="0" step="0.01" placeholder="0">
              </div>
            </div>

            <!-- Error -->
            <div id="quote-error" class="alert alert-danger mt-3"></div>

            <!-- Submit -->
            <div class="mt-4">
              <button type="submit" class="btn-calc" id="btn-calc">
                <span id="calc-text"><i class="mdi mdi-calculator-variant"></i> Calculate Shipping Rate</span>
                <span id="calc-spin" style="display:none">
                  <span class="spinner-border spinner-border-sm" role="status"></span>
                  Calculating…
                </span>
              </button>
            </div>

          </form>
        </div>
      </div>
      <!-- / LEFT -->

      <!-- ── RIGHT: Result ── -->
      <div class="col-lg-5">
        <div class="side-sticky">

          <!-- Placeholder (shown before quote) -->
          <div id="placeholder-card" class="card-quote">
            <div class="placeholder-panel">
              <div class="placeholder-icon-wrap">
                <i class="mdi mdi-truck-delivery-outline"></i>
              </div>
              <h5>Fill in the form</h5>
              <p>Complete the shipping details on the left<br>and click <strong>Calculate</strong> to see your instant rate estimate.</p>
              <div class="feature-pills">
                <div class="feature-pill">
                  <i class="mdi mdi-lightning-bolt"></i> Instant
                </div>
                <div class="feature-pill">
                  <i class="mdi mdi-lock-open-outline"></i> No Login
                </div>
                <div class="feature-pill">
                  <i class="mdi mdi-tag-outline"></i> Free
                </div>
              </div>
            </div>
          </div>

          <!-- Result (shown after quote) -->
          <div id="result-card" class="card-quote result-panel">
            <div class="result-header">
              <div class="result-label-sm">Estimated Total</div>
              <div class="result-price-main">
                <sup id="r-sym">$</sup><span id="r-total">0.00</span>
              </div>
              <div class="result-service-badge">
                <i class="mdi mdi-truck-fast-outline"></i>
                <span id="r-service">Standard</span>
              </div>
            </div>
            <div class="result-body">
              <div class="stat-grid">
                <div class="stat-box">
                  <div class="stat-val" id="r-perlb">—</div>
                  <div class="stat-lbl">Price / lb</div>
                </div>
                <div class="stat-box">
                  <div class="stat-val" id="r-weight">—</div>
                  <div class="stat-lbl">Chargeable lbs</div>
                </div>
                <div class="stat-box">
                  <div class="stat-val" id="r-real">—</div>
                  <div class="stat-lbl">Real Weight</div>
                </div>
              </div>

              <div id="r-vol-row" class="result-detail-row" style="display:none">
                <span>Volumetric weight:</span>
                <span id="r-vol">—</span>
              </div>

              <div class="result-disclaimer">
                <i class="mdi mdi-information-outline"></i>
                Estimated price only. Final charges may vary based on actual weight and dimensions verified on arrival.
              </div>

              <a href="sign-up.php" class="btn-cta">
                <i class="mdi mdi-account-plus-outline"></i>
                Create Free Account &amp; Ship
              </a>
              <button type="button" class="btn-reset" id="btn-reset">
                <i class="mdi mdi-refresh"></i> New Quote
              </button>
            </div>
          </div>

        </div>
      </div>
      <!-- / RIGHT -->

    </div>
  </div>
</div>

<!-- ═══════════ FOOTER ═══════════ -->
<footer class="pub-footer">
  <div class="container">
    <span>&copy; <?php echo date('Y'); ?> <?php echo $site; ?></span>
    &nbsp;·&nbsp;
    <a href="tracking.php"><i class="mdi mdi-map-marker-path"></i> Track Shipment</a>
    &nbsp;·&nbsp;
    <a href="index.php"><i class="mdi mdi-login"></i> Login</a>
  </div>
</footer>

<!-- Scripts -->
<script src="assets/custom_dependencies/jquery-3.6.0.min.js"></script>
<script src="assets/select2/dist/js/select2.min.js"></script>
<script>
(function($){
"use strict";

// ── Select2 helpers ──────────────────────────────────
function s2(sel, ajaxUrl, extraData){
  $(sel).select2({
    placeholder: $(sel).data('placeholder') || 'Select…',
    allowClear: true,
    width: '100%',
    ajax: {
      url: ajaxUrl,
      dataType: 'json',
      delay: 250,
      data: function(p){ return $.extend({q: p.term||''}, extraData ? extraData() : {}); },
      processResults: function(d){ return {results: d}; }
    }
  });
}

// Countries (both)
s2('#origin_country', 'ajax/select2_countries.php');
s2('#dest_country',   'ajax/select2_countries.php');

// Cascading: country → state
$('#dest_country').on('change', function(){
  var cid = $(this).val();
  $('#dest_state').val(null).trigger('change').prop('disabled', true).empty();
  $('#dest_city').val(null).trigger('change').prop('disabled', true).empty();
  if(!cid) return;
  s2('#dest_state', 'ajax/select2_states.php', function(){ return {id: cid}; });
  $('#dest_state').prop('disabled', false);
});

// state → city
$('#dest_state').on('change', function(){
  var sid = $(this).val();
  $('#dest_city').val(null).trigger('change').prop('disabled', true).empty();
  if(!sid) return;
  s2('#dest_city', 'ajax/select2_cities.php', function(){ return {id: sid}; });
  $('#dest_city').prop('disabled', false);
});

// Service
s2('#service_mode', 'ajax/select2_shipping_mode.php');

// ── Form submit ──────────────────────────────────────
$('#quote-form').on('submit', function(e){
  e.preventDefault();
  var orig = $('#origin_country').val();
  var dest = $('#dest_country').val();
  var w    = parseFloat($('#weight').val());

  $('#quote-error').hide().text('');

  if(!orig || !dest){
    showErr('Please select origin and destination countries.');
    return;
  }
  if(!w || w <= 0){
    showErr('Please enter a valid weight greater than 0.');
    return;
  }

  $('#calc-text').hide();
  $('#calc-spin').show();
  $('#btn-calc').prop('disabled', true);

  $.ajax({
    url: 'ajax/public_quote_ajax.php',
    type: 'POST',
    dataType: 'json',
    data: {
      origin_country: orig,
      origin_state:   0,
      origin_city:    0,
      dest_country:   dest,
      dest_state:     $('#dest_state').val()  || 0,
      dest_city:      $('#dest_city').val()   || 0,
      weight:         w,
      qty:            parseInt($('#qty').val()) || 1,
      length:         parseFloat($('#length').val()) || 0,
      width:          parseFloat($('#width').val())  || 0,
      height:         parseFloat($('#height').val()) || 0,
      service_mode:   $('#service_mode').val() || 0
    },
    success: function(res){
      resetBtn();
      if(res.success){
        showResult(res);
      } else {
        showErr(res.message || 'No tariff found for this route and weight.');
      }
    },
    error: function(){
      resetBtn();
      showErr('Server error. Please try again later.');
    }
  });
});

function resetBtn(){
  $('#calc-text').show();
  $('#calc-spin').hide();
  $('#btn-calc').prop('disabled', false);
}

function showErr(msg){
  $('#quote-error').text(msg).fadeIn(200);
  $('html,body').animate({scrollTop: $('#quote-error').offset().top - 100}, 300);
}

function showResult(res){
  var sym = res.symbol || '$';
  var dec = parseInt(res.decimals) || 2;
  var fmt = function(v){ return parseFloat(v).toFixed(dec); };

  $('#r-sym').text(sym);
  $('#r-total').text(fmt(res.total));
  $('#r-perlb').text(sym + fmt(res.price_per_lb));
  $('#r-weight').text(fmt(res.chargeable_weight) + ' lb');
  $('#r-real').text(fmt(res.weight_real) + ' lb');
  $('#r-service').text(res.service || 'Standard');

  if(res.is_air && parseFloat(res.weight_vol) > 0){
    $('#r-vol').text(fmt(res.weight_vol) + ' lb');
    $('#r-vol-row').show();
  } else {
    $('#r-vol-row').hide();
  }

  $('#placeholder-card').fadeOut(200, function(){
    $('#result-card').fadeIn(300);
  });

  if($(window).width() < 992){
    $('html,body').animate({scrollTop: $('#result-card').offset().top - 20}, 400);
  }
}

// ── Reset ────────────────────────────────────────────
$('#btn-reset').on('click', function(){
  // Clear all selects
  ['#origin_country','#dest_country','#dest_state','#dest_city','#service_mode'].forEach(function(s){
    $(s).val(null).trigger('change');
  });
  $('#dest_state, #dest_city').prop('disabled', true);
  // Clear inputs
  $('#weight, #qty, #length, #width, #height').val('');
  $('#qty').val(1);
  $('#quote-error').hide();
  // Swap panels
  $('#result-card').fadeOut(200, function(){
    $('#placeholder-card').fadeIn(300);
  });
});

}(jQuery));
</script>
</body>
</html>
