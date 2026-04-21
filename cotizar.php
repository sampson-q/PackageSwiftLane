<?php
require_once("loader.php");
require_once("helpers/querys.php");

$core = new Core();
$db = new Conexion();

$origin = isset($_POST['origin']) ? (int)$_POST['origin'] : 0;
$destiny = isset($_POST['destiny']) ? (int)$_POST['destiny'] : 0;
$weight = isset($_POST['weight']) ? (float)$_POST['weight'] : 0.0;
$quoteResult = null;
$quoteError = '';

$db->cdp_query("SELECT id, country_name FROM cdb_countries ORDER BY country_name ASC");
$db->cdp_execute();
$countries = $db->cdp_registros();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($origin <= 0 || $destiny <= 0 || $weight <= 0) {
        $quoteError = 'Please provide origin, destination and weight.';
    } else {
        $db->cdp_query("
            SELECT id, initial_range, final_range, price, price_mile, order_service_options
            FROM cdb_shipping_fees
            WHERE origin = :origin
              AND destiny = :destiny
              AND (client_id = 0 OR client_id IS NULL)
              AND :weight BETWEEN initial_range AND final_range
            ORDER BY id DESC
            LIMIT 1
        ");
        $db->bind(':origin', $origin);
        $db->bind(':destiny', $destiny);
        $db->bind(':weight', $weight);
        $db->cdp_execute();
        $quoteResult = $db->cdp_registro();

        if (!$quoteResult) {
            $quoteError = 'No tariff configured for this route and weight range.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Calculator | <?php echo htmlspecialchars($core->site_name, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo htmlspecialchars($core->favicon, ENT_QUOTES, 'UTF-8'); ?>">
    <link href="assets/css_main_deprixa/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css_main_deprixa/css/style.css" rel="stylesheet" type="text/css" />
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h4 class="mb-4">Public Rate Calculator</h4>
                        <form method="post" action="cotizar.php" novalidate>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Origin country</label>
                                    <select class="form-select" name="origin" required>
                                        <option value="">Select</option>
                                        <?php foreach ($countries as $country): ?>
                                            <option value="<?php echo (int)$country->id; ?>" <?php echo $origin === (int)$country->id ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($country->country_name, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Destination country</label>
                                    <select class="form-select" name="destiny" required>
                                        <option value="">Select</option>
                                        <?php foreach ($countries as $country): ?>
                                            <option value="<?php echo (int)$country->id; ?>" <?php echo $destiny === (int)$country->id ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($country->country_name, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Weight (<?php echo htmlspecialchars($core->weight_p, ENT_QUOTES, 'UTF-8'); ?>)</label>
                                    <input type="number" min="0.01" step="0.01" name="weight" value="<?php echo $weight > 0 ? htmlspecialchars((string)$weight, ENT_QUOTES, 'UTF-8') : ''; ?>" class="form-control" required>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button type="submit" class="btn btn-danger w-100">Calculate</button>
                                </div>
                            </div>
                        </form>

                        <?php if ($quoteError !== ''): ?>
                            <div class="alert alert-warning mt-4 mb-0"><?php echo htmlspecialchars($quoteError, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php elseif ($quoteResult): ?>
                            <div class="alert alert-success mt-4 mb-0">
                                <div><strong>Base price:</strong> <?php echo htmlspecialchars($core->currency, ENT_QUOTES, 'UTF-8'); ?> <?php echo number_format((float)$quoteResult->price, 2); ?></div>
                                <div><strong>Range:</strong> <?php echo number_format((float)$quoteResult->initial_range, 2); ?> - <?php echo number_format((float)$quoteResult->final_range, 2); ?></div>
                                <div><strong>Tariff ID:</strong> <?php echo (int)$quoteResult->id; ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
