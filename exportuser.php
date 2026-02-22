<?php
/*
Copyright (c) 2022-2025 Arman Jussupgaliyev
*/
if (!isset($_COOKIE['user'])) {
    http_response_code(401);
    die;
}
echo $_COOKIE['user'];