<?php
error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR | E_NOTICE);
setlocale(LC_ALL, 'ru_RU.UTF-8');
mb_internal_encoding("UTF-8");
@session_start();// заводим сессию

define('_', '~'.md5(uniqid(time())).'~');// спец. хэш

if(mb_substr($_SERVER['DOCUMENT_ROOT'], -1)=='/'){ $_SERVER['DOCUMENT_ROOT'] = mb_substr($_SERVER['DOCUMENT_ROOT'], 0, -1); }

$GLOBALS['Templier']['increment'] = 0;
$GLOBALS['Templier']['before'] = array();
$GLOBALS['Templier']['after'] = array();

/**
 * сохраняет заданную строку в массив $GLOBALS['Templier']['before'],
 * добавляет инкремент строки (для ее восстановления) в массив $GLOBALS['Templier']['after'],
 * и возвращает инкремент для замены данной строки
 * @param $s
 * @return string
 */
function htmlSave($s){
    // в связи с регуляркой данные поступают в эскепированном виде, поэтому мы их правильно расслэшиваем (оставляя слэши там где нужно)
    $s = str_replace('Save'._.'escapes', "\'", stripslashes( str_replace("\'", 'Save'._.'escapes', $s)));
    $GLOBALS['Templier']['increment']++;
    $GLOBALS['Templier']['before'][$GLOBALS['Templier']['increment']] = $s;
    return $GLOBALS['Templier']['after'][ $GLOBALS['Templier']['increment'] ] = '['._.$GLOBALS['Templier']['increment'].']';
}

/**
 * находит в тексте ИНКРЕМЕНТ строки, и заменяет его на реальные данные строки из массива $GLOBALS['Templier']['before']
 * @param $s
 * @return mixed
 */
function htmlBack($s){
    if($s && $GLOBALS['Templier']['after'] && $GLOBALS['Templier']['before']){
        $level = 0;
        while($s && mb_stristr($s, '[~') && $level<10){
            $level++;
            $s = str_replace($GLOBALS['Templier']['after'], $GLOBALS['Templier']['before'], $s);
        }
    }
    return $s;
}

class Templier {

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
		$text = preg_replace("/[\s]{2,}/u", ' ', $text);
		
		return $text;
	}

    private $title = '';

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    private $keywords = '';

    /**
     * @return string
     */
    public function getKeywords()
    {
        return $this->keywords;
    }

    /**
     * @param string $keywords
     */
    public function setKeywords($keywords)
    {
        $this->keywords = $keywords;
    }

    private $description = '';

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * формирует переменные мета-данных
     */
    private function meta(){

        $title = $this->getTitle();
        $keywords = $this->getKeywords();
        $description = $this->getDescription();

        if(empty($title) || empty($keywords) || empty($description)){

            $metaData = '';// находим мета-данные по содержанию

            preg_match_all('/<!--MetaData-->(.+)<!--\/MetaData-->/sUiu', $this->content, $meta);

            if($meta['1']){

                foreach($meta['1'] as $v){
                    if(!empty($v)){ $metaData .= $v.' '; }
                }

                $this->content = str_replace('<!--MetaData-->', '', str_replace('<!--/MetaData-->', '', $this->content));
            }

            $name = '';// находим название страницы
            preg_match_all('/<h1(.*)>(.+)<\/h1>/sUiu', $metaData, $h1);

            if(isset($h1['2']) && !empty($h1['2'])){
                $name = $h1['2']['0'].'.';
                $metaData = str_replace($h1['0']['0'], '', $metaData);
            }

            $text = $name . $this->clear($metaData);

            if($text){

                $msie = preg_match('/(?i)msie [1-8]/',$_SERVER['HTTP_USER_AGENT']);// детектим Internet Explorer

                if($meta['1']){

                    foreach($meta['1'] as $v){
                        if(!empty($v)){ $metaData .= $v.' '; }
                    }

                    $this->content = str_replace('<!--MetaData-->', '', str_replace('<!--/MetaData-->', '', $this->content));
                }

                $word = explode(' ', $text);// разбираем текст по словам

                $titleText = $keywordsText = $descriptionText = '';

                $x = 0;
                for($i=0; ($i<100 || $x<500); $i++){// собираем данные

                    if(isset($word[$i])){

                        // 127 символов - максимум который IE может поместить в Избранное
                        if($i<=25 && (!$msie || mb_strlen($titleText.$word[$i], 'utf-8')<128)){ $titleText .= $word[$i].' '; }

                        if($i<=50 && mb_strlen($word[$i])>1){ $keywordsText .= $word[$i].', '; }

                        if(mb_strlen($descriptionText)<200){ $descriptionText .= $word[$i].' '; }
                    }
                    $x++;
                }

                if(empty($title)){ $title = $titleText; }
                if(empty($keywords)){ $keywords = $keywordsText; }
                if(empty($description)){ $description = $descriptionText; }
            }
        }

		$titleData = str_replace('&amp;', '&', trim($title));
		if(mb_substr($titleData,-1)=='\\'){ $titleData = mb_substr($titleData,0,-1); }
		$this->content = str_replace('[~title~]', $titleData, $this->content);

		$descriptionData = trim($description);
		if(mb_substr($descriptionData,-1)=='\\'){ $descriptionData = mb_substr($descriptionData,0,-1); }
		$this->content = str_replace('[~description~]', $descriptionData, $this->content);
		
		$keywordsData = trim($keywords);
        // удаляем последнюю запятую
        if(mb_substr($keywordsData,-1)==','){ $keywordsData = mb_substr($keywordsData,0,-1); }
		$keywordsData = preg_replace("'\&(.+)\;'sUi", '',
            str_replace(array(')', ' ('), '',
                str_replace('.,', ',',
                    str_replace(',,', ',',
                        str_replace(':,', ',',
                            str_replace('?,', ',', $keywordsData))))));
        // если в конце слэш - удаляем его
		if(mb_substr($keywordsData,-1)=='\\'){ $keywordsData = mb_substr($keywordsData,0,-1); }
		$this->content = str_replace('[~keywords~]', $keywordsData, $this->content);
    }

    /**
     * выводит необходимые хедеры
     * @param array $data
     */
    private function headers($data=array()){
		
		// регулировка кэширования в браузерах
		$ETag = 'ETagHash';//.md5($_SERVER['REQUEST_URI']);
		if(!isset($_COOKIE[$ETag]) || $_SERVER['REQUEST_METHOD']=='POST'){// устанавливается в первый раз при заходе на страницу
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
		
		if($this->content){
    		
    		$body = preg_split("/<body/i", $this->content);
			if(isset($body['1'])){
				$doc = $body['1'];
			}else{
				$doc = $body['0'];
			}
			
			// метод замены строк содержащих символ " пока не разработан!
			
			if($escape_script = preg_replace('/<script(.+)<\/script>/sUei', "htmlSave('<script\\1</script>')", $doc)){
				$doc = $escape_script;
			}
			if($escape_css = preg_replace('/<style(.+)<\/style>/sUei', "htmlSave('<style\\1</style>')", $doc)){
				$doc = $escape_css;
			}
			if($escape_textarea = preg_replace('/<textarea(.+)<\/textarea>/sUei', "htmlSave('<textarea\\1</textarea>')", $doc)){
				$doc = $escape_textarea;
			}
			if($escape_input = preg_replace('/<input(.+)>/sUei', "htmlSave('<input\\1>')", $doc)){
				$doc = $escape_input;
			}
			if($escape_img = preg_replace('/<img(.+)>/sUei', "htmlSave('<img\\1>')", $doc)){
				$doc = $escape_img;
			}
			if($escape_a = preg_replace('/<!--NoReplace-->(.+)<!--\/NoReplace-->/sUei', "htmlSave('\\1')", $doc)){
				$doc = $escape_a;
			}
			if( isset($body['1']) ){
				$this->content = $body['0'].'<body'.$doc;
			}else{
				$this->content = $doc;
			}
		}
    }

    /**
     * @param $error_message
     * @throws Exception
     */
    function header404($error_message = ''){

        header("HTTP/1.0 404 Not Found");

        // раньше была задержка в 1 сек. которая была нужна для правильного сбора JS статистики
        echo '<META HTTP-EQUIV="Refresh" CONTENT="0; URL=http://'.$_SERVER['HTTP_HOST'].'/outer/404.php">';

        $messages = array();

        if( isset($_SERVER['REQUEST_URI']) ){
            $messages[] = 'не найдена страница: '.$_SERVER['REQUEST_URI'];
            $messages[] = 'не найдена страница urldecode: '.urldecode($_SERVER['REQUEST_URI']);
        }

        if( isset($_SERVER['HTTP_REFERER']) ){
            $messages[] = 'рефферер: '.$_SERVER['HTTP_REFERER'];
            $messages[] = 'реферер urldecode: '.urldecode($_SERVER['HTTP_REFERER']);
        }

        if( !empty($error_message) ){
            $messages[] = 'пояснение: '.$error_message;
        }

        if( isset($_SERVER['REMOTE_ADDR']) ){
            $messages[] = 'IP: '.$_SERVER['REMOTE_ADDR'];
        }

        if( isset($_SERVER['HTTP_USER_AGENT']) ){
            $messages[] = 'браузер: '.$_SERVER['HTTP_USER_AGENT'];
        }

        trigger_error(implode("\n", $messages), E_USER_NOTICE);

        exit;
    }

    function header301($path = ''){

        header("location: http://".$_SERVER['HTTP_HOST'].$path, true, 301);// 301 - Ресурс окончательно перенесен
        exit;
    }

    function url($path = '', $allowGetData = false){

        $return = array();

        if( empty($path) ){
            return $return;
        }

        $REQUEST_URI = $allowGetData? current( explode('?', $_SERVER['REQUEST_URI']) ) : $_SERVER['REQUEST_URI'];

        $url = explode('/', $REQUEST_URI);

        $e = explode('/', $path);

        if( count($url) != count($e) ){
            return $return;
        }

        foreach($e as $k => $v){

            if( !isset($url[ $k ]) ){// если хоть один параметр URL не совпадает

                return array();

            }elseif( mb_substr($v,0,1) === '{' && mb_substr($v, -1) === '}' ){

                $key = mb_substr($v, 1, -1);

                $return[ $key ] = $url[ $k ];

            }elseif( $v !== $url[ $k ] ){// если хоть один параметр URL не совпадает

                return array();

            }
        }
        return $return;
    }

    private $content = '';

    /**
     * получаем содержимое переменной (нужно, когда необходимо что-то поменять в ней и следом вывести данные)
     * @return string
     */
    function getContent(){
        return $this->content;
    }

    /**
     * начинает построение документа
     * @param string $filePath
     * @return bool
     */
    function getTemplate($filePath = ''){

        if( !is_file($filePath) ){
            return false;
        }

        ob_start();
        include_once($filePath);
        $this->content = ob_get_contents();
        ob_end_clean();

    }

    private $allow_get_data_in_url = false;

    /**
     * @param boolean $allow_get_data_in_url
     */
    public function setAllowGetDataInUrl($allow_get_data_in_url){
        $this->allow_get_data_in_url = $allow_get_data_in_url;
    }

    /**
     * @return boolean
     */
    public function getAllowGetDataInUrl(){
        return $this->allow_get_data_in_url;
    }

    /**
     * @var string доменное имя сайта
     */
    private $host = '';

    /**
     * @param string $host
     */
    public function setHost($host){
        $this->host = $host;
    }

    /**
     * @return string
     */
    public function getHost(){
        return empty($this->host)? $_SERVER['HTTP_HOST'] : $this->host;
    }

	function __construct(){

        $this->getTemplate($_SERVER['DOCUMENT_ROOT'].'/before.php');// проверим наличие возможного пользовательского кода

        if( $this->getHost() !== $_SERVER['HTTP_HOST'] ){// если доменное имя указано неправильно - исправляем
            header('location: http://'. $this->getHost().$_SERVER['REQUEST_URI'], true, 301);
            exit;
        }

        if( $this->getAllowGetDataInUrl() ){// избавимся от гет-данных
            $url = current( explode('?', $_SERVER['REQUEST_URI']) );
        }else{
            $url = $_SERVER['REQUEST_URI'];
        }

        if( empty($this->content) ){

            $tpl = '/index.php';// основной файл шаблона

            if( $url === $tpl ){// попытка обратиться к главному шаблону напрямую

                $this->header301('/', 'нельзя обратиться к главному шаблону напрямую');

            }elseif( empty($url) || $url === '/' ){// обращение к главной странице сайта

                // файл шаблона не меняется

            }elseif( mb_substr($url,-1) === '/' ){// обращение к директории (урл заканчивается на слэш)

                $this->header301(mb_substr($url,0,-1), 'переадресация по слэшу в конце');

            }else{// обращение к странице сайта

                $ext = '.php';// расширение php-скриптов

                if( mb_substr($url,-4) === $ext ){// попытка обратиться к php-шаблону напрямую

                    $this->header301( mb_substr($url,0,-4) , 'нельзя обратиться к php-шаблону напрямую');

                }else{

                    $tpl = $url . $ext;

                }

            }

            $filePath = $_SERVER['DOCUMENT_ROOT'].'/templates'.$tpl;

            if( !is_file($filePath) ){
                $this->header404('Шаблон страницы не найден');
            }

            $this->getTemplate($filePath);// начинаю построение документа

            if( empty($this->content) ){
                $this->header404('Шаблон страницы пуст');
            }
        }

        $this->beforeLinksReplace();// сохраняем данные в которых нельзя производить замены

        @include_once($_SERVER['DOCUMENT_ROOT'].'/after.php');

        $preg_quote_host = preg_quote($_SERVER['HTTP_HOST'], '/');

        if($doc = preg_replace('/<a(.+)href=("|\'|)http:\/\/(?!'.$preg_quote_host.'|www\.'.$preg_quote_host.')(.*)("|\'|\s|)>/sUi', '<a$1href=$2/outer/r.php?to=http://$3$4 target=_blank>', $this->content)){
            $this->content = $doc;
        }

        $this->content = htmlBack($this->content);// преобразуем запрещенный код в нормальный вид

		$this->content = str_replace("\xEF\xBB\xBF", '', $this->content);// удаляем BOM-символы

		$this->meta();// получем мета-данные

		$this->headers( array('Last-Modified' => filemtime($filePath) ) );// отправялем заголовки

		echo $this->content;// выводим страницу
	}
}

new Templier();