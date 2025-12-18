<?php
require 'db_connection.php';

$conn = getDbConnection();
if (!$conn) {
    die("Connection failed");
}

$display = null;
$lookup_info = '';

function loadPerson($conn, $regn_num) {
    $sql = "SELECT regn_num, first_name, last_name, house_num, pre_dir, street_name, street_type, city, zip_code, precinct, 
                   county_supervisor, city_council_ward, full_township, unit_type, unit_num, latitude, longitude
            FROM persons4 
            WHERE regn_num = $1";
    $res = pg_query_params($conn, $sql, [$regn_num]);
    if ($res && pg_num_rows($res) > 0) {
        return pg_fetch_assoc($res);
    }
    return null;
}

// Search
if (isset($_POST['search'])) {
    $regn_num = $_POST['regn_num'];
    $display = loadPerson($conn, $regn_num);
}

// Update only non-blank fields
if (isset($_POST['update'])) {
    $regn_num = $_POST['regn_num'];

    $fields = [
        'house_num', 'pre_dir', 'street_name', 'street_type', 'city',
        'zip_code', 'precinct', 'county_supervisor', 'city_council_ward',
        'full_township', 'unit_type', 'unit_num', 'latitude', 'longitude'
    ];

    $updates = [];
    $values = [];
    $i = 1;

    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            $v = trim($_POST[$f]);
            if ($v !== '') {
                $updates[] = "$f = $" . $i;
                $values[] = $v;
                $i++;
            }
        }
    }

    if ($updates) {
        $sql = "UPDATE persons4 SET " . implode(", ", $updates) . " WHERE regn_num = $" . $i;
        $values[] = $regn_num;
        pg_query_params($conn, $sql, $values);
        echo "Record updated successfully!";
    } else {
        echo "No fields provided to update.";
    }

    // Reload from DB
    $display = loadPerson($conn, $regn_num);
}

// Lookup lat/lng from addresses (zip dropped, st_predir optional)
if (isset($_POST['lookup_latlng'])) {
    $regn_num = $_POST['regn_num'];
    $house_num = trim($_POST['house_num'] ?? '');
    $pre_dir   = trim($_POST['pre_dir'] ?? '');
    $street    = trim($_POST['street_name'] ?? '');
    $stype     = trim($_POST['street_type'] ?? '');
    $city      = trim($_POST['city'] ?? '');

    $query = "SELECT latitude, longitude 
              FROM addresses 
              WHERE add_number = $1::bigint
                AND st_name ILIKE $2
                AND st_postyp ILIKE $3
                AND post_city ILIKE $4";
    $params = [$house_num, $street, $stype, $city];

    if ($pre_dir !== '') {
        $query .= " AND st_predir ILIKE $5";
        $params[] = $pre_dir;
    }
    $query .= " LIMIT 1";

    $lookup_result = pg_query_params($conn, $query, $params);

    if ($lookup_result && pg_num_rows($lookup_result) > 0) {
        $coords = pg_fetch_assoc($lookup_result);
        $lookup_info = "Lookup successful!";
        echo "<script>console.log('Lat/Lng lookup success', { lat: '" . addslashes($coords['latitude']) . "', lng: '" . addslashes($coords['longitude']) . "' });</script>";

        // Load person from DB
        $display = loadPerson($conn, $regn_num);

        // Inject looked-up values into display array
        if ($display) {
            $display['latitude']  = $coords['latitude'];
            $display['longitude'] = $coords['longitude'];
        }

        // Also place into $_POST so form inputs can be prefilled
        $_POST['latitude']  = $coords['latitude'];
        $_POST['longitude'] = $coords['longitude'];
    } else {
        $lookup_info = "No matching address found for lookup.";
        echo "<script>console.log('Lat/Lng lookup no match');</script>";

        // Keep the screen by loading person
        $display = loadPerson($conn, $regn_num);
    }
}
?>

<!-- Search form -->
<form method="post">
    Registration Number: <input type="text" name="regn_num" required value="<?= htmlspecialchars($_POST['regn_num'] ?? '') ?>">
    <button type="submit" name="search">Search</button>
</form>

<?php if (!empty($lookup_info)) : ?>
    <p><?= htmlspecialchars($lookup_info) ?></p>
<?php endif; ?>

<?php if ($display) : ?>
    <table border="1">
        <tr>
            <th>Regn_num</th><th>First Name</th><th>Last Name</th><th>House #</th><th>Pre Dir</th>
            <th>Street Name</th><th>Street Type</th><th>City</th><th>Zip</th><th>Precinct</th>
            <th>County Supervisor</th><th>City Council Ward</th><th>Full Township</th><th>Unit Type</th><th>Unit #</th>
            <th>Latitude</th><th>Longitude</th>
        </tr>
        <tr>
            <td><?= htmlspecialchars($display['regn_num']) ?></td>
            <td><?= htmlspecialchars($display['first_name']) ?></td>
            <td><?= htmlspecialchars($display['last_name']) ?></td>
            <td><?= htmlspecialchars($display['house_num']) ?></td>
            <td><?= htmlspecialchars($display['pre_dir']) ?></td>
            <td><?= htmlspecialchars($display['street_name']) ?></td>
            <td><?= htmlspecialchars($display['street_type']) ?></td>
            <td><?= htmlspecialchars($display['city']) ?></td>
            <td><?= htmlspecialchars($display['zip_code']) ?></td>
            <td><?= htmlspecialchars($display['precinct']) ?></td>
            <td><?= htmlspecialchars($display['county_supervisor']) ?></td>
            <td><?= htmlspecialchars($display['city_council_ward']) ?></td>
            <td><?= htmlspecialchars($display['full_township']) ?></td>
            <td><?= htmlspecialchars($display['unit_type']) ?></td>
            <td><?= htmlspecialchars($display['unit_num']) ?></td>
            <td><?= htmlspecialchars($display['latitude'] ?? '') ?></td>
            <td><?= htmlspecialchars($display['longitude'] ?? '') ?></td>
        </tr>
    </table>

    <!-- Update row -->
    <form method="post">
        <input type="hidden" name="regn_num" value="<?= htmlspecialchars($display['regn_num']) ?>">
        <table border="1">
            <tr>
                <td><input type="text" name="house_num" placeholder="House #" value="<?= htmlspecialchars($_POST['house_num'] ?? '') ?>"></td>
                <td><input type="text" name="pre_dir" placeholder="Pre Dir" value="<?= htmlspecialchars($_POST['pre_dir'] ?? '') ?>"></td>
                <td><input type="text" name="street_name" placeholder="Street Name" value="<?= htmlspecialchars($_POST['street_name'] ?? '') ?>"></td>
                <td><input type="text" name="street_type" placeholder="Street Type" value="<?= htmlspecialchars($_POST['street_type'] ?? '') ?>"></td>
                <td><input type="text" name="city" placeholder="City" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"></td>
                <td><input type="text" name="zip_code" placeholder="Zip" value="<?= htmlspecialchars($_POST['zip_code'] ?? '') ?>"></td>
                <td><input type="text" name="precinct" placeholder="Precinct" value="<?= htmlspecialchars($_POST['precinct'] ?? '') ?>"></td>
                <td><input type="text" name="county_supervisor" placeholder="County Supervisor" value="<?= htmlspecialchars($_POST['county_supervisor'] ?? '') ?>"></td>
                <td><input type="text" name="city_council_ward" placeholder="City Council Ward" value="<?= htmlspecialchars($_POST['city_council_ward'] ?? '') ?>"></td>
                <td><input type="text" name="full_township" placeholder="Full Township" value="<?= htmlspecialchars($_POST['full_township'] ?? '') ?>"></td>
                <td><input type="text" name="unit_type" placeholder="Unit Type" value="<?= htmlspecialchars($_POST['unit_type'] ?? '') ?>"></td>
                <td><input type="text" name="unit_num" placeholder="Unit #" value="<?= htmlspecialchars($_POST['unit_num'] ?? '') ?>"></td>
                <td><input type="text" name="latitude" placeholder="Latitude" value="<?= htmlspecialchars($_POST['latitude'] ?? '') ?>"></td>
                <td><input type="text" name="longitude" placeholder="Longitude" value="<?= htmlspecialchars($_POST['longitude'] ?? '') ?>"></td>
                <td>
                    <button type="submit" name="update">Update</button>
                    <button type="submit" name="lookup_latlng">Lookup Lat/Lng</button>
                </td>
            </tr>
        </table>
    </form>
<?php endif; ?>
