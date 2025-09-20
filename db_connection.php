<?php
function getDbConnection() {
    $GLOBALS['dbConnection'] = $GLOBALS['dbConnection'] ?? null;
    if ($GLOBALS['dbConnection'] === null) {
        $dbconn = pg_connect(
            "host=localhost " .
            "dbname=Winneshiek " .
            "user=postgres " .
            "password=(163Lydia)"
        );

        if (!$dbconn) {
            throw new Exception("Database connection failed: " . pg_last_error());
        }

        $GLOBALS['dbConnection'] = $dbconn;
    }
    return $GLOBALS['dbConnection'];
}
?>

