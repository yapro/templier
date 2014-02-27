<?php
error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR);
setlocale(LC_ALL, 'ru_RU.UTF-8');
mb_internal_encoding("UTF-8");

$url = explode('/outer/r.php?to=', $_SERVER['REQUEST_URI']);
$url = $url['1'];

if(mb_substr($url, 0, 4)=='http' || mb_substr($url, 0, 6)=='ftp://'){

    // функция безопасной обработки урл-пути
    function parse_url_($url){
        if($url){
            if(!mb_stristr($url, '://')){// если это /путь.html?data=x
                $url = 'http://' .$_SERVER['HTTP_HOST'].$url;// создаем полный URL
            }
            $ex = explode('://', $url);// разбиваем урл - protocol://site/путь.html?data=x
            $c=count($ex);
            if($c>1){// если урл правилен и имеет вид - protocol://site/путь.html
                $url = $ex['0'].'://'.$ex['1'];// первым делом создаем protocol://site/путь.html
                if($c>2){// если в пути имеются данные другого урл, то кодируем их
                    for($i=2; $i<$c; $i++){
                        $url .= urlencode('://'.$ex[ $i ]);
                    }
                }
                $arr = @parse_url($url);// парсим правильный урл
                return $arr;// отдаем массив
            }
        }
    }

	$parse_url = @parse_url_($url);
	
	if($parse_url['scheme'] && $parse_url['host'] && str_replace('www.', '', $parse_url['host'])!=str_replace('www.', '', $_SERVER['HTTP_HOST'])){
		
		header('location: '.$url);
		
	}else{
		header('location: /outer/404.php');// не удалось правильно распарсить
	}
}else{
	header('location: /outer/404.php');// урл не найден
}
?>