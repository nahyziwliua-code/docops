<?php
// 1) Koneksi ke database
$host     = 'localhost';
$dbname   = 'spektra';
$username = 'root';
$password = '';
try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}
// 2) Endpoint AJAX untuk Select2 fasilitas
if (isset($_GET['ajax']) && $_GET['ajax'] === 'facilities') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $q        = $_GET['q']        ?? '';
    $area     = $_GET['area']     ?? '';
    $division = $_GET['division'] ?? '';
    $conditions = [];
    $params     = [];
    if ($area !== '') {
        $conditions[]    = "area = :area";
        $params[':area'] = $area;
    }
    if ($division !== '') {
        $conditions[]         = "tim = :division";
        $params[':division']  = $division;
    }
    if ($q !== '') {
        $conditions[]    = "nama_fasilitas LIKE :q";
        $params[':q']    = "%{$q}%";
    }
    $sql = "SELECT DISTINCT nama_fasilitas AS text
            FROM fasilitas";
    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    $sql .= " ORDER BY nama_fasilitas
              LIMIT 50";
    $stmt     = $pdo->prepare($sql);
    $stmt->execute($params);
    $results  = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
    exit;
}
// 3) Endpoint AJAX untuk menghitung counts kategori
if (isset($_GET['ajax']) && $_GET['ajax'] === 'counts') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $area     = $_GET['area']     ?? '';
    $division = $_GET['division'] ?? '';
    $search   = $_GET['search']   ?? '';
    $conds  = [];
    $params = [];
    if ($area !== '') {
        $conds[]         = "f.area = :area";
        $params[':area'] = $area;
    }
    if ($division !== '') {
        $conds[]            = "f.tim = :division";
        $params[':division']= $division;
    }
    if ($search !== '') {
        $conds[]            = "f.nama_fasilitas = :search";
        $params[':search']  = $search;
    }
    // Hitung GS (PON, POS)
    $sqlGS = "SELECT COUNT(*) FROM fasilitas f WHERE f.tim IN ('PON','POS','POHO')";
    if (count($conds) > 0) {
        $sqlGS .= " AND " . implode(' AND ', $conds);
    }
    $stmtGS = $pdo->prepare($sqlGS);
    $stmtGS->execute($params);
    $countGS = (int) $stmtGS->fetchColumn();
    // Hitung Well Pad (FON, FOS)
    $sqlWP = "SELECT COUNT(*) FROM fasilitas f WHERE f.nama_fasilitas LIKE '%KV%'";
    if (count($conds) > 0) {
        $sqlWP .= " AND " . implode(' AND ', $conds);
    }
    $stmtWP = $pdo->prepare($sqlWP);
    $stmtWP->execute($params);
    $countWP = (int) $stmtWP->fetchColumn();
    // Hitung PG & T (PGT)
    $sqlPgt = "SELECT COUNT(*) FROM fasilitas f WHERE f.tim = 'PGT'";
    if (count($conds) > 0) {
        $sqlPgt .= " AND " . implode(' AND ', $conds);
    }
    $stmtPgt = $pdo->prepare($sqlPgt);
    $stmtPgt->execute($params);
    $countPgt = (int) $stmtPgt->fetchColumn();
    // Hitung Dokumen (join fasilitas ↔ dokumen)
    $sqlDocs = "SELECT COUNT(*) FROM dokumen d
                JOIN fasilitas f ON f.nama_fasilitas = d.fasilitas";
    if (count($conds) > 0) {
        $sqlDocs .= " WHERE " . implode(' AND ', $conds);
    }
    $stmtDocs = $pdo->prepare($sqlDocs);
    $stmtDocs->execute($params);
    $countDocs = (int) $stmtDocs->fetchColumn();
    echo json_encode([
        'gs'      => $countGS,
        'wellpad' => $countWP,
        'pgt'     => $countPgt,
        'docs'    => $countDocs
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
// 3.1) Endpoint AJAX untuk Select2 Divisi dinamis
if (isset($_GET['ajax']) && $_GET['ajax'] === 'divisions') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $area = $_GET['area'] ?? '';
    $sql    = "SELECT DISTINCT tim AS id, tim AS text
               FROM fasilitas";
    $params = [];
    if ($area !== '') {
        $sql         .= " WHERE area = :area";
        $params[':area'] = $area;
    }
    $sql   .= " ORDER BY tim";
    $stmt    = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
    exit;
}
// 4) Proses filter dari form biasa (POST)
$conditions = [];
$params     = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['area'])) {
        $conditions[]    = "area = :area";
        $params[':area'] = $_POST['area'];
    }
    if (!empty($_POST['division'])) {
        $conditions[]         = "tim = :division";
        $params[':division']  = $_POST['division'];
    }
    if (!empty($_POST['search'])) {
        $conditions[]       = "nama_fasilitas = :search";
        $params[':search']  = $_POST['search'];
    }
}
// 5) Build & jalankan query data fasilitas
$sql = "
    SELECT 
        id, 
        nama_fasilitas, 
        foto,
        area,
        tim
    FROM fasilitas
";
if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " ORDER BY nama_fasilitas";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
// 6) Hitung initial counts untuk tampil di page load (mengikuti filter area, division, search jika ada)
$filters      = [];
$paramsFilter = [];
if (!empty($_POST['area'])) {
    $filters[]             = "f.area = :area";
    $paramsFilter[':area'] = $_POST['area'];
}
if (!empty($_POST['division'])) {
    $filters[]                = "f.tim = :division";
    $paramsFilter[':division']= $_POST['division'];
}
if (!empty($_POST['search'])) {
    $filters[]               = "f.nama_fasilitas = :search";
    $paramsFilter[':search'] = $_POST['search'];
}
// GS
$sqlGS = "SELECT COUNT(*) FROM fasilitas f WHERE f.tim IN ('PON','POS','POHO')";
if (count($filters) > 0) {
    $sqlGS .= " AND " . implode(' AND ', $filters);
}
$stmtCount = $pdo->prepare($sqlGS);
$stmtCount->execute($paramsFilter);
$countGS = (int) $stmtCount->fetchColumn();
// Well Pad
$sqlWP = "SELECT COUNT(*) FROM fasilitas f WHERE f.tim IN ('FON','FOS')";
if (count($filters) > 0) {
    $sqlWP .= " AND " . implode(' AND ', $filters);
}
$stmtCount = $pdo->prepare($sqlWP);
$stmtCount->execute($paramsFilter);
$countWP = (int) $stmtCount->fetchColumn();
// PG & T
$sqlPgt = "SELECT COUNT(*) FROM fasilitas f WHERE f.tim = 'PGT'";
if (count($filters) > 0) {
    $sqlPgt .= " AND " . implode(' AND ', $filters);
}
$stmtCount = $pdo->prepare($sqlPgt);
$stmtCount->execute($paramsFilter);
$countPgt = (int) $stmtCount->fetchColumn();
// Dokumen
$sqlDocs = "SELECT COUNT(*) FROM dokumen d
            JOIN fasilitas f ON f.nama_fasilitas = d.fasilitas";
if (count($filters) > 0) {
    $sqlDocs .= " WHERE " . implode(' AND ', $filters);
}
$stmtCount = $pdo->prepare($sqlDocs);
$stmtCount->execute($paramsFilter);
$countDocs = (int) $stmtCount->fetchColumn();
// 7) Persiapkan subtitle dinamis
$subtitle = 'Semua Hasil';
if (!empty($_POST['area']) || !empty($_POST['division'])) {
    $parts = [];
    if (!empty($_POST['area'])) {
        $parts[] = ucfirst($_POST['area']);
    }
    if (!empty($_POST['division'])) {
        $parts[] = $_POST['division'];
    }
    $subtitle = implode(' | ', $parts);
}
// 8) Apakah sudah submit? untuk show/hide container
$showFacilities = ($_SERVER['REQUEST_METHOD'] === 'POST');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dokumen Fasilitas P&O</title>
  <!-- Favicons-->
  <link rel="shortcut icon" href="./img/pertamina_icon.png" type="image/x-icon">
  <!-- GOOGLE WEB FONT -->
<link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="./assets/fonts/fonts.css">
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">
  <link href="css/vendors.css" rel="stylesheet">
  <!-- YOUR CUSTOM CSS -->
  <link href="css/custom.css" rel="stylesheet">
  <link href="css/select2.min.css" rel="stylesheet" />
  <style>
   

</style>


</head>
<body>
  <!-- Loader -->
  <div id="loader">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
  </div>
  <div id="page">
    <header class="header menu_fixed">
      <div id="logo">
        <a href="#" title="Spektra">
          <img src="img/phrputih.png" width="165" height="35" alt="" class="logo_normal">
        <img src="/spektra/img/Pertamina_Logo.svg" width="165" height="35" alt="" class="logo_sticky">
        </a>
      </div>
      <a href="#menu" class="btn_mobile">
        <div class="hamburger hamburger--spin" id="hamburger">
          <div class="hamburger-box">
            <div class="hamburger-inner"></div>
          </div>
        </div>
      </a>
    </header>
    
    <!-- Modern & Elegant Hero Section -->
    <section
      id="hero-intro"
      class="hero_single version_5 fullscreen"
      style="<?= $showFacilities ? 'display:none;' : '' ?>"
    >
      <div class="decorative-elements"></div>
      <div class="wrapper w-100">
        <div class="container h-100">
          <div class="row justify-content-center align-items-center h-100">
            <div class="col-xl-6 col-lg-7">
              <div class="hero-content">
                <h3>Temukan Dokumen yang Anda Inginkan</h3>
                <p>Cari dan dapatkan dokumen yang Anda butuhkan</p>
                
                <div class="modern-btn-group">
                  <a href="#" id="btn-dokumen-fasilitas" class="modern-btn modern-btn-primary">
                    <i class="fas fa-file-alt"></i>
                    Dokumen Fasilitas P&O
                  </a>
                  <a href="quicklink-psi.html" class="modern-btn modern-btn-success">
                    <i class="fas fa-link"></i>
                    Dokumen PSI (Quicklink)
                  </a>
                  <!-- <a href="quicklink-technical-engineering.html" class="modern-btn modern-btn-success">
                    <i class="fas fa-cogs"></i>
                    Dokumen Engineering
                  </a> -->
                  <a href="quicklink-technical-engineering.html" class="modern-btn modern-btn-warning">
                    <i class="fas fa-cube"></i>
                    SPEKTRA 360 (Quicklink)
                  </a>
                </div>
              </div>
            </div>
            <div class="col-xl-5 col-lg-5 text-center d-none d-lg-block">
              <img src="img/pno.png" alt="Spektra" class="img-fluid img-animated">
            </div>
          </div>
        </div>
      </div>
    </section>
    
    <main id="main-content" class="pattern" style="<?= $showFacilities ? '' : 'display:none;' ?>">
      <section class="hero_single version_2">
        <div class="wrapper">
          <div class="container">
            <h3>Dokumen Fasilitas P&O</h3>
            <p>Sistem Pemetaan Komprehensif Dokumen Fasilitas Production & Operation</p>
            <form method="post" action="">
              <div class="row g-0 custom-search-input-2">
                <!-- 1. AREA -->
                <div class="col-lg-4">
                  <div class="form-group position-relative">
                    <select id="select-area" name="area" class="form-control select2" data-placeholder="Pilih Area">
                      <option></option>
                      <option value="north"   <?= (isset($_POST['area']) && $_POST['area']==='north')   ? 'selected' : '' ?>>North</option>
                      <option value="central" <?= (isset($_POST['area']) && $_POST['area']==='central') ? 'selected' : '' ?>>Central</option>
                      <option value="south"   <?= (isset($_POST['area']) && $_POST['area']==='south')   ? 'selected' : '' ?>>South</option>
                    </select>
                    <i class="icon_pin_alt"></i>
                  </div>
                </div>
                <!-- 2. DIVISI -->
                <div class="col-lg-4">
                  <div class="form-group position-relative">
                    <select id="select-division" name="division" class="form-control select2" data-placeholder="Pilih TEAM">
                      <option></option>
                    </select>
                    <i class="icon_list"></i>
                  </div>
                </div>
                <!-- 3. CARI FASILITAS -->
                <div class="col-lg-3">
                  <div class="form-group position-relative">
                    <select id="select-search"
                            name="search"
                            class="form-control select2-ajax"
                            data-placeholder="Cari Fasilitas">
                      <option></option>
                    </select>
                    <i class="icon_search"></i>
                  </div>
                </div>
                <!-- 4. BUTTON SUBMIT -->
                <div class="col-lg-1">
                  <button type="submit" class="btn btn-primary w-100" style="margin-top: 6px;">CARI</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </section>
      <div class="main_categories">
        <div class="container">
          <ul class="clearfix">
            <li>
              <a href="#">
                <i class="fas fa-solar-panel fa-2x"></i>
                <h3>Substation</h3>
                <h3 class="count" id="count-wellpad"><?= $countWP ?></h3>
              </a>
            </li>
            <li>
              <a href="#">
                <i class="fas fa-database fa-2x"></i>
                <h3>GS</h3>
                <h3 class="count" id="count-gs"><?= $countGS ?></h3>
              </a>
            </li>
            <li>
              <a href="#">
                <i class="fas fa-bolt-lightning fa-2x"></i>
                <h3>PG & T</h3>
                <h3 class="count" id="count-pgt"><?= $countPgt ?></h3>
              </a>
            </li>
            <li>
              <a href="#">
                <i class="fas fa-file-alt fa-2x"></i>
                <h3>Dokumen</h3>
                <h3 class="count" id="count-docs"><?= $countDocs ?></h3>
              </a>
            </li>
          </ul>
        </div>
      </div>
      <!-- FASILITAS: hidden by default on GET -->
      <div id="facility-container" class="container margin_60_35"
           style="<?= $showFacilities ? '' : 'display:none;' ?>">
        <div class="main_title_3">
            <span></span>
            <h2>Fasilitas</h2>
            <p><?= htmlspecialchars($subtitle, ENT_QUOTES) ?></p>
        </div>
        <div class="row add_bottom_30">
            <!-- Replace your existing facility card structure with this -->
<?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
<?php $docPath = "dokumen/" . rawurlencode($row['nama_fasilitas']); ?>
<div class="col-lg-3 col-sm-6">
    <div class="griditem facility-card" data-facility="<?= htmlspecialchars($row['nama_fasilitas'], ENT_QUOTES) ?>">
        <figure class="facility-image">
            <?php 
            $fotoFile = $row['foto'] ?? '';
            $fotoFile = basename($fotoFile);
            $fotoFile = $fotoFile != '' ? $fotoFile : 'placeholder.jpg';
            $imgSrc = "img/fasilitas/" . rawurlencode($fotoFile);
            if (!is_file(__DIR__ . "/" . $imgSrc)) {
                $imgSrc = "img/fasilitas/placeholder.jpg";
            }
            ?>
            <img src="<?= htmlspecialchars($imgSrc, ENT_QUOTES) ?>" 
                 alt="<?= htmlspecialchars($row['nama_fasilitas'], ENT_QUOTES) ?>" 
                 loading="lazy" width="400" height="267">
            
            <!-- Overlay dengan animasi -->
            <div class="facility-overlay">
                <div class="facility-content">
                    <h3 class="facility-name"><?= htmlspecialchars($row['nama_fasilitas'], ENT_QUOTES) ?></h3>
                    <div class="facility-meta">
                        <span class="facility-area"><?= htmlspecialchars($row['area'] ?? '', ENT_QUOTES) ?></span>
                        <span class="facility-team"><?= htmlspecialchars($row['tim'] ?? '', ENT_QUOTES) ?></span>
                    </div>
                    <a href="<?= $docPath ?>" class="facility-btn">
                        <i class="fas fa-eye"></i>
                        <span>Lihat Fasilitas</span>
                    </a>
                </div>
            </div>
        </figure>
    </div>
</div>
<?php endwhile; ?>

        </div>
      </div>
    </main>
  </div><!-- /page -->
  <div id="toTop"></div>
  <!-- SCRIPTS -->
  <script src="js/common_scripts.js"></script>
  <script src="js/functions.js"></script>
  <script src="assets/validate.js"></script>
  <script src="js/jquery-3.6.0.min.js"></script>
  <script src="js/select2.min.js"></script>
  <script>
    // inisialisasi Select2 untuk Area (static)
    $('#select-area').select2({
      placeholder: function(){
        return $(this).data('placeholder');
      },
      allowClear: true
    });
    // inisialisasi Select2 AJAX untuk Divisi
    $('#select-division').select2({
      placeholder: function(){
        return $(this).data('placeholder');
      },
      allowClear: true,
      ajax: {
        url: 'index.php?ajax=divisions',
        dataType: 'json',
        delay: 250,
        cache: false, // anti-cache intranet
        data: function(params) {
          return {
            area: $('#select-area').val(),
            q: params.term,
            _: Date.now()
          };
        },
        processResults: function(data) {
          return { results: data.results };
        },
        error: function(xhr, status, err){
          console.error('Divisions AJAX error:', status, err, xhr && xhr.responseText);
        }
      }
    });
    // inisialisasi Select2 AJAX untuk Search Fasilitas
    $('#select-search').select2({
      placeholder: function(){
        return $(this).data('placeholder');
      },
      allowClear: true,
      ajax: {
        url: 'index.php?ajax=facilities',
        dataType: 'json',
        delay: 250,
        cache: false, // anti-cache intranet
        data: function (params) {
          return {
            q:        params.term,
            area:     $('#select-area').val(),
            division: $('#select-division').val(),
            _: Date.now()
          };
        },
        processResults: function (data) {
          return {
            results: data.results.map(function(item){
              return { id: item.text, text: item.text };
            })
          };
        },
        error: function(xhr, status, err){
          console.error('Facilities AJAX error:', status, err, xhr && xhr.responseText);
        }
      }
    });
    // fungsi untuk update counts kategori
    function updateCounts(area, division, search) {
      $.ajax({
        url: 'index.php',
        type: 'GET',
        dataType: 'json',
        cache: false,
        timeout: 10000,
        data: {
          ajax: 'counts',
          area: area || '',
          division: division || '',
          search: search || '',
          _: Date.now()
        },
        success: function(data, textStatus, xhr) {
          var ct = (xhr.getResponseHeader && xhr.getResponseHeader('Content-Type')) || '';
          if (ct.indexOf('application/json') === -1) {
            console.warn('Non-JSON response for counts:', ct);
            return;
          }
          $('#count-wellpad').fadeOut(200, function(){
            $(this).text(data.wellpad).fadeIn(200);
          });
          $('#count-gs').fadeOut(200, function(){
            $(this).text(data.gs).fadeIn(200);
          });
          $('#count-pgt').fadeOut(200, function(){
            $(this).text(data.pgt).fadeIn(200);
          });
          $('#count-docs').fadeOut(200, function(){
            $(this).text(data.docs).fadeIn(200);
          });
        },
        error: function(xhr, status, err) {
          console.error('Counts AJAX error:', status, err, xhr && xhr.responseText);
          ['#count-wellpad','#count-gs','#count-pgt','#count-docs'].forEach(function(sel){
            $(sel).text('-');
          });
        }
      });
    }
    $(function(){
      // jika form POST, langsung tampilkan dan scroll
      <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        $('#main-content').show();
        $('html, body').animate({
          scrollTop: $('#facility-container').offset().top
        }, 150);
      <?php endif; ?>
      // update saat area berubah
      $('#select-area').on('change', function(){
        $('#select-division').val(null).trigger('change');
        $('#select-search').val(null).trigger('change');
        updateCounts(
          $('#select-area').val(),
          $('#select-division').val(),
          $('#select-search').val()
        );
      });
      // update saat division berubah
      $('#select-division').on('change', function(){
        $('#select-search').val(null).trigger('change');
        updateCounts(
          $('#select-area').val(),
          $('#select-division').val(),
          $('#select-search').val()
        );
      });
      // update saat fasilitas (search) berubah/clear
      $('#select-search').on('change', function(){
        updateCounts(
          $('#select-area').val(),
          $('#select-division').val(),
          $('#select-search').val()
        );
      });
      // toggle loader & container saat submit
      $('form').on('submit', function(){
        $('#facility-container').hide();
        $('#loader').css('display','flex');
      });
      <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
      // setelah hasil POST tiba, sembunyikan loader, tampilkan fasilitas, scroll
      $(window).on('load', function(){
        // sembunyikan hero intro sebelum scroll
        $('#hero-intro').hide();
        $('#loader').hide();
        $('#facility-container').show();
        $('html, body').animate({
          scrollTop: $('#facility-container').offset().top
        }, 150);
      });
      <?php endif; ?>
      // jalankan counts awal
      updateCounts(
        $('#select-area').val(),
        $('#select-division').val(),
        $('#select-search').val()
      );
    });
    // klik tombol Dokumen Fasilitas P&O dengan fade out/in yang lebih smooth
    $('#btn-dokumen-fasilitas').on('click', function(e) {
      e.preventDefault();
      $('#hero-intro').fadeOut(800, function() {
        $('#main-content').fadeIn(800, function() {
          $('html, body').animate({
            scrollTop: $('#main-content').offset().top
          }, 500);
        });
      });
    });
    /* ===== Back to Top: sembunyikan di intro, tampil di halaman hasil, dan scroll ke main ===== */
    /* Sembunyikan saat intro masih tampil, tampilkan setelah intro hilang */
    function syncToTopVisibility(){
      if ($('#hero-intro').is(':visible')){
        $('#toTop').hide();
      } else {
        $('#toTop').fadeIn();
      }
    }
    syncToTopVisibility();
    $(window).on('scroll resize', syncToTopVisibility);
    /* Jika tombol CTA ditekan (intro -> main), tampilkan back-to-top setelah transisi */
    $('#btn-dokumen-fasilitas').on('click', function(){
      setTimeout(syncToTopVisibility, 850); // jeda ≈ durasi fade
    });
    /* Pada halaman hasil (POST), pastikan back-to-top tampil */
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
      $(window).on('load', syncToTopVisibility);
    <?php endif; ?>
    /* Perilaku klik: scroll ke awal #main-content (bukan ke intro yang hidden) */
    $('#toTop').off('click').on('click', function(e){
      e.preventDefault();
      var headerH = $('header.header.menu_fixed').outerHeight() || 0;
      var targetY = $('#main-content').is(':visible')
        ? Math.max(0, $('#main-content').offset().top - headerH - 10)
        : 0; // jika intro masih tampil (harusnya tidak), fallback ke 0
      $('html, body').animate({scrollTop: targetY}, 400);
    });
    $(document).ready(function() {
  // Add floating label effect
  $('.custom-search-input-2 .form-group').each(function() {
    const $group = $(this);
    const $select = $group.find('select');
    const placeholder = $select.data('placeholder');
    
    if (placeholder) {
      $group.attr('data-label', placeholder).addClass('floating-label');
    }
  });
  
  // Handle floating label states
  $('.custom-search-input-2 select').on('select2:open select2:close change', function() {
    const $group = $(this).closest('.form-group');
    const hasValue = $(this).val() && $(this).val() !== '';
    
    $group.toggleClass('active', hasValue);
  });
  
  // Add loading state during form submission
  $('.custom-search-input-2 form, form').on('submit', function() {
    $('.custom-search-input-2').addClass('loading');
  });
  
  // Add success state after successful search
  <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
  $('.custom-search-input-2').removeClass('loading').addClass('success');
  setTimeout(function() {
    $('.custom-search-input-2').removeClass('success');
  }, 3000);
  <?php endif; ?>
  
  // Enhanced hover effects for each column
  $('.custom-search-input-2 .col-lg-4, .custom-search-input-2 .col-lg-3, .custom-search-input-2 .col-lg-1').hover(
    function() {
      $(this).find('.form-group').addClass('hover-state');
    },
    function() {
      $(this).find('.form-group').removeClass('hover-state');
    }
  );
});

// Add stagger animation on page load
$(window).on('load', function() {
  $('.custom-search-input-2 .col-lg-4, .custom-search-input-2 .col-lg-3, .custom-search-input-2 .col-lg-1').each(function(index) {
    $(this).css({
      'opacity': '0',
      'transform': 'translateY(30px)'
    }).delay(index * 100).animate({
      'opacity': '1'
    }, 600).css({
      'transform': 'translateY(0)',
      'transition': 'transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)'
    });
  });
});

  </script>
  <script>
// Enhanced facility card interactions
$(document).ready(function() {
    // Intersection Observer for staggered animations
    if ('IntersectionObserver' in window) {
        const facilityObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.animationDelay = `${index * 0.1}s`;
                        entry.target.classList.add('animate-in');
                    }, index * 100);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        $('.facility-card').each(function() {
            facilityObserver.observe(this);
        });
    }
    
    // Enhanced hover sound effect (optional)
    $('.facility-card').on('mouseenter', function() {
        $(this).addClass('hover-active');
        // Optional: Add subtle sound effect here
    }).on('mouseleave', function() {
        $(this).removeClass('hover-active');
    });
    
    // Facility button click animation
    $('.facility-btn').on('click', function(e) {
        const ripple = $('<div class="ripple-effect"></div>');
        $(this).append(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    });
});
</script>

</body>
</html>
