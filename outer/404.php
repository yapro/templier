<?php
/**
 * данную страницу можно оформить по-своему усмотрению
 */
error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR);
setlocale(LC_ALL, 'ru_RU.UTF-8');
mb_internal_encoding("UTF-8");
@header('Content-type:text/html; charset=UTF-8');

echo '<H2 style="text-align: center; padding-top: 150px;">Страница не найдена<br>Page not found</H2>';