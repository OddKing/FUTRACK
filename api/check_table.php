<?php
require_once 'config.php';
try {
    $res = $pdo->query("DESCRIBE users");
    while($row = $res->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
