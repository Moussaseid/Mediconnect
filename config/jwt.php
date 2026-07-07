<?php
// config/jwt.php — Configuration JWT

return [
    'secret'     => ($_ENV['JWT_SECRET']     ?? '') ?: (getenv('JWT_SECRET')     ?: 'mediconnect_dev_secret_change_in_production'),
    'expires_in' => (int)(($_ENV['JWT_EXPIRES_IN'] ?? '') ?: (getenv('JWT_EXPIRES_IN') ?: 3600)),
    'algorithm'  => 'HS256',
];
