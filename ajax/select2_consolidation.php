<?php
// ajax/select2_consolidation.php
// Search consolidations by c_no, c_prefix, or combined prefix+no (handles "PREFIX123", "PREFIX 123", "PREFIX-123", "PREFIX.123")
require_once("../loader.php");

$db = new Conexion();

// sanitize incoming query param (select2 sends 'q')
$search = isset($_REQUEST['q']) ? cdp_sanitize($_REQUEST['q']) : '';
$data = [];

if ($search === '') {
    // Return empty array when no search term (prevents huge results)
    echo json_encode($data);
    exit;
}

// prepare wildcard patterns for various combination forms
$like = '%' . $search . '%';

// Build SQL to match:
// - c_no LIKE '%search%'
// - c_prefix LIKE '%search%'
// - CONCAT(c_prefix, c_no) LIKE '%search%'         (PREFIX123)
// - CONCAT(c_prefix, ' ', c_no) LIKE '%search%'    (PREFIX 123)
// - CONCAT(c_prefix, '-', c_no) LIKE '%search%'    (PREFIX-123)
// - CONCAT(c_prefix, '.', c_no) LIKE '%search%'    (PREFIX.123)
$sql = "
SELECT 
  consolidate_id,
  c_prefix,
  c_no
FROM cdb_consolidate
WHERE
  (c_no LIKE :like
   OR c_prefix LIKE :like
   OR CONCAT(IFNULL(c_prefix,''), IFNULL(c_no,'')) LIKE :like
   OR CONCAT(IFNULL(c_prefix,''), ' ', IFNULL(c_no,'')) LIKE :like
   OR CONCAT(IFNULL(c_prefix,''), '-', IFNULL(c_no,'')) LIKE :like
   OR CONCAT(IFNULL(c_prefix,''), '.', IFNULL(c_no,'')) LIKE :like
  )
LIMIT 50
";

$db->cdp_query($sql);
$db->bind(':like', $like);
$db->cdp_execute();

$rows = $db->cdp_registros();

foreach ($rows as $row) {
    // Build a friendly label: prefer prefix+no, fall back to id
    $prefix = trim((string)$row->c_prefix);
    $no     = trim((string)$row->c_no);

    if ($prefix !== '' && $no !== '') {
        // common display form: PREFIX-123 (you can change formatting if needed)
        $label = $prefix . $no;
    }
    // elseif ($no !== '') {
    //     $label = $no . ' - #' . $row->consolidate_id;
    // } elseif ($prefix !== '') {
    //     $label = $prefix . ' - #' . $row->consolidate_id;
    // } else {
    //     $label = 'Consolidation #' . $row->consolidate_id;
    // }

    $data[] = ['id' => $row->consolidate_id, 'text' => $label];
}

echo json_encode($data);
