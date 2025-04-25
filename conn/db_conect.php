<?php
    function loadEnv($path)
    {
        if (!file_exists($path)) {
            throw new Exception(".env file not found at $path");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            $value = trim($value, "\"'");
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    // Cargar archivo .env
    loadEnv(__DIR__ . '/../../.env'); 

    $servername = getenv('DB_HOST');
    $username = getenv('DB_USER');
    $password = getenv('DB_PASS');
    $dbname   = getenv('DB_DATABASE');

    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8");
?>