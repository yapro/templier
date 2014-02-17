<?php
error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR);
setlocale(LC_ALL, 'ru_RU.UTF-8');
mb_internal_encoding("UTF-8");
@header("HTTP/1.0 404 Not Found");
@header('Content-type:text/html; charset=UTF-8');
echo '<H2 style="text-align: center; padding-top: 150px;">Страница не найдена</H2>';
?>