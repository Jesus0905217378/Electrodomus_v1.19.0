<?php
// app/config.php
return [
  'db' => [
    'host'    => '127.0.0.1',   // o 'localhost'
    'name'    => 'electrica_db',
    'user'    => 'root',
    'pass'    => '',            // en XAMPP suele ir vacío
    'charset' => 'utf8mb4',
  ],
  'app' => [
    // si tu carpeta es C:\xampp\htdocs\electrica y el index está en /public
    'base_url' => '/electrodomus/public'
  ]
];
