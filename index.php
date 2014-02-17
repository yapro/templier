<?php
error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR);
define('_', '~'.md5(uniqid(time())).'~');// спец. хэш
setlocale(LC_ALL, 'ru_RU.UTF-8');
mb_internal_encoding("UTF-8");
@session_start();// заводим сессию

if(mb_substr($_SERVER['DOCUMENT_ROOT'], -1)=='/'){ $_SERVER['DOCUMENT_ROOT'] = mb_substr($_SERVER['DOCUMENT_ROOT'], 0, -1); }

class Templier {

    private $document = '';

    private $increment = 0;
    private $before = array();
    private $after = array();

    /**
     * сохраняет заданную строку в массив $this->before, 
     * добавляет инкремент строки (для ее восстановления) в массив $GLOBALS['HTML']['after'], 
     * и возвращает инкремент для замены данной строки
     * @param $s
     * @return string
     */
    function htmlSave($s){
        $s = $this->realslash( stripslashes($s) );
        $this->increment++;
        $this->before[ $this->increment ] = $s;
        return $this->after[ $this->increment ] = '['._.$this->increment.']';
    }

    /**
     * находит в тексте ИНКРЕМЕНТ строки, и заменяет его на реальные данные строки из массива $this->before
     * @param $s
     * @return mixed
     */
    private function htmlBack($s){
        if($s && $this->after && $this->before){
            $level = 0;
            while($s && mb_stristr($s, '[~') && $level<10){
                $level++;
                $s = str_replace($this->after, $this->before, $s);
            }
        }
        return $s;
    }

    /**
     * удаляет возможное автоматическое эскепирование текста в POST и GET запросах
     * @param $s
     * @return mixed
     */
    private function realslash($s){
        if(ini_get('magic_quotes_gpc')=='1'){// если \ заменяется на \\, производим обратную замену
            $s = str_replace(_, "\\", str_replace("\\", '', str_replace("\\\\", _, $s)));
            /*
            1. меняем ~SuniqueS~ на \\
            2. уничтожаем \
            3. меняем ~SuniqueS~ на \
            */
        }
        return $s;
    }

    /**
     * возвращает полный путь к файлу (вспомогательная ф-я для ф-ии подобной content)
     * @param string $file_path
     * @return string
     */
    private function path($file_path=''){

		if(!$file_path){ return ''; }

		if(mb_substr($file_path,0,1)=='/'){
			return $file_path;
		}else{
			return $_SERVER['DOCUMENT_ROOT'].'/'.$file_path;
		}
	}

    /**
     * проверка и вставка по найденному
     * @param string $system_document
     * @param int $level
     * @return string
     */
    private function content(&$system_document='', $level=0){
		
		if(!$system_document){ return ''; }
		
        // находим то, что между { и } то есть находим имена файлов в которых тоже надо порыться    {~$s='5'; function1();.filename.code~}
        preg_match_all('/(?<={~).+?(?=~})/i', $system_document, $system_found_names);
		
        if($system_found_names['0']){
			
            foreach($system_found_names['0'] as $system_name){// пробегаем по массиву
				
				$system_ex = array_reverse( explode('.', $system_name) );
				
				$system_processing = $system_ex['0'];

                $document = '';
				
				switch ($system_ex['0']){

					case 'html':// шаблон
						
						$document = @file_get_contents( dirname(__FILE__).'/templates/'.$system_name );
						break;
						
					case 'php':// скрипт ИЛИ пхп-код~AND~скрипт
						
						ob_start();
						unset($system_filename, $system_ex_code, $system_ex_string_n);
						$system_filename = $system_name;
						$system_ex_code = array_reverse(explode('~AND~', $system_name));
						if($system_ex_code['1']){
							$system_ex_string_n = explode("\n", $system_ex_code['1']);
							if($system_ex_string_n){
								foreach($system_ex_string_n as $system_ex_string_v){
									if($system_ex_string_v){
										eval($system_ex_string_v);
									}
								}
							}
							$system_filename = $system_ex_code['0'];
						}
						$system_filename = (strstr($system_filename,'/')? '' : 'inner/').$system_filename;
						$system_filename = $this-> path($system_filename);
						if(is_file($system_filename)){
							include($system_filename);
						}
						$document .= ob_get_contents();
						ob_end_clean();
						break;
						
					case '$':// вычисляем строку как PHP-код
						
						ob_start();
						$code = '';
						for($i=1; $i<count($system_ex); $i++){ $code = $system_ex[$i].'.'.$code; }
						$system_ex_string_n = explode("\n", mb_substr($code,0,-1) );
						if($system_ex_string_n){
							foreach($system_ex_string_n as $system_ex_string_v){
								if($system_ex_string_v){
									eval ($system_ex_string_v);
								}
							}
						}
						$document .= ob_get_contents();
						ob_end_clean();
						break;
						
					default:
						
						if(mb_substr($system_name, 0, 1)=='$'){// php-переменная
							
							if(!$this->depth[$system_name] || $this->depth[$system_name]<7){// 7 - максимальный уровень вложенности
								
								eval('$document = '.$system_name.';');
								
								if(!$document){// даем возможность проверить данный код в более глубоком уровне вложенности
									$document = '{~'.$system_name.'~}'; 
									$this->depth[$system_name]++;
								}
							}
							
						}else{// просто выставляем пустое значение
							$document = '';
						}
				}
				$system_document = str_replace('{~'.$system_name.'~}', $document, $system_document);
				/*
				log_("Уровень вложенности: ".$level.
				"\n--------------\n".
				"Проверка кода:\n{~".$system_name."~}".
				"\n----------------------\n".
				"Получаемое содержание:\n".$document.
				"\n------------------------\n".
				"Cодержание после замены:\n".$system_document);
				*/
				$system_ex = $document = '';
			}
			if($level>25){
				$system_document .= '<h1 style="color: #FF0000">Внимание: уровень вложенности привысил отметку 25</h1>';
			}else if(stristr($system_document, '{~')){
				$level++;
				$this-> content($system_document, $level);
			}
        }
    }

    /**
     * очищает текст от кода, который непозволителен в выводе атрибутов HTML-тегов и мнемонизирует его
     * @param string $text
     * @param bool $dont_htmlspecialchars - мнемонизацию можно отключить
     * @return mixed|string
     */
    private function clear($text='', $dont_htmlspecialchars=false){
		
		if(!$text){ return ; }
		
		// формируем текст
		$close_tags = array("'<\!\-\-(.+)\-\-\>'sUi", "'<noindex(.+)noindex>'sUi", "'<style(.+)style>'sUi", "'<script(.+)script>'sUi", "'&nbsp;'", '/\{\~(.+)\~\}/sUi', "'<(.+)>'sUi");

        $replace_modifiers = array("\n","\t","\r","\f");// заменяемые модификторы используемые в большинстве случаев
		
		// очищенный текст
		$text = str_replace($replace_modifiers, ' ', strip_tags( preg_replace($close_tags, ' ', $text) ) );
		
		$text = $dont_htmlspecialchars? $text :  htmlspecialchars( $text );
		
		// удяляем лишние пробелы
		$text = preg_replace("/[\s]{2,}/u", " ", $text);
		
		return $text;
	}

    /**
     * формирует переменные мета-данных
     */
    private function meta(){
		
		if(!$this->systemMeta){// если мета-данные не указаны - находим их по содержанию
			preg_match_all('/<!--MetaData-->(.+)<!--\/MetaData-->/sUiu', $this->document, $meta);
			if($meta['1']){
				foreach($meta['1'] as $v){
					if($v){ $this->systemMeta .= $v.' '; }
				}
			}
		}

        $title = $keywords = $description = '';
        $text = $this-> clear($this->systemMeta);
		
		if($text){
			
            $word = explode(' ', $text);// разбираем текст по словам
            
            $x = 0;
            for($i=0; ($i<100 || $x<500); $i++){// собираем данные
				
				if($word[$i]){
					
					if($i<=25 && (!$this->msie || mb_strlen($title.$word[$i], 'utf-8')<128)){ $title .= $word[$i].' '; }// 127 символов - максимум который IE может поместить в Избранное
					
					if($i<=50 && mb_strlen($word[$i])>1){ $keywords .= $word[$i].', '; }
					
					if(mb_strlen($description)<200){ $description .= $word[$i].' '; }
				}
				$x++;
            }
		}

		$titleData = str_replace('&amp;', '&', trim($title));
		if(mb_substr($titleData,-1)=='\\'){ $titleData = mb_substr($titleData,0,-1); }
		$this->document = str_replace('[~title~]', $titleData, $this->document);

		$descriptionData = trim($description);
		if(mb_substr($descriptionData,-1)=='\\'){ $descriptionData = mb_substr($descriptionData,0,-1); }
		$this->document = str_replace('[~description~]', $descriptionData, $this->document);
		
		$keywordsData = mb_substr(trim($keywords), 0, -1);// удаляем последнюю запятую
		$keywordsData = preg_replace("'\&(.+)\;'sUi", '',
            str_replace(array(')', ' ('), '',
                str_replace('.,', ',',
                    str_replace(',,', ',',
                        str_replace(':,', ',', $keywordsData)))));
		if(mb_substr($keywordsData,-1)=='\\'){ $keywordsData = mb_substr($keywordsData,0,-1); }
		$this->document = str_replace('[~keywords~]', $keywordsData, $this->document);
    }

    /**
     * выводит необходимые хедеры
     * @param array $data
     */
    private function headers($data=array()){
		
		// регулировка кэширования в браузерах
		$ETag = 'ETagHash';//.md5($_SERVER['REQUEST_URI']);
		if(!$_COOKIE[$ETag] || $_SERVER['REQUEST_METHOD']=='POST'){// устанавливается в первый раз при заходе на страницу
			$etag_hash = md5(time());
			setcookie($ETag, $etag_hash, (time()+86400), '/');
		}else{
			$etag_hash = $_COOKIE[$ETag];
		}
		header('ETag: "'.str_replace('"','\"',$etag_hash).'"');
		
		@header('Content-type:'.($data['Content-type']? $data['Content-type'] : 'text/html').'; charset=UTF-8');// кодировка страницы
		
		// Время кэширования (дата времени с которого начинается кэширование со стороны браузера)
		@header("Expires: ".gmdate("D, d M Y H:i:s", time() )." GMT");
		
		// количество времени кэширования страницы со стороны браузера (в секундах)
		@header("Cache-Control: post-check=0,pre-check=0");

		// Дата последнего изменения страницы
		@header("Last-Modified: ".gmdate("D, d M Y H:i:s", ($data['Last-Modified']? $data['Last-Modified'] : (time()-15000) ) )." GMT");
		
    }

    /**
     * сохраняем данные в которых нельзя производить замены
     */
    private function beforeLinksReplace(){
		
		if($this->document){
    		
    		$body = spliti('<body', $this->document);
			if($body['1']){
				$doc = $body['1'];
			}else{
				$doc = $body['0'];
			}
			
			// метод замены строк содержащих символ " пока не разработан!
			
			if($escape_script = preg_replace('/<script(.+)<\/script>/sUei', "$this->htmlSave('<script\\1</script>')", $doc)){
				$doc = $escape_script;
			}
			if($escape_css = preg_replace('/<style(.+)<\/style>/sUei', "$this->htmlSave('<style\\1</style>')", $doc)){
				$doc = $escape_css;
			}
			if($escape_textarea = preg_replace('/<textarea(.+)<\/textarea>/sUei', "$this->htmlSave('<textarea\\1</textarea>')", $doc)){
				$doc = $escape_textarea;
			}
			if($escape_input = preg_replace('/<input(.+)>/sUei', "$this->htmlSave('<input\\1>')", $doc)){
				$doc = $escape_input;
			}
			if($escape_img = preg_replace('/<img(.+)>/sUei', "$this->htmlSave('<img\\1>')", $doc)){
				$doc = $escape_img;
			}
			if($escape_a = preg_replace('/<!--NoReplace-->(.+)<!--\/NoReplace-->/sUei', "$this->htmlSave('\\1')", $doc)){
				$doc = $escape_a;
			}
			if($body['1']){
				$this->document = $body['0'].'<body'.$doc;
			}else{
				$this->document = $doc;
			}
		}
    }

    /**
     * собственный метод закавычивания символов регулярного выражения
     * @param $str - текстовые данные
     * @return mixed
     */
    private function preg_quote_($str){
        $str = str_replace('-', '\-', str_replace('_', '\_', str_replace("'", "\\'", preg_quote($str, "/"))));
        return $str;
    }

	function __construct(){
		
		$tpl = ($_SERVER['REQUEST_URI']=='/')? '/index.html' : $_SERVER['REQUEST_URI'];

        $filePath = $_SERVER['DOCUMENT_ROOT'].'/templates'.$tpl;
		
		// начинаю построение документа
		$this->document = @file_get_contents($filePath);
		
		if(!$this->document){ $this-> headers(); echo 'Шаблон страницы не найден или не имеет содержания!'; exit; }

		$this-> content($this->document);// получаем содержание страницы

        $this-> beforeLinksReplace();// сохраняем данные в которых нельзя производить замены

        $preg_quote_host = preg_quote_($_SERVER['HTTP_HOST']);

        if($doc = preg_replace('/<a(.+)href=("|\'|)http:\/\/(?!'.$preg_quote_host.'|www\.'.$preg_quote_host.')(.*)("|\'|\s|)>/sUi', '<a$1href=$2http://'.$_SERVER['HTTP_HOST'].'/outer/r.php?to=http://$3$4 target=_blank>', $this->document)){
            $this->document = $doc;
        }

        $this->document = $this->htmlBack($this->document);// преобразуем запрещенный код в нормальный вид

		$this->document = str_replace("\xEF\xBB\xBF", '', $this->document);// удаляем BOM-символы

		$this-> meta();// получем мета-данные

		$this-> headers( array('Last-Modified' => filemtime($filePath) ) );// отправялем заголовки

		echo $this->document;// выводим страницу
	}
}

$system = new Templier();