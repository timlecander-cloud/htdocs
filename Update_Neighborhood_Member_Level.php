<?php
// Database connection
// $host = "localhost";
// $dbname = "your_database";
// $user = "your_user";
// $password = "your_password";

// $conn = pg_connect("host=$host dbname=$dbname user=$user password=$password");

require 'db_connection.php';

$conn = getDbConnection();

if (!$conn) {
    die("Connection failed");
}

// Handle search
if (isset($_POST['search'])) {
    $first = $_POST['first_name'];
    $last = $_POST['last_name'];

    $query = "SELECT regn_num, first_name, last_name, neighborhood_member_level 
              FROM persons4 
              WHERE first_name ILIKE $1 AND last_name ILIKE $2";
    $result = pg_query_params($conn, $query, array($first, $last));
}

// Handle update
if (isset($_POST['update'])) {
    $regn_num = $_POST['regn_num'];
    $level = $_POST['neighborhood_member_level'];

    // Ensure level is between 1 and 10
    if ($level >= 0 && $level <= 10) {
        $update = "UPDATE persons4 SET neighborhood_member_level = $1 WHERE regn_num = $2";
        pg_query_params($conn, $update, array($level, $regn_num));
        echo "Record updated successfully!";
    } else {
        echo "Invalid level. Must be between 0 and 10.";
    }
}
?>

<!-- HTML Form -->
<form method="post">
    First Name: <input type="text" name="first_name" required>
    Last Name: <input type="text" name="last_name" required>
    <button type="submit" name="search">Search</button>
</form>

<?php if (isset($result)) : ?>
    <form method="post">
        <table border="1">
            <tr><th>Regn_num</th><th>Name</th><th>Current Level</th><th>New Level</th><th>Action</th></tr>
            <?php while ($row = pg_fetch_assoc($result)) : ?>
                <tr>
                    <td><?= $row['regn_num'] ?></td>
                    <td><?= $row['first_name'] . " " . $row['last_name'] ?></td>
                    <td><?= $row['neighborhood_member_level'] ?></td>
                    <td>
                        <select name="neighborhood_member_level">
                            <?php for ($i = 0; $i <= 10; $i++) : ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </td>
                    <td>
                        <input type="hidden" name="regn_num" value="<?= $row['regn_num'] ?>">
                        <button type="submit" name="update">Update</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </form>
<?php endif; ?>
