<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'config.php';
require_once 'document_types.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

session_start();

function buildUrl(array $params = []): string {
    $query = array_merge($_GET, $params);
    return strtok($_SERVER["REQUEST_URI"], '?') . '?' . http_build_query($query);
}

function getStatusSteps(string $current_status, array $t): array {
    $statuses = ['received', 'approved', 'shipped'];
    $steps = [];
    foreach ($statuses as $i => $status) {
        $step_number = $i + 1;
        $is_completed = array_search($status, $statuses) <= array_search($current_status, $statuses);
        $is_active = $status === $current_status;
        $image_suffix = ($is_completed || $is_active) ? '2' : '1';
        $image_file = "img/step{$step_number}-{$image_suffix}.png";
        $steps[] = [
            'name' => $t['status_' . $status] ?? ucfirst($status),
            'image' => $image_file,
            'completed' => $is_completed,
            'active' => $is_active
        ];
    }
    return $steps;
}

$lang = 'sq';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['sq','en'], true)) {
    $lang = $_GET['lang'];
    $_SESSION['lang'] = $lang;
} elseif (isset($_SESSION['lang'])) {
    $lang = $_SESSION['lang'];
}

$translations = [
    'sq' => [
        'page_title' => 'Gjurmoni Statusin e Dokumentit',
        'header_title' => 'Gjurmoni Dokumentin në kohë reale',
        'header_subtitle' => 'Gjurmoni statusin e aplikimit tuaj në kohë reale',
        'app_number_label' => 'Numri i Aplikimit',
        'app_number_placeholder' => 'Vendos kodin e aplikimit',
        'track_button' => 'Kontrollo Statusin',
        'not_found' => '❌ Nuk u gjet dokument për aplikimin',
        'switch_to_albanian' => 'SQ',
        'switch_to_english' => 'EN',
        'app_progress' => 'Ecuria e aplikimit',
        'current_status' => 'Statusi aktual',
        'document_type' => 'Lloji i dokumentit',
        'barcode_title' => 'Barkod për tërheqjen e dokumentit',
        'barcode_instruction' => 'Paraqiteni këtë barkod operatorit për të tërhequr dokumentin tuaj',
        'status_received' => 'Aplikimi u krye',
        'status_approved' => 'Në proces prodhimi',
        'status_shipped' => 'Në proces dorëzimi',
		'status_withdrawn' => 'Për këtë numer aplikimi dokumenti duhët të jëtë tërhequr. Për më tepër, kontaktoni me shërbimin ndaj klientit.'
    ],
    'en' => [
        'page_title' => 'Track Document Status',
        'header_title' => ' Track Your Document in Real-Time',
        'header_subtitle' => 'Track the status of your application in real-time',
        'app_number_label' => 'Application Number',
        'app_number_placeholder' => 'Enter your application code',
        'track_button' => ' Track Status',
        'not_found' => '❌ No document found for application',
        'switch_to_albanian' => 'SQ',
        'switch_to_english' => 'EN',
        'app_progress' => 'Application Progress',
        'current_status' => 'Current Status',
        'document_type' => 'Document Type',
        'barcode_title' => 'Barcode for Document Collection',
        'barcode_instruction' => 'Show this code to the operator to collect your document',
        'status_received' => 'Application done',
        'status_approved' => 'In production process',
        'status_shipped' => 'In delivery process',
		'status_withdrawn' => 'Për këtë numer aplikimi dokumenti duhët të jëtë tërhequr. Për më tepër, kontaktoni me shërbimin ndaj klientit.'
    ]
];

$t = $translations[$lang];

$form_number = '';
$error_message = '';
$document_data = [];
$normal_delivery_date_str = '';

if (($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['form_number'])) || !empty($_GET['id'])) {
    $form_number = trim($_POST['form_number'] ?? $_GET['id']);

    if (!preg_match('/^\d{17,18}$/', $form_number)) {
        $error_message = $lang === 'sq' ?
            'Numri i aplikimit është i pavlefshëm. Kontrolloni numrin e aplikimit' :
            'Application number is invalid. Check your application number.';
    } else {
        $year_prefix = substr($form_number, 0, 2);
        $year = 2000 + (int)$year_prefix;
        if ($year < 2024) {
            $error_message = $t['not_found'] . ' ' . htmlspecialchars($form_number);
        } else {
            try {
                $stmt = $pdo->prepare("SELECT form_number, status, document_type_id, mobile_phone, client, created_at, updated_at FROM documents WHERE form_number = ? AND document_type_id IN (2,3) ORDER BY document_type_id");
                $stmt->execute([$form_number]);
                $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (empty($documents)) {
                    $prefix = substr($form_number, 0, 6);
                    $todayPrefix = date('ymd');
                    if ($prefix === $todayPrefix) {
                        $error_message = $lang === 'sq' ?
                            'Aplikimi nuk është regjistruar ende në sistem' :
                            'The application is not yet registered in the system';
                    } else if ($year >= 2024) {
                        $error_message = $t['status_withdrawn'] . ' ' . htmlspecialchars($form_number);
                    } else {
                        $error_message = $t['not_found'] . ' ' . htmlspecialchars($form_number);
                    }
                } else {
                    $document_data = $documents;
                    $appDatePrefix = substr($form_number, 0, 6);
                    $appDate = DateTime::createFromFormat('ymd', $appDatePrefix);
                    if ($appDate instanceof DateTime) {
                        $normal_delivery_date_str = $appDate->add(new DateInterval('P14D'))->format('d-m-Y');
                    }
                }
            } catch (PDOException $e) {
                $error_message = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['page_title']) ?></title>
    <link rel="icon" type="image/svg+xml" href="img/favicon.svg">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="css/style.css">

    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
</head>
<body>
    <!-- Top Contact Bar -->
    <div class="top-contact-bar">
        <div class="container">
            <div class="contact-info">
                <span class="contact-item">
                    <i class="fa-solid fa-envelope"></i>
                    <a href="mailto:kujdesi.klientit@identitek.al">kujdesi.klientit@identitek.al</a>
                </span>
                <span class="contact-item">
                    <i class="fa-solid fa-phone"></i>
                    <a href="tel:042420000">04 242 0000</a>
                </span>
            </div>
            <div class="top-language-switcher">
                <?php $otherLang = ($lang === 'sq') ? 'en' : 'sq'; ?>
                <a href="<?= buildUrl(['lang' => $otherLang]); ?>">
                    <?= strtoupper($otherLang); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Navigation (white background) -->
    <nav class="navbar main-navbar">
        <div class="container">
            <div class="navbar-logo">
                <a href="https://identitek.al">
                    <img src="img/identitek-logo.svg" alt="identiTek" class="logo-img">
                </a>
            </div>
            <button class="hamburger" id="mobileMenuToggle" aria-controls="navbarMenu" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fa-solid fa-bars"></i>
            </button>
            <ul class="navbar-menu" id="navbarMenu">
                <li class="menu-item"><span><a href="https://identitek.al/identitek">Rreth Nesh</a></span></li>
                <li class="menu-item"><span><a href="https://identitek.al/dokumente-biometrike">Dokumente Biometrike</a></span></li>
                <li class="menu-item"><span><a href="https://identitek.al/aplikimet">Procedura e Aplikimit</a></span></li>
                <li class="menu-item"><span><a href="https://identitek.al/njoftime">Njoftime</a></span></li>
                <li class="menu-item"><span><a href="https://identitek.al/e-kupon">E-Kupon</a></span></li>
                <li class="menu-item"><span><a href="https://identitek.al/programi-i-transparences">Programi i Transparencës</a></span></li>
            </ul>
            <div class="navbar-actions">
                <div class="navbar-button">
                    <a href="tel:042420000" class="btn-contact">Telefono</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Original hero image and text -->
    <div class="hero-header">
        <div class="hero-text">
            <div class="top-lines">
                <?php if ($lang === 'sq'): ?>
                    <span class="line">Gjurmo</span>
                    <span class="line">Dokumentin</span>
                <?php else: ?>
                    <span class="line">Track</span>
                    <span class="line">Document</span>
                <?php endif; ?>
            </div>
            <div class="bottom-lines">
                <?php if ($lang === 'sq'): ?>
                    <span class="line">N'CAST NGA</span>
                    <span class="line">IDENTITEK</span>
                <?php else: ?>
                    <span class="line">INSTANTLY FROM</span>
                    <span class="line">IDENTITEK</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content-container">
        <div class="header">
            <h1>
                <?php if ($lang === 'sq'): ?>
                    <span class="black-text">Gjurmo Dokumentin</span>
                    <span class="blue-text">në kohë Reale</span>
                <?php else: ?>
                    <span class="black-text">Track Document</span>
                    <span class="blue-text">in Real Time</span>
                <?php endif; ?>
            </h1>
        </div>

        <div class="content">
            <div class="search-form">
                <?php
                    $base_url = strtok($_SERVER['REQUEST_URI'], '?');
                    if (!empty($_GET['lang'])) {
                        $base_url .= '?lang=' . urlencode($_GET['lang']);
                    }
                ?>
                <form method="POST" action="<?= htmlspecialchars($base_url); ?>">
                    <?php if (!empty($_GET['lang'])): ?>
                        <input type="hidden" name="lang" value="<?= htmlspecialchars($_GET['lang']); ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="form_number"><?= $t['app_number_label']; ?></label>
                        <input type="text" id="form_number" name="form_number" value="<?= htmlspecialchars($form_number); ?>" placeholder="<?= $t['app_number_placeholder']; ?>" required>
                    </div>
                    <button type="submit" class="btn"><?= $lang === 'sq' ? 'Kontrollo Statusin' : 'Check Status'; ?></button>
                </form>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?= $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($document_data)): ?>
                <?php foreach ($document_data as $doc): ?>
                <div class="status-container" style="margin-bottom: 30px;">
                    <div class="status-progress">
                        <?php $docTypeName = getDocumentTypeTranslations($lang)[$doc['document_type_id']] ?? 'Unknown'; ?>
                        <h3>
                            <?= $lang === 'sq'
                                ? ('Statusi Aplikimit - ' . htmlspecialchars($docTypeName))
                                : ('Application Progress -: ' . htmlspecialchars($docTypeName)); ?>
                        </h3>
                        <?php
                        $steps = getStatusSteps($doc['status'], $t);
                        ?>
                        <div class="progress-container">
                            <?php foreach ($steps as $step): ?>
                                <div class="step">
                                    <div class="step-circle <?= $step['completed'] ? 'completed' : ''; ?> <?= $step['active'] ? 'active' : ''; ?>">
                                        <img src="<?= htmlspecialchars($step['image']); ?>" alt="<?= htmlspecialchars($step['name']); ?>" />
                                    </div>
                                    <div class="step-progress-bar <?= ($step['completed'] || $step['active']) ? 'active' : 'inactive'; ?>"></div>
                                    <div class="step-name <?= $step['completed'] ? 'completed' : ''; ?> <?= $step['active'] ? 'active' : ''; ?>">
                                        <?= $step['name']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="success-message">
                            <?= $t['current_status']; ?>: <span class="status-action"><?php
                                $status_key = 'status_' . $doc['status'];
                                echo $t[$status_key] ?? htmlspecialchars($doc['status']);
                            ?></span>
                        </div>
                        <div class="delivery-info">
                            <?php if ($lang === 'sq'): ?>
                                <?php if (!empty($normal_delivery_date_str)): ?>
                                    <div>
                                        Për aplikim me kupon normal data e shpërndarjes duhet të jetë
                                        <strong><?= htmlspecialchars($normal_delivery_date_str); ?></strong>.
                                    </div>
                                <?php endif; ?>
                                <div>
                                    Për aplikimi me kupon të shpejtë data e shpërndarjes duhet të jetë brenda 3 ditëve.
                                </div>
                            <?php else: ?>
                                <?php if (!empty($normal_delivery_date_str)): ?>
                                    <div>
                                        For a normal coupon application, the delivery date should be
                                        <strong><?= htmlspecialchars($normal_delivery_date_str); ?></strong>.
                                    </div>
                                <?php endif; ?>
                                <div>
                                    For a fast coupon application, the delivery date should be within 3 days.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php
                    $showBarcode = false;
                    if ($doc['status'] === 'shipped') {
                        $shippedCount = array_reduce($document_data, function($count, $d) {
                            return $count + ($d['status'] === 'shipped' ? 1 : 0);
                        }, 0);
                        if ($shippedCount == 1 || ($shippedCount > 1 && $doc['document_type_id'] == min(array_column(array_filter($document_data, fn($d) => $d['status'] === 'shipped'), 'document_type_id')))) {
                            $showBarcode = true;
                        }
                    }
                    if ($showBarcode): ?>
                        <div class="barcode-section">
                            <h3><?= $t['barcode_title']; ?></h3>
                            <p class="barcode-instruction"><?= $t['barcode_instruction']; ?></p>
                            <div class="barcode-container">
                                <svg id="barcode"></svg>
                                <div class="barcode-text"><?= htmlspecialchars($doc['form_number']); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $hasShippedDoc = false;
    $barcodeFormNumber = '';
    foreach ($document_data as $doc) {
        if ($doc['status'] === 'shipped') {
            $hasShippedDoc = true;
            $barcodeFormNumber = $doc['form_number'];
            break;
        }
    }
    if ($hasShippedDoc): ?>
    <script>
        JsBarcode("#barcode", "<?= htmlspecialchars($barcodeFormNumber); ?>", {
            format: "CODE128",
            width: 2,
            height: 80,
            displayValue: false,
            background: "#ffffff",
            lineColor: "#000000"
        });
    </script>
    <?php endif; ?>

    <!-- Footer from Laravel -->
    <footer class="footer-two-area secondary-background">
        <div class="container">
            <div class="quote__wrp quote-wrapper gradient-background mb-5">
                <div class="row align-items-center">
                    <div class="col-md-9">
                        <div class="section-header">
                            <h5 class="wow fadeInLeft text-white">
                                <i class="fa fa-envelope"></i> <?= $lang === 'sq' ? 'KONTAKTO' : 'CONTACT'; ?>
                            </h5>
                            <h2 class="wow fadeInLeft text-white quote-text">
                                <?php if ($lang === 'sq'): ?>
                                    Jemi këtu, të përkushtuar<br>për t'ju ndihmuar.
                                <?php else: ?>
                                    We are here, dedicated to help you.
                                <?php endif; ?>
                            </h2>
                        </div>
                    </div>
                    <div class="col-md-3 text-end">
                        <a href="https://identitek.al/contact" class="btn-one wow FadeInUp quote-cta">
                            <?= $lang === 'sq' ? 'Kontakto' : 'Contact'; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer__shape-regular-left wow slideInLeft">
            <img src="img/footer-regular-left.png" alt="shape">
        </div>
        <div class="footer__shape-solid-right wow slideInRight">
            <img class="sway_Y__animation" src="img/footer-regular-right.png" alt="shape">
        </div>

        <div class="container">
            <div class="footer__wrp pt-100 pb-100">
                <div class="footer__item item-big wow fadeInUp">
                    <a href="https://identitek.al" class="logo mb-30">
                        <img src="img/identitek-logo-blue.svg" alt="identiTek">
                    </a>
                </div>
                <div class="footer__item item-sm wow fadeInUp">
                    <h3 class="footer-title">Rreth Nesh</h3>
                    <ul>
                        <li><a href="https://identitek.al"><i class="fa-solid fa-angles-right me-1"></i>Kreu</a></li>
                        <li><a href="https://identitek.al/identitek"><i class="fa-solid fa-angles-right me-1"></i>Rreth Nesh</a></li>
                        <li><a href="https://identitek.al/dokumente-biometrike"><i class="fa-solid fa-angles-right me-1"></i>Dokumente Biometrike</a></li>
                        <li><a href="https://identitek.al/aplikimet"><i class="fa-solid fa-angles-right me-1"></i>Procedurat e Aplikimit</a></li>
                        <li><a href="https://identitek.al/njoftime"><i class="fa-solid fa-angles-right me-1"></i>Njoftime</a></li>
                    </ul>
                </div>
                <div class="footer__item item-sm wow fadeInUp">
                    <h3 class="footer-title">Të Tjera</h3>
                    <ul>
                        <li><a href="https://identitek.al/legjislacion"><i class="fa-solid fa-angles-right me-1"></i>Legjislacioni</a></li>
                        <li><a href="https://identitek.al/programi-i-transparences"><i class="fa-solid fa-angles-right me-1"></i>Programi i Transparencës</a></li>
                        <li><a href="https://identitek.al/karriera"><i class="fa-solid fa-angles-right me-1"></i>Karriera</a></li>
                        <li><a href="https://identitek.al/faq"><i class="fa-solid fa-angles-right me-1"></i>Pyetjet më të shpeshta</a></li>
                        <li><a href="https://identitek.al/sherbime"><i class="fa-solid fa-angles-right me-1"></i>Shërbime</a></li>
                    </ul>
                </div>
                <div class="footer__item item-big wow fadeInUp">
                    <h3 class="footer-title"><?= $lang === 'sq' ? 'Kontakti' : 'Contact'; ?></h3>
                    <ul class="footer-contact">
                        <li>
                            <i class="fa-solid fa-map-pin"></i>
                            <div class="info">
                                <p>Rr. “Xhanfize Keko”,<br>Ndërtesa nr. 111,<br>Hyrja nr.1, Tiranë</p>
                            </div>
                        </li>
                        <li>
                            <i class="fa-solid fa-phone"></i>
                            <div class="info">
                                <a href="tel:042420000"><p style="color: white">04 242 0000</p></a>
                            </div>
                        </li>
                        <li>
                            <i class="fa-solid fa-envelope"></i>
                            <div class="info">
                                <p>kujdesi.klientit@identitek.al</p>
                            </div>
                        </li>
                        <li>
                            <a href="https://www.instagram.com/identitek_sh.a" target="_blank"><i class="fa-brands fa-instagram"></i></a>
                            <a href="https://www.linkedin.com/company/identitek-albania/" target="_blank"><i class="fa-brands fa-linkedin-in"></i></a>
                            <a href="https://www.facebook.com/IdentiTekAlbania" target="_blank"><i class="fa-brands fa-facebook"></i></a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer__copyright">
            <div class="container">
                <div class="d-flex gap-1 flex-wrap align-items-center justify-content-md-between justify-content-center">
                    <p class="wow fadeInDown">© Të Gjitha Të Drejtat e Rezervuara nga <a href="https://identitek.al">identiTek sh.a</a></p>
                    <ul class="d-flex align-items-center gap-4 wow fadeInDown">
                        <li><a href="https://identitek.al/politikat-e-privatesise"><?= $lang === 'sq' ? 'Privatësia' : 'Privacy'; ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <script src="js/jquery-3.7.1.min.js"></script>
    <script src="js/script.js"></script>
    <script src="js/wow.min.js"></script>
    <script> if (typeof WOW === 'function') { new WOW().init(); } </script>
</body>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var toggle = document.getElementById('mobileMenuToggle');
  var menu = document.getElementById('navbarMenu');
  if (!toggle || !menu) return;
  function setOpen(open) {
    if (open) { menu.classList.add('open'); toggle.setAttribute('aria-expanded', 'true'); }
    else { menu.classList.remove('open'); toggle.setAttribute('aria-expanded', 'false'); }
  }
  toggle.addEventListener('click', function() {
    setOpen(!menu.classList.contains('open'));
  });
  // Close on link click
  menu.addEventListener('click', function(e) {
    if (e.target.closest('a')) setOpen(false);
  });
  // Close on Escape
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') setOpen(false);
  });
});
</script>
</html>
