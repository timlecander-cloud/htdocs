<?php
function getDbConnection() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "pgsql:host=localhost;dbname=Winneshiek";
        $user = "postgres";
        $password = "(163Lydia)";

        try {
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    return $pdo;
}
?>
