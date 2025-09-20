<?php
date_default_timezone_set('Asia/Jakarta');
try {
    $host     = 'localhost';
    $dbname   = 'spektra';
    $username = 'root';
    $password = '';
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo 'Connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
$facility = '';
if (!empty($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] !== '/') {
    $facility = trim($_SERVER['PATH_INFO'], '/');
} elseif (isset($_GET['facility'])) {
    $facility = trim($_GET['facility']);
}
$isAjax = isset($_GET['ajax_search']);
if ($facility === '' && !$isAjax) {
    header('Location: /spektra/');
    exit;
}
function buildImageUrl($value) {
    $appBase = '/spektra';
    $baseWeb = $appBase . '/img/fasilitas/dokumen/';
    $filename = basename((string)$value);
    if ($filename === '') {
        return $appBase . '/img/no-image.png';
    }
    $webPath = $baseWeb . $filename;
    $fsPath  = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . $webPath;
    if (!is_file($fsPath)) {
        return $appBase . '/img/no-image.png';
    }
    return $webPath;
}
function extractFacilityBase($name) {
    $base = preg_replace('/\s*GS\b/i','', (string)$name);
    return trim($base);
}
function buildSharePointUrl($area, $docType) {
    $base = '';
    $q = '?FilterField1=Area&FilterValue1=' . rawurlencode($area) . '&FilterField2=Document_x0020_Type&FilterValue2=' . rawurlencode($docType);
    return $base . $q;
}
$coordStmt = $pdo->prepare("SELECT koordinat FROM fasilitas WHERE nama_fasilitas = :facility");
$coordStmt->bindValue(':facility', $facility);
$coordStmt->execute();
$koordinat = $coordStmt->fetchColumn();
$areaForSharePoint = extractFacilityBase($facility);
if (!$koordinat || strpos($koordinat, ',') === false) {
    $latFacility = -6.2088;
    $lngFacility = 106.8456;
} else {
    $parts = array_map('trim', explode(',', $koordinat, 2));
    $latFacility = (float)$parts[0];
    $lngFacility = (float)$parts[1];
}
if (isset($_GET['ajax_search'])) {
    $search = $_GET['search'] ?? '';
    $filter = $_GET['listing_filter'] ?? 'all';
    $subFilter = $_GET['sub_filter'] ?? '';
    
    $sql = "SELECT * FROM dokumen
            WHERE fasilitas = :facility
              AND (nama_dokumen LIKE :search OR dokumen_owner LIKE :search)";
    $params = [
        ':facility' => $facility,
        ':search'   => '%' . $search . '%',
    ];
    
    if ($filter !== 'all') {
        $sql .= " AND klasifikasi_dokumen = :filter";
        $params[':filter'] = $filter;
    }
    
    // Sub-filter logic
    if ($subFilter) {
        switch($subFilter) {
            case 'OP':
                $sql .= " AND nama_dokumen LIKE '%OP%'";
                break;
            case 'SOP':
                $sql .= " AND nama_dokumen LIKE '%SOP%'";
                break;
            case 'STK':
                $sql .= " AND nama_dokumen LIKE '%STK%'";
                break;
        }
    }
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $today = date('Y-m-d');
    
    foreach ($results as $dok) {
        $status = ($dok['expired_dokumen'] >= $today) ? 'Active' : 'Expired';
        $class  = ($status === 'Active') ? 'loc_open' : 'loc_closed';
        $downloadCount = rand(0, 100);
        $imgUrl = buildImageUrl($dok['gambar']);
        echo '<div class="strip map_view add_top_10">';
        echo '  <div class="row g-0">';
        echo '    <div class="col-4">';
        echo '      <figure>';
        echo '        <a href="'.htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8').'" target="_blank">';
        echo '          <img src="'.htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8').'" class="img-fluid" width="460" height="306" alt="">';
        echo '        </a>';
        echo '        <small>'.htmlspecialchars($dok['klasifikasi_dokumen'], ENT_QUOTES, 'UTF-8').'</small>';
        echo '      </figure>';
        echo '    </div>';
        echo '    <div class="col-8">';
        echo '      <div class="wrapper">';
        echo '        <a href="#0" class="wish_bt"></a>';
        echo '        <h3><a href="'.htmlspecialchars($dok['dokumen'], ENT_QUOTES, 'UTF-8').'" target="_blank">'.htmlspecialchars($dok['nama_dokumen'], ENT_QUOTES, 'UTF-8').'</a></h3>';
        echo '        <small>'.htmlspecialchars($dok['dokumen_owner'], ENT_QUOTES, 'UTF-8').' | '.htmlspecialchars($dok['fasilitas'], ENT_QUOTES, 'UTF-8').'</small>';
        echo '      </div>';
        echo '      <ul>';
        echo '        <li><span class="'.$class.'">'.$status.'</span></li>';
        echo '        <li>';
        echo '          <div class="score">';
        echo '            <span>Download<em>'.$downloadCount.'</em></span>';
        echo '            <a href="'.htmlspecialchars($dok['dokumen'], ENT_QUOTES, 'UTF-8').'" target="_blank"><strong><i class="fa fa-download"></i></strong></a>';
        echo '          </div>';
        echo '        </li>';
        echo '      </ul>';
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
    }
    
    // SharePoint extras for Technical Engineering
    if (($filter === 'Technical Engineering' || $filter === 'all') && (!$subFilter || in_array($subFilter, ['P&ID', 'HCA', 'Layout']))) {
        $extra = [
            ['title' => 'Piping & Instrument Diagrams (P&ID)', 'img' => 'pid.jpg', 'docType' => 'Piping & Instrument Diagrams (P&ID)', 'subtype' => 'P&ID'],
            ['title' => 'Hazardous Area Classification', 'img' => 'hca.jpg', 'docType' => 'Hazardous Area Classification', 'subtype' => 'HCA'],
            ['title' => 'One Line Diagram', 'img' => 'old.jpg', 'docType' => 'One Line Diagram', 'subtype' => 'Layout'],
        ];
        $searchTrim = trim($search);
        foreach ($extra as $x) {
            // Filter by sub-filter if specified
            if ($subFilter && $x['subtype'] !== $subFilter) {
                continue;
            }
            
            if ($searchTrim !== '' && stripos($x['title'], $searchTrim) === false) {
                continue;
            }
            $imgUrl = buildImageUrl($x['img']);
            $spUrl = buildSharePointUrl($areaForSharePoint, $x['docType']);
            echo '<div class="strip map_view add_top_10">';
            echo '  <div class="row g-0">';
            echo '    <div class="col-4">';
            echo '      <figure>';
            echo '        <a href="'.htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8').'" target="_blank">';
            echo '          <img src="'.htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8').'" class="img-fluid" width="460" height="306" alt="">';
            echo '        </a>';
            echo '        <small>Technical Engineering</small>';
            echo '      </figure>';
            echo '    </div>';
            echo '    <div class="col-8">';
            echo '      <div class="wrapper">';
            echo '        <a href="#0" class="wish_bt"></a>';
            echo '        <h3><a href="#0" onclick="openSharePoint(\''.htmlspecialchars($spUrl, ENT_QUOTES, 'UTF-8').'\');return false;">'.htmlspecialchars($x['title'], ENT_QUOTES, 'UTF-8').'</a></h3>';
            echo '        <small>SharePoint | '.htmlspecialchars($areaForSharePoint, ENT_QUOTES, 'UTF-8').'</small>';
            echo '      </div>';
            echo '      <ul>';
            echo '        <li><span class="badge_sharepoint">SharePoint</span></li>';
            echo '        <li>';
            echo '          <div class="score">';
            echo '            <span>Link<em>&nbsp;</em></span>';
            echo '            <a href="#0" onclick="openSharePoint(\''.htmlspecialchars($spUrl, ENT_QUOTES, 'UTF-8').'\');return false;"><strong><i class="fa fa-external-link"></i></strong></a>';
            echo '          </div>';
            echo '        </li>';
            echo '      </ul>';
            echo '    </div>';
            echo '  </div>';
            echo '</div>';
        }
    }
    exit;
}
$filter = isset($_GET['listing_filter']) ? $_GET['listing_filter'] : 'all';
$subFilter = isset($_GET['sub_filter']) ? $_GET['sub_filter'] : '';
$sql    = "SELECT * FROM dokumen";
$params = [];
if ($facility !== '') {
    $sql .= " WHERE fasilitas = :facility";
    $params[':facility'] = $facility;
}
if ($filter !== 'all') {
    $sql .= (strpos($sql, 'WHERE') !== false)
        ? " AND klasifikasi_dokumen = :filter"
        : " WHERE klasifikasi_dokumen = :filter";
    $params[':filter'] = $filter;
}
// Sub-filter for initial load
if ($subFilter) {
    $whereClause = (strpos($sql, 'WHERE') !== false) ? " AND " : " WHERE ";
    switch($subFilter) {
        case 'OP':
            $sql .= $whereClause . "nama_dokumen LIKE '%OP%'";
            break;
        case 'SOP':
            $sql .= $whereClause . "nama_dokumen LIKE '%SOP%'";
            break;
        case 'STK':
            $sql .= $whereClause . "nama_dokumen LIKE '%STK%'";
            break;
    }
}
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$dokumens = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Count extras for display
$extraCount = 0;
if ($filter === 'Technical Engineering' || $filter === 'all') {
    if (!$subFilter) {
        $extraCount = 3; // All P&ID, HCA, Layout
    } elseif (in_array($subFilter, ['P&ID', 'HCA', 'Layout'])) {
        $extraCount = 1; // Only the selected one
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SPEKTRA">
    <meta name="author" content="OIMS">
    <title>SPEKTRA</title>
    <link rel="icon" href="/spektra/img/pertamina_icon.png" type="image/png">
    <link rel="apple-touch-icon" type="image/x-icon" href="/spektra/img/apple-touch-icon-57x57-precomposed.png">
    <link rel="apple-touch-icon" type="image/x-icon" sizes="72x72" href="/spektra/img/apple-touch-icon-72x72-precomposed.png">
    <link rel="apple-touch-icon" type="image/x-icon" sizes="114x114" href="/spektra/img/apple-touch-icon-114x114-precomposed.png">
    <link rel="apple-touch-icon" type="image/x-icon" sizes="144x144" href="/spektra/img/apple-touch-icon-144x144-precomposed.png">
    <link rel="stylesheet" href="/spektra/assets/fonts/fonts.css">
    <link href="/spektra/css/bootstrap.min.css" rel="stylesheet">
    <link href="/spektra/css/style.css" rel="stylesheet">
    <link href="/spektra/css/vendors.css" rel="stylesheet">
    <link href="/spektra/css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>
<body>
    <div id="page">
        <header class="header_in map_view">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-3 col-12">
                        <div id="logo">
                            <a href="/spektra/">
                                <img src="/spektra/img/Pertamina_Logo.svg" width="165" height="35" alt="" class="logo_sticky">
                            </a>
                        </div>
                    </div>
                    <div class="col-lg-9 col-12">
                        <a href="#menu" class="btn_mobile">
                            <div class="hamburger hamburger--spin" id="hamburger">
                                <div class="hamburger-box">
                                    <div class="hamburger-inner"></div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </header>
        <main>
            <div class="container-fluid full-height">
                <div class="row row-height">
                    <div class="col-lg-5 content-left order-md-last order-sm-last order-last">
<div id="results_map_view">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-10">
        <h4 id="result_count" class="text-white mb-0">
          <strong><?php echo count($dokumens) + $extraCount; ?></strong>
          <span id="result_count_text">result untuk fasilitas "<?php echo htmlspecialchars($facility, ENT_QUOTES, 'UTF-8'); ?>"</span>
        </h4>
      </div>
      <div class="col-2 text-end">
        <a href="#0" class="search_mob btn_search_mobile map_view"></a>
      </div>
    </div>
  </div>
</div>
                        <div id="search_container" class="container-fluid" style="display:none; padding:10px;">
                            <form id="searchForm">
                                <input type="text" id="searchInput" class="form-control" placeholder="Cari dokumen..." autocomplete="off">
                            </form>
                        </div>
                        
                        <div class="filters_listing version_3" id="filter_section">
                            <div class="container-fluid">
                                <div class="d-flex align-items-center justify-content-between w-100">
                                    <!-- Filter Kiri (Main Categories) -->
                                    <div class="filter_left">
                                        <form id="filterForm" method="GET">
                                            <input type="hidden" name="facility" value="<?php echo htmlspecialchars($facility, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="sub_filter" id="hidden_sub_filter" value="<?php echo htmlspecialchars($subFilter, ENT_QUOTES, 'UTF-8'); ?>">
                                            <ul class="clearfix">
                                                <li>
                                                    <div class="switch-field">
                                                        <input type="radio" id="all" name="listing_filter" value="all" <?php if ($filter === 'all') echo 'checked'; ?>>
                                                        <label for="all">All</label>
                                                        <input type="radio" id="operation" name="listing_filter" value="Operation" <?php if ($filter === 'Operation') echo 'checked'; ?>>
                                                        <label for="operation">Operation</label>
                                                        <input type="radio" id="technical" name="listing_filter" value="Technical Engineering" <?php if ($filter === 'Technical Engineering') echo 'checked'; ?>>
                                                        <label for="technical">Engineering</label>
                                                    </div>
                                                </li>
                                            </ul>
                                        </form>
                                    </div>
                                    
                                    <!-- Filter Kanan (Sub Filters) -->
                                    <div class="filter_right" style="position: relative;">
                                        <button type="button" class="subfilter_toggle" id="subfilterToggle">
                                            <i class="fa fa-filter"></i>
                                            <span id="subfilterText">Filter</span>
                                            <i class="fa fa-chevron-down"></i>
                                        </button>
                                        <div class="subfilter_dropdown" id="subfilterDropdown">
                                            <!-- Dropdown items will be populated by JavaScript -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="dokumen_list">
                            <?php foreach ($dokumens as $dok) : ?>
                                <?php
                                    $today = date('Y-m-d');
                                    $status = ($dok['expired_dokumen'] >= $today) ? 'Active' : 'Expired';
                                    $class  = ($status === 'Active') ? 'loc_open' : 'loc_closed';
                                    $downloadCount = rand(0, 100);
                                    $imgUrl = buildImageUrl($dok['gambar']);
                                ?>
                                <div class="strip map_view add_top_20">
                                    <div class="row g-0">
                                        <div class="col-4">
                                            <figure>
                                                <a href="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                                                    <img src="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8'); ?>" class="img-fluid" width="460" height="306" alt="">
                                                </a>
                                                <small><?php echo htmlspecialchars($dok['klasifikasi_dokumen'], ENT_QUOTES, 'UTF-8'); ?></small>
                                            </figure>
                                        </div>
                                        <div class="col-8">
                                            <div class="wrapper">
                                                <a href="#0" class="wish_bt"></a>
                                                <h3>
                                                    <a href="<?php echo htmlspecialchars($dok['dokumen'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                                                        <?php echo htmlspecialchars($dok['nama_dokumen'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                </h3>
                                                <small>
                                                    <?php echo htmlspecialchars($dok['dokumen_owner'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($dok['fasilitas'], ENT_QUOTES, 'UTF-8'); ?>
                                                </small>
                                            </div>
                                            <ul>
                                                <li><span class="<?php echo $class; ?>"><?php echo $status; ?></span></li>
                                                <li>
                                                    <div class="score">
                                                        <span>Download<em><?php echo $downloadCount; ?></em></span>
                                                        <a href="<?php echo htmlspecialchars($dok['dokumen'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank"><strong><i class="fa fa-download"></i></strong></a>
                                                    </div>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($filter === 'Technical Engineering' || $filter === 'all'): ?>
                                <?php
                                    $extras = [
                                        ['title' => 'Piping & Instrument Diagrams (P&ID)', 'img' => 'pid.jpg', 'docType' => 'Piping & Instrument Diagrams (P&ID)', 'subtype' => 'P&ID'],
                                        ['title' => 'Hazardous Area Classification', 'img' => 'hca.jpg', 'docType' => 'Hazardous Area Classification', 'subtype' => 'HCA'],
                                        ['title' => 'One Line Diagram', 'img' => 'old.jpg', 'docType' => 'One Line Diagram', 'subtype' => 'Layout'],
                                    ];
                                ?>
                                <?php foreach ($extras as $x): ?>
                                    <?php if ($subFilter && $x['subtype'] !== $subFilter) continue; ?>
                                    <?php
                                        $imgUrl = buildImageUrl($x['img']);
                                        $spUrl = buildSharePointUrl($areaForSharePoint, $x['docType']);
                                    ?>
                                    <div class="strip map_view add_top_20">
                                        <div class="row g-0">
                                            <div class="col-4">
                                                <figure>
                                                    <a href="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                                                        <img src="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8'); ?>" class="img-fluid" width="460" height="306" alt="">
                                                    </a>
                                                    <small>Technical Engineering</small>
                                                </figure>
                                            </div>
                                            <div class="col-8">
                                                <div class="wrapper">
                                                    <a href="#0" class="wish_bt"></a>
                                                    <h3>
                                                        <a href="#0" onclick="openSharePoint('<?php echo htmlspecialchars($spUrl, ENT_QUOTES, 'UTF-8'); ?>');return false;">
                                                            <?php echo htmlspecialchars($x['title'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </a>
                                                    </h3>
                                                    <small>SharePoint | <?php echo htmlspecialchars($areaForSharePoint, ENT_QUOTES, 'UTF-8'); ?></small>
                                                </div>
                                                <ul>
                                                    <li><span class="badge_sharepoint">SharePoint</span></li>
                                                    <li>
                                                        <div class="score">
                                                            <span>Link<em>&nbsp;</em></span>
                                                            <a href="#0" onclick="openSharePoint('<?php echo htmlspecialchars($spUrl, ENT_QUOTES, 'UTF-8'); ?>');return false;"><strong><i class="fa fa-external-link"></i></strong></a>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <p class="text-center add_top_30"><a href="#0" class="btn_1 rounded"><strong>Load more</strong></a></p>
                    </div>
                    <div class="col-lg-7 map-right">
                        <div id="map_right_listing" style="width:100%; height:100%;"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="/spektra/js/common_scripts.js"></script>
    <script src="/spektra/js/functions.js"></script>
    <script src="/spektra/assets/validate.js"></script>
    <script async defer
        <!-- src="https://maps.googleapis.com/maps/api/js?key=&callback=initMap"> -->
    </script>
    <script>
        function initMap() {
            const center = { lat: <?php echo $latFacility; ?>, lng: <?php echo $lngFacility; ?> };
            new google.maps.StreetViewPanorama(
                document.getElementById('map_right_listing'),
                {
                    position: center,
                    pov: { heading: 0, pitch: 0 },
                    zoom: 1
                }
            );
        }
        function openSharePoint(url){
            window.open(url,'sharepoint_popup','width=1200,height=800,menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=yes');
        }
        
        var initialText = 'result untuk fasilitas "<?php echo htmlspecialchars($facility, ENT_QUOTES, 'UTF-8'); ?>"';
        var currentSubFilter = '<?php echo htmlspecialchars($subFilter, ENT_QUOTES, 'UTF-8'); ?>';
        
        document.querySelector('.btn_search_mobile.map_view').addEventListener('click', function(e) {
            e.preventDefault();
            var container = document.getElementById('search_container');
            if (container.style.display === 'none' || container.style.display === '') {
                container.style.display = 'block';
                document.getElementById('searchInput').focus();
            } else {
                container.style.display = 'none';
                document.getElementById('searchInput').value = '';
                fetchAndUpdate('');
            }
        });
        
        var timeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            var query = this.value;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                fetchAndUpdate(query);
            }, 300);
        });
        
        function fetchAndUpdate(query) {
            var currentFilter = document.querySelector('input[name="listing_filter"]:checked').value;
            var url = 'index.php?ajax_search=1'
                + '&facility=<?php echo rawurlencode($facility); ?>'
                + '&listing_filter=' + encodeURIComponent(currentFilter)
                + '&sub_filter=' + encodeURIComponent(currentSubFilter)
                + '&search=' + encodeURIComponent(query);
            
            fetch(url)
              .then(function(response) { return response.text(); })
              .then(function(html) {
                  document.getElementById('dokumen_list').innerHTML = html;
                  var count = document.querySelectorAll('#dokumen_list .strip').length;
                  document.querySelector('#result_count strong').textContent = count;
                  document.querySelector('#result_count_text').textContent = query
                      ? 'result untuk pencarian "' + query + '"'
                      : 'result untuk fasilitas "<?php echo htmlspecialchars($facility, ENT_QUOTES, "UTF-8"); ?>"';
              });
        }
        
        // Sub-filter functionality
        const SUBFILTERS = {
            'Operation': [
                { value: 'OP', label: 'OP' },
                { value: 'SOP', label: 'SOP' },
                { value: 'STK', label: 'STK' }
            ],
            'Technical Engineering': [
                { value: 'P&ID', label: 'P&ID' },
                { value: 'HCA', label: 'HCA' },
                { value: 'Layout', label: 'Layout' }
            ]
        };
        
        function updateSubfilterButton() {
            const toggle = document.getElementById('subfilterToggle');
            const text = document.getElementById('subfilterText');
            
            if (currentSubFilter) {
                toggle.classList.add('active');
                text.textContent = currentSubFilter;
            } else {
                toggle.classList.remove('active');
                text.textContent = 'Filter';
            }
        }
        
        function renderSubfilterDropdown(selected) {
            const dropdown = document.getElementById('subfilterDropdown');
            
            if (!SUBFILTERS[selected]) {
                dropdown.innerHTML = '<div class="subfilter_item">Tidak ada filter tersedia</div>';
                return;
            }
            
            let html = '<div class="subfilter_item' + (!currentSubFilter ? ' active' : '') + '" data-subfilter="">Semua</div>';
            html += SUBFILTERS[selected]
                .map(item => `<div class="subfilter_item${currentSubFilter === item.value ? ' active' : ''}" data-subfilter="${item.value}">${item.label}</div>`)
                .join('');
            dropdown.innerHTML = html;
        }
        
        // Toggle dropdown
        document.getElementById('subfilterToggle').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('subfilterDropdown');
            const isVisible = dropdown.classList.contains('show');
            
            if (isVisible) {
                dropdown.classList.remove('show');
                this.classList.remove('open');
            } else {
                dropdown.classList.add('show');
                this.classList.add('open');
            }
        });
        
        // Handle dropdown item clicks
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('subfilter_item')) {
                // Update active states
                document.querySelectorAll('.subfilter_item').forEach(item => item.classList.remove('active'));
                e.target.classList.add('active');
                
                // Update current sub-filter
                currentSubFilter = e.target.getAttribute('data-subfilter') || '';
                document.getElementById('hidden_sub_filter').value = currentSubFilter;
                
                // Update button appearance
                updateSubfilterButton();
                
                // Hide dropdown
                document.getElementById('subfilterDropdown').classList.remove('show');
                document.getElementById('subfilterToggle').classList.remove('open');
                
                // Trigger search with current query
                var searchQuery = document.getElementById('searchInput').value || '';
                fetchAndUpdate(searchQuery);
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.filter_right')) {
                document.getElementById('subfilterDropdown').classList.remove('show');
                document.getElementById('subfilterToggle').classList.remove('open');
            }
        });
        
        // Main filter change handler
        document.querySelectorAll('input[name="listing_filter"]').forEach(function(el) {
            el.addEventListener('change', function() {
                // Reset sub-filter when main filter changes
                currentSubFilter = '';
                document.getElementById('hidden_sub_filter').value = '';
                updateSubfilterButton();
                
                // Render sub-filters for new category
                renderSubfilterDropdown(this.value);
                
                // Submit form to reload with new main filter
                document.getElementById('filterForm').submit();
            });
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const checked = document.querySelector('input[name="listing_filter"]:checked');
            renderSubfilterDropdown(checked ? checked.value : 'all');
            updateSubfilterButton();
        });
    </script>
</body>
</html>
