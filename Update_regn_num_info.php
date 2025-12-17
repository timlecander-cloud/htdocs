<?php
require 'db_connection.php';

$conn = getDbConnection();
if (!$conn) {
    die("Connection failed");
}

// Handle search by regn_num
if (isset($_POST['search'])) {
    $regn_num = $_POST['regn_num'];

    $query = "SELECT regn_num, first_name, last_name, house_num, pre_dir, street_name, street_type, city, zip_code, precinct, 
                     county_supervisor, city_council_ward, full_township, unit_type, unit_num, latitude, longitude
              FROM persons4 
              WHERE regn_num = $1";
    $result = pg_query_params($conn, $query, array($regn_num));
}

// Handle update of multiple fields (excluding first_name & last_name)
if (isset($_POST['update'])) {
    $regn_num = $_POST['regn_num'];

    // Fields allowed to be updated
    $fields = [
        'house_num', 'pre_dir', 'street_name', 'street_type', 'city',
        'zip_code', 'precinct', 'county_supervisor', 'city_council_ward',
        'full_township', 'unit_type', 'unit_num', 'latitude', 'longitude'
    ];

    $updates = [];
    $values = [];
    $i = 1;

    foreach ($fields as $field) {
        if (!empty($_POST[$field])) {
            $updates[] = "$field = $" . $i;
            $values[] = $_POST[$field];
            $i++;
        }
    }

    if (!empty($updates)) {
        $sql = "UPDATE persons4 SET " . implode(", ", $updates) . " WHERE regn_num = $" . $i;
        $values[] = $regn_num;
        pg_query_params($conn, $sql, $values);
        echo "Record updated successfully!";
    } else {
        echo "No fields provided to update.";
    }
}

// Handle latitude/longitude lookup from addresses table
if (isset($_POST['lookup_latlng'])) {
    $house_num = $_POST['house_num'];
    $pre_dir   = $_POST['pre_dir'];
    $street    = $_POST['street_name'];
    $stype     = $_POST['street_type'];
    $city      = $_POST['city'];
    $zip       = $_POST['zip_code'];

    $query = "SELECT latitude, longitude 
              FROM addresses 
              WHERE add_number = $1 
                AND st_predir = $2 
                AND st_name = $3 
                AND st_postyp = $4 
                AND post_city = $5 
                AND zip_code = $6";

    $lookup_result = pg_query_params($conn, $query, array($house_num, $pre_dir, $street, $stype, $city, $zip));
    if ($lookup_result && pg_num_rows($lookup_result) > 0) {
        $coords = pg_fetch_assoc($lookup_result);
        echo "Lookup successful: Latitude = " . htmlspecialchars($coords['latitude']) . 
             ", Longitude = " . htmlspecialchars($coords['longitude']);
    } else {
        echo "No matching address found for lookup.";
    }
}
?>

<!-- HTML Form -->
<form method="post">
    Registration Number: <input type="text" name="regn_num" required>
    <button type="submit" name="search">Search</button>
</form>

<?php if (isset($result) && pg_num_rows($result) > 0) : ?>
    <?php $row = pg_fetch_assoc($result); ?>
    <table border="1">
        <tr>
            <th>Regn_num</th><th>First Name</th><th>Last Name</th><th>House #</th><th>Pre Dir</th>
            <th>Street Name</th><th>Street Type</th><th>City</th><th>Zip</th><th>Precinct</th>
            <th>County Supervisor</th><th>City Council Ward</th><th>Full Township</th><th>Unit Type</th><th>Unit #</th>
            <th>Latitude</th><th>Longitude</th>
        </tr>
        <tr>
            <td><?= htmlspecialchars($row['regn_num']) ?></td>
            <td><?= htmlspecialchars($row['first_name']) ?></td>
            <td><?= htmlspecialchars($row['last_name']) ?></td>
            <td><?= htmlspecialchars($row['house_num']) ?></td>
            <td><?= htmlspecialchars($row['pre_dir']) ?></td>
            <td><?= htmlspecialchars($row['street_name']) ?></td>
            <td><?= htmlspecialchars($row['street_type']) ?></td>
            <td><?= htmlspecialchars($row['city']) ?></td>
            <td><?= htmlspecialchars($row['zip_code']) ?></td>
            <td><?= htmlspecialchars($row['precinct']) ?></td>
            <td><?= htmlspecialchars($row['county_supervisor']) ?></td>
            <td><?= htmlspecialchars($row['city_council_ward']) ?></td>
            <td><?= htmlspecialchars($row['full_township']) ?></td>
            <td><?= htmlspecialchars($row['unit_type']) ?></td>
            <td><?= htmlspecialchars($row['unit_num']) ?></td>
            <td><?= htmlspecialchars($row['latitude']) ?></td>
            <td><?= htmlspecialchars($row['longitude']) ?></td>
        </tr>
    </table>

    <!-- Update row (no first_name/last_name fields) -->
    <form method="post">
        <input type="hidden" name="regn_num" value="<?= htmlspecialchars($row['regn_num']) ?>">
        <table border="1">
            <tr>
                <td><input type="text" name="house_num" placeholder="House #"></td>
                <td><input type="text" name="pre_dir" placeholder="Pre Dir"></td>
                <td><input type="text" name="street_name" placeholder="Street Name"></td>
                <td><input type="text" name="street_type" placeholder="Street Type"></td>
                <td><input type="text" name="city" placeholder="City"></td>
                <td><input type="text" name="zip_code" placeholder="Zip"></td>
                <td><input type="text" name="precinct" placeholder="Precinct"></td>
                <td><input type="text" name="county_supervisor" placeholder="County Supervisor"></td>
                <td><input type="text" name="city_council_ward" placeholder="City Council Ward"></td>
                <td><input type="text" name="full_township" placeholder="Full Township"></td>
                <td><input type="text" name="unit_type" placeholder="Unit Type"></td>
                <td><input type="text" name="unit_num" placeholder="Unit #"></td>
                <td><input type="text" name="latitude" placeholder="Latitude"></td>
                <td><input type="text" name="longitude" placeholder="Longitude"></td>
                <td>
                    <button type="submit" name="update">Update</button>
                    <button type="submit" name="lookup_latlng">Lookup Lat/Lng</button>
                </td>
            </tr>
        </table>
    </form>
<?php elseif (isset($result)) : ?>
    <p>No record found for that registration number.</p>
<?php endif; ?>
