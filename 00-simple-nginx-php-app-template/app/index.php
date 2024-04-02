<?php

phpinfo();

// $host = "mysql"; //! El host es el nombre del servicio, presente en docker-compose.yml
// $dbname = "mysqldb";
// $charset = "utf8";
// $port = "3306";

// try {
//     $pdo = new PDO(
//         dsn: "mysql:host=$host;dbname=$dbname;charset=$charset;port=$port",
//         username: "root",
//         password: "myrootpassword",
//     );

//     // $persons = $pdo->query("SELECT * FROM Persons");

//     echo '<p>Hola</p>';
//     echo '<pre>';
//     // foreach ($persons->fetchAll(PDO::FETCH_ASSOC) as $person) {
//     //     print_r($person);
//     // }
//     echo '</pre>';

// } catch (PDOException $e) {
//     throw new PDOException(
//         message: $e->getMessage(),
//         code: (int)$e->getCode()
//     );
// }