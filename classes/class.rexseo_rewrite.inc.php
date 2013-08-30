<?php
/**
 * RexSEO - URLRewriter Addon
 *
 * @link https://github.com/gn2netwerk/rexseo
 *
 * @author dh[at]gn2-netwerk[dot]de Dave Holloway
 * @author code[at]rexdev[dot]de jdlx
 *
 * Based on url_rewrite Addon by
 * @author markus.staab[at]redaxo[dot]de Markus Staab
 *
 * @package redaxo 4.3.x/4.4.x/4.5.x
 * @version 1.6.3
 */

define('REXSEO_PATHLIST', $REX['INCLUDE_PATH'].'/generated/files/rexseo_pathlist.php');


class RexseoRewrite
{
  private $use_levenshtein;
  private $use_params_rewrite;


  /* constructor */
  function RexseoRewrite($use_levenshtein = false, $use_params_rewrite = false)
  {
    $this->use_levenshtein = $use_levenshtein;
    $this->use_params_rewrite = $use_params_rewrite;
  }



  /**
  * LOGERROR()
  */
  public function logError($err_txt=false,$err_type=false,$trace=false)
  {
    global $REX;

    if(!$err_type)
      $err_type = 'E_USER_NOTICE';

    $err_txt = 'CLASS REXSEO_REWRITE: '.$err_txt.'.';
    trigger_error($err_txt, $err_type);

    if($trace!=false)
    {
      $logfile = $REX['INCLUDE_PATH'].'/addons/rexseo/rexseo.log';
      $log_content = file_exists($logfile) ? rex_get_file_contents($logfile) : '';
      $log_content = $log_content!='empty..' ? $log_content : '';

      $new_entry = str_pad('### '.date("d.m.Y H:i").' ',80, "#").PHP_EOL.$err_txt.PHP_EOL;
      if(is_array($trace))
        $new_entry .= 'BACKTRACE:'.PHP_EOL.var_export($trace,true).PHP_EOL.PHP_EOL;

      rex_put_file_contents($logfile,$new_entry .$log_content);
    }
  }


  /**
  * RESOLVE()
  *
  * resolve url to ARTICLE_ID & CLANG,
  * resolve rewritten params back to GET/REQUEST
  */
  function resolve()
  {
    global $REX, $REXSEO_URLS, $REXSEO_IDS;

    if(!file_exists(REXSEO_PATHLIST))
    {
      rexseo_generate_pathlist(array());
    }

    require_once(REXSEO_PATHLIST);

    if(!$REX['REDAXO'])
    {
      $article_id      = -1;
      $clang           = $REX['CUR_CLANG'];
      $start_id        = $REX['START_ARTICLE_ID'];
      $notfound_id     = $REX['NOTFOUND_ARTICLE_ID'];

      $params_starter  = $REX['ADDON']['rexseo']['settings']['params_starter'];
      $install_subdir  = $REX['ADDON']['rexseo']['settings']['install_subdir'];
      $allow_articleid = $REX['ADDON']['rexseo']['settings']['allow_articleid'];
      $homelang        = $REX['ADDON']['rexseo']['settings']['homelang'];


      // TRY IMMEDIATE MATCH OF REQUEST_URI AGAINST PATHLIST..
      if(self::resolve_from_pathlist(ltrim($_SERVER['REQUEST_URI'],'/')))
      {
        return;
      }


      // IF NON_REWRITTEN URLS ALLOWED -> USE ARTICLE_ID FROM REQUEST
      if ($allow_articleid != 0 && rex_get('article_id', 'int') > 0)
      {
        if($allow_articleid == 1)
        {
          $redirect = array('id'    =>rex_request('article_id','int'),
                            'clang' =>rex_request('clang','int',$clang),
                            'status'=>301);
          return self::redirect($redirect); /*todo: include params*/
        }
        else
        {
          return self::setArticleId(rex_request('article_id','int'), rex_request('clang','int',$clang));
        }
      }


      // GET PATH RELATIVE TO INTALL_SUBDIR
      $length = strlen($install_subdir);
      $path = substr(ltrim($_SERVER['REQUEST_URI'],'/'), $length);


      // TRIM STANDARD PARAMS
      if(($pos = strpos($path, '?')) !== false)
      {
        $path = substr($path, 0, $pos);
      }


      // TRIM ANCHORS
      if(($pos = strpos($path, '#')) !== false)
      {
        $path = substr($path, 0, $pos);
      }


      // RESOLVE REWRITTEN PARAMS -> POPULATE GET/REQUEST GLOBALS
      if($this->use_params_rewrite && strstr($path,$params_starter.'/'))
      {
        $tmp = explode($params_starter.'/',$path);
        $path = $tmp[0];
        $vars = explode('/',$tmp[1]);
        self::populateGlobals($vars);
      }

      // RETRY RESOLVE VIA PATHLIST
      if(self::resolve_from_pathlist($path))
      {
        return;
      }


      // GET ID FROM EXTENSION POINT
      $ep = rex_register_extension_point('REXSEO_ARTICLE_ID_NOT_FOUND', '', array('path' => $path, 'request' => $_SERVER['REQUEST_URI']));
      if(isset($ep['article_id']) && $ep['article_id'] > 0)
      {
        if(isset($ep['clang']) && $ep['clang'] > -1)
        {
          $clang = $ep['clang'];
        }
        return self::setArticleId($ep['article_id'],$clang);
      }


      // CHECK CLOSEST URL MATCH VIA LEVENSHTEIN
      if($this->use_levenshtein)
      {
        foreach ($REXSEO_URLS as $url => $params)
        {
          $levenshtein[levenshtein($path, $url)] = $params['id'].'#'.$params['clang'];
        }

        ksort($levenshtein);
        $best = explode('#', array_shift($levenshtein));

        return self::redirect(array('id'    => $best[0],
                                    'clang' => $best[1],
                                    'status'=> 301)     );
      }


      // STILL NO MATCH -> 404
      self::setArticleId($notfound_id,$clang);
    }
  }


  /**
  * RESOLVE_FROM_PATHLIST()
  *
  * @param  $path  string  URL to look up in pathlist
  * @return        boolean
  */
  function resolve_from_pathlist($path)
  {
    global $REXSEO_URLS;

    if(isset($REXSEO_URLS[$path]))
    {
      $status = isset($REXSEO_URLS[$path]['status']) ? $REXSEO_URLS[$path]['status'] : 200;

      switch($status)
      {
        case 301:
        case 302:
        case 303:
        case 307:
          $redirect = array('id'    => $REXSEO_URLS[$path]['id'],
                            'clang' => $REXSEO_URLS[$path]['clang'],
                            'status'=> $status);
          self::redirect($redirect);
          return true;
        default:
          if(isset($REXSEO_URLS[$path]['params']))
            self::populateGlobals($REXSEO_URLS[$path]['params'],false);
          self::setArticleId($REXSEO_URLS[$path]['id'],$REXSEO_URLS[$path]['clang']);
          return true;
      }
    }
    return false;
}


  /**
  * REWRITE()
  *
  * rewrite URL
  * @param $params from EP URL_REWRITE
  */
  function rewrite($params)
  {
    // URL ALREADY SET BY OTHER EXTENSION
    if($params['subject'] != '')
    {
      return $params['subject'];
    }

    global $REX, $REXSEO_IDS;

    $id             = $params['id'];
    $name           = $params['name'];
    $clang          = $params['clang'];
    $subdir         = $REX['ADDON']['rexseo']['settings']['install_subdir'];
    $notfound_id    = $REX['NOTFOUND_ARTICLE_ID'];

    // GET PARAMS STRING
    $urlparams = self::makeUrlParams($params);

    // GET URL FROM PATHLIST AND APPEND PARAMS
    if(isset($REXSEO_IDS[$id]) && isset($REXSEO_IDS[$id][$clang]))
    {
      $base_url = $REXSEO_IDS[$id][$clang]['url'];
      $url      = $base_url.$urlparams;
      $notfound = false;
    }
    else
    {
      $url = $base_url = $REXSEO_IDS[$notfound_id][$clang]['url'];
      $notfound = true;

      if($REX['ADDON']['rexseo']['debug_log']==1)
      {
        $trace = debug_backtrace();
        self::logError('article (id='.$id.'/clang='.$clang.') does not exist',E_USER_WARNING,$trace);
      }
    }

    // SUBDIR
    $subdir = !$REX['REDAXO'] ? '/'.$subdir  : '';

    // HACK: EP URL_REWRITE WON'T ACCEPT EMPTY STRING AS RETURN
    if($subdir == '' && $url == '')
    {
      $url = ' ';
    }

    // str_replace fixes a caching bug that appears while updating specific
    // modules/slices in the redaxo backend
    $url = str_replace('/redaxo/','/',$subdir.$url);

    // STRIP LEADING SLASH IF A ABSOLUTE URL WAS GIVEN VIA REXSEO CUSTOM URL
    $url = substr($url,0,8)=='/http://' ? ltrim($url,'/') : $url;


    // EP "REXSEO_POST_REWRITE"
    $ep_params = array('article_id'     => $id,
                       'clang'          => $clang,
                       'notfound'       => $notfound,
                       'base_url'       => $base_url,
                       'subdir'         => $subdir,
                       'urlparams'      => $urlparams,
                       'params'         => $params['params'],
                       'divider'        => $params['divider'],
                       'params_starter' => $REX['ADDON']['rexseo']['settings']['params_starter'],
                       'urlencode'      => $REX['ADDON']['rexseo']['settings']['urlencode'],
                       );
    $url = rex_register_extension_point('REXSEO_POST_REWRITE', $url, $ep_params);

    return $url;
  }



  /**
  * REDIRECT()
  *
  * redirect request
  * @param $redirect   (array) params passed through from EP
  */
  protected function redirect($redirect)
  {
    global $REXSEO_IDS;

    $status   = isset($redirect['status']) ? $redirect['status'] : 200;
    $location = rexseo::base().$REXSEO_IDS[$redirect['id']][$redirect['clang']]['url'];

    while(ob_get_level()){
      ob_end_clean();
    }

    header('HTTP/1.1 '.$status);
    header('Location:'.$location);
    exit();
  }



  /**
  * SETARTICLEID()
  *
  * set ARTICLE_ID & CLANG in global var $REX
  * @param $art_id   article id
  * @param $clang_id language id
  */
  protected function setArticleId($art_id, $clang_id = -1)
  {
    global $REX;
    $REX['ARTICLE_ID'] = $art_id;
    if($clang_id > -1)
      $REX['CUR_CLANG'] = $clang_id;
  }



  /**
  * MAKEURLPARAMS()
  *
  * Create params string for url
  * @param $EPparams   (array) urlencoded params from rex_getUrl/URL_REWRITE
  */
  protected function makeUrlParams($EPparams)
  {
    global $REX;
    $divider        = $EPparams['divider'];
    $urlparams      = $EPparams['params'];
    $params_starter = $REX['ADDON']['rexseo']['settings']['params_starter'];

    if($this->use_params_rewrite)
    {
      // REWRITE PARAMS
      $urlparams = str_replace(array($divider,'='),'/',$urlparams);
      $urlparams = str_replace(array('%5B','%5D'),array('(',')'),$urlparams); /* pseudo array: brackets "[]" not allowed by RFC1738, replace with "()", */
      $urlparams = $urlparams == '' ? '' : $params_starter.$urlparams.'/';
    }
    else
    {
      // STANDARD PARAMS STRING
      $urlparams = $urlparams == '' ? '' : '?'.$urlparams;
    }
    $urlparams = str_replace(array('/amp;','?&amp;'),array('/','?'),$urlparams);
    return $urlparams;
  }



  /**
  * POPULATEGLOBALS()
  *
  * Populate GET/REQUEST Globals with params from either rex_getUrl/URL_REWRITE
  * (params will come urlencoded) or from pathlist (NOT urlencoded)
  * @param $vars   (array) resolved URL Parameters
  * @param $decode (bool)  urldecode vars yes/no
  */
  protected function populateGlobals($vars,$decode=true)
  {
    if(is_array($vars))
    {
      for($c=0;$c<count($vars);$c+=2)
      {
        if($vars[$c]!='')
        {
          $key = $decode ? urldecode($vars[$c])   : $vars[$c];
          $val = $decode ? urldecode($vars[$c+1]) : $vars[$c+1];

          if(strstr($key,'('))
          {
            $key = rtrim($key,')');
            $key = explode('(',$key);
          }

          if(is_array($key) && count($key)==2)
          {
            $_GET[$key[0]][$key[1]]     = $val;
            $_REQUEST[$key[0]][$key[1]] = $val;
          }
          else
          {
            $_GET[$key]     = $val;
            $_REQUEST[$key] = $val;
          }
        }
      }
    }
  }

}

// END OF CLASS -> OTHER FUNCTIONS
////////////////////////////////////////////////////////////////////////////////

/**
* regenerate pathlist on each extension point
*/
if ($REX['REDAXO'])
{
  $extension = 'rexseo_manage_pathlist_events';
  $extensionPoints = array(
    'CAT_ADDED',     'CAT_UPDATED',   'CAT_DELETED',
    'ART_ADDED',     'ART_UPDATED',   'ART_DELETED', 'ART_META_FORM_SECTION',
    'ART_TO_CAT',    'CAT_TO_ART',    'ART_TO_STARTPAGE',
    'CLANG_ADDED',   'CLANG_UPDATED', 'CLANG_DELETED',
    'ALL_GENERATED');

  foreach($extensionPoints as $extensionPoint)
  {
    rex_register_extension($extensionPoint, $extension);
  }
}

function rexseo_manage_pathlist_events($params)
{
  global $REX,$i18N;

  if(!isset($params['extension_point']))
    $params['extension_point'] = '';

  $where = $wait_last_clang = $clear_pathlist = false;
  switch($params['extension_point'])
  {
    case 'CAT_UPDATED':
    case 'ART_UPDATED':
      $where = '(id='. $params['id'] .' AND clang='. $params['clang'] .') OR (path LIKE "%|'. $params['id'] .'|%" AND clang='. $params['clang'] .')';
      break;

    case 'CAT_ADDED':
    case 'ART_ADDED':
    case 'ART_TO_CAT':
    case 'CAT_TO_ART':
      $wait_last_clang = true;
      $where = '1=1';
      break;

    case 'ART_META_FORM_SECTION':
      if(rex_request('savemeta','string') != ''){
        $where = '(id='. $params['id'] .' AND clang=}'. $params['clang'] .') OR (path LIKE "%|'. $params['id'] .'|%" AND clang='. $params['clang'] .')';
      }
      break;

    case 'CAT_DELETED':
    case 'ART_DELETED':
    case 'ART_TO_STARTPAGE':
      $wait_last_clang = true;
      $clear_pathlist  = true;
      $where           = '1=1';
      break;

    case 'CLANG_ADDED':
    case 'CLANG_UPDATED':
    case 'CLANG_DELETED':
    case 'ALL_GENERATED':
    default:
      $clear_pathlist = true;
      $where          = '1=1';
      break;
  }

  if(count($REX['CLANG']) > 1 && $wait_last_clang) {
    if(isset($params['clang']) && ($params['clang'] != (count($REX['CLANG'])-1))) {
        return;
    }
  }

  if(rex_get('info','string')!='') {
    if(!isset($I18N)) {
      $I18N = rex_create_lang($REX['LANG']);
    }
    switch(rex_get('info','string')) {
      case $I18N->msg('content_articlecopied'):
      case $I18N->msg('content_articlemoved'):
      case $I18N->msg('category_moved'):
       $where = '1=1';
    }
  }

  if(!$where){
    return;
  }

  $query = 'SELECT `id`, `clang`, `path`, `startpage`,`art_rexseo_url` FROM '. $REX['TABLE_PREFIX'] .'article WHERE '. $where.' AND revision=0 OR revision IS NULL';
  $data = rexseo_get_pathlist_data($query);
  rexseo_generate_pathlist($data, $clear_pathlist);
}

function rexseo_get_pathlist_data($query = false)
{
  global $REX;

  if(!$query) {
    $query = 'SELECT `id`, `clang`, `path`, `startpage`,`art_rexseo_url` FROM '. $REX['TABLE_PREFIX'] .'article WHERE 1=1 AND revision=0 OR revision IS NULL';
  }

  $db = rex_sql::factory();
   // REVISION FIX
  $db->setQuery('UPDATE '. $REX['TABLE_PREFIX'] .'article SET revision = 0 WHERE revision IS NULL;');
  $db->setQuery('UPDATE '. $REX['TABLE_PREFIX'] .'article_slice SET revision = 0 WHERE revision IS NULL;');

  $data = $db->getArray($query);

  // CHECK & REPAIR DORKED METAINFO
  if($db->hasError() && $db->getErrno()==1054) {
    rexseo_setup_metainfo();
    $data = $db->getArray($query);
  }
  return $data;
}


/**
* REXSEO_GENERATE_PATHLIST()
*
* generiert die Pathlist, abhängig von Aktion
* @author markus.staab[at]redaxo[dot]de Markus Staab
* @package redaxo4.2
*/
function rexseo_generate_pathlist($data = false, $clear_pathlist = false)
{
  if(!$data) {
    $data = rexseo_get_pathlist_data();
  }

  global $REX, $REXSEO_IDS, $REXSEO_URLS;

  if(file_exists(REXSEO_PATHLIST)) {
    require_once (REXSEO_PATHLIST);
  }

  // EXTENSION POINT "REXSEO_PATHLIST_BEFORE_REBUILD"
  rex_register_extension_point('REXSEO_PATHLIST_BEFORE_REBUILD',array('REXSEO_IDS'=>$REXSEO_IDS,'REXSEO_URLS'=>$REXSEO_URLS));

  if($clear_pathlist){
    $REXSEO_IDS = $REXSEO_URLS = array();
  }


  // HARDCODED PATHES: REDIRECT STARTPAGE VARIANTS
  $REXSEO_URLS['index.php'] = array('id'  => $REX['START_ARTICLE_ID'], 'clang' => $REX['ADDON']['rexseo']['settings']['homelang'], 'status' => 301);
  $REXSEO_URLS[''] = $REXSEO_URLS['index.php'];
  if(count($REX['CLANG'] > 1)) {
    $REXSEO_URLS[ $REX['CLANG'][ $REX['ADDON']['rexseo']['settings']['homelang'] ] ] = $REXSEO_URLS['index.php'];
  }

  foreach($data as $k => $v)
  {
    $pathname   = '';
    $id         = $v['id'];
    $clang      = $v['clang'];
    $path       = $v['path'];
    $rexseo_url = trim($v['art_rexseo_url']);

    // FALLS REXSEO URL -> ERSETZEN
    if ($rexseo_url != '')
    {
      if(substr($rexseo_url,0,7)=='http://')
      {
        $REXSEO_IDS[$id][$clang] = array('url' => $rexseo_url);
        continue;
      }

      $pathname = ltrim($rexseo_url,'/'); // sanitize leading slash
      $pathname = urlencode($pathname);
      $pathname = str_replace('%2F','/',$pathname); // decode slahes..

    }
    // NORMALE URL ERZEUGUNG
    else
    {
      // LANG SLUG
      if (count($REX['CLANG']) > 1 && $clang != $REX['ADDON']['rexseo']['settings']['hide_langslug'])
      {
        $pathname = '';
        $pathname = rexseo_appendToPath($pathname, $REX['CLANG'][$clang], $id, $clang);
      }

      // pfad über kategorien bauen
      $path = trim($path, '|');
      if($path != '')
      {
        $path = explode('|', $path);
        foreach ($path as $p)
        {
          $ooc = OOCategory::getCategoryById($p, $clang);

          // PREVENT FATALS IN RARE CONDITIONS WHERE DB/CACHE ARE OUT OF SYNC
          if(!is_a($ooc,'OOCategory')){
            RexseoRewrite::logError('couldn\'t create OOCategory object with params id='.$p.'/clang='.$clang.'',E_USER_WARNING);
            continue;
          }

          $name = $ooc->getName();
          unset($ooc);

          $pathname = rexseo_appendToPath($pathname, $name, $id, $clang);
        }
      }

      $ooa = OOArticle::getArticleById($id, $clang);

      // PREVENT FATALS IN RARE CONDITIONS WHERE DB/CACHE ARE OUT OF SYNC
      if(!is_a($ooa,'OOArticle')){
        RexseoRewrite::logError('couldn\'t create OOArticle object with params id='.$id.'/clang='.$clang.'',E_USER_WARNING);
        continue;
      }

      if($ooa->isStartArticle())
      {
        $ooc = $ooa->getCategory();
        $catname = $ooc->getName();
        unset($ooc);
        $pathname = rexseo_appendToPath($pathname, $catname, $id, $clang);
      }

      if($REX['ADDON']['rexseo']['settings']['url_schema'] == 'rexseo')
      {
        if(!$ooa->isStartArticle())
        {
        // eigentlicher artikel anhängen
        $name = $ooa->getName();
        unset($ooa);
        $pathname = rexseo_appendToPath($pathname, $name, $id, $clang);
        }
      }
      else
      {
        // eigentlicher artikel anhängen
        $name = $ooa->getName();
        unset($ooa);
        $pathname = rexseo_appendToPath($pathname, $name, $id, $clang);
      }

      // ALLGEMEINE URL ENDUNG
      $pathname = substr($pathname,0,strlen($pathname)-1).$REX['ADDON']['rexseo']['settings']['url_ending'];

      // STARTSEITEN URL FORMAT
      if($v['id']    == $REX['START_ARTICLE_ID'] &&
         $v['clang'] == $REX['ADDON']['rexseo']['settings']['homelang'] &&
         $REX['ADDON']['rexseo']['settings']['homeurl'] == 1)
      {
        $pathname = '';
      }
      elseif($REX['ADDON']['rexseo']['settings']['homeurl'] == 2 &&
             $v['id'] == $REX['START_ARTICLE_ID'] &&
             count($REX['CLANG']) > 1)
      {
        $pathname = $REX['CLANG'][$clang].'/';
      }

    }

    // SANITIZE MULTIPLE "-" IN PATHNAME
    $pathname = preg_replace('/[-]{1,}/', '-', $pathname);

    // UNSET OLD URL FROM $REXSEO_URLS
    if(isset($REXSEO_IDS[$id][$clang]['url']) && isset($REXSEO_URLS[$REXSEO_IDS[$id][$clang]['url']]))
      unset($REXSEO_URLS[$REXSEO_IDS[$id][$clang]['url']]);

    $REXSEO_IDS[$id][$clang] = array('url' => $pathname);
    $REXSEO_URLS[$pathname]  = array('id'  => (int) $id, 'clang' => (int) $clang);

  }


  // EXTENSION POINT "REXSEO_PATHLIST_CREATED"
  $subject = array('REXSEO_IDS'=>$REXSEO_IDS,'REXSEO_URLS'=>$REXSEO_URLS);
  $subject = rex_register_extension_point('REXSEO_PATHLIST_CREATED',$subject);

  // EXTENSION POINT "REXSEO_PATHLIST_FINAL" - READ ONLY
  rex_register_extension_point('REXSEO_PATHLIST_FINAL',$subject);

  // ASSEMBLE, COMPRESS & WRITE PATHLIST TO FILE
  $pathlist_content = '$REXSEO_IDS = '.var_export($subject['REXSEO_IDS'],true).';'.PHP_EOL.'$REXSEO_URLS = '.var_export($subject['REXSEO_URLS'],true).';';

  $pathlist_content = rexseo_compressPathlist($pathlist_content);

  rex_put_file_contents(REXSEO_PATHLIST,'<?php'.PHP_EOL.$pathlist_content);

  // PURGE *.CONTENT CACHEFILES TO UPDATE INTERNAL LINKS CREATED BY replceLinks() in rex_article_base
  rexseo_purgeCacheFiles();
}


/**
* REXSEO_PURGECACHEFILES()
*
* selectively purge cache files by extension
* @param $type (string) cachefile extension
*/
function rexseo_purgeCacheFiles($ext='.content')
{
  global $REX;
  $pattern     = $REX['INCLUDE_PATH'].'/generated/articles/*'.$ext;
  $purge_files = glob($pattern);

  if(is_array($purge_files) && count($purge_files)>0)
  {
    foreach ($purge_files as $file)
    {
      unlink($file);
    }
  }
}


/**
* REXSEO_COMPRESSPATHLIST()
*
* replaces excessive linfeeds and whitespaces from var_export
* @param $str (string) the rexseo_pathlist as string
*/
function rexseo_compressPathlist($str)
{
  global $REX;

  switch($REX['ADDON']['rexseo']['settings']['compress_pathlist'])
  {
    case 0:
      return $str;
      break;

    case 1:
      $matrix   = array(
        'array ('.PHP_EOL.'      \'' => 'array (\'',
        '=> '.PHP_EOL.'  array'      => '=> array',
        '=> '.PHP_EOL.'    array'    =>'=> array',
        ','.PHP_EOL.'    ),'         => ',),',
        '('.PHP_EOL.'    \''         => '(\'',
        ','.PHP_EOL.'    \''         => ',\'',
        ','.PHP_EOL.'  ),'           => ',),'
        );
      break;

    case 2:
      $matrix   = array(
        PHP_EOL => '',
        ' '     => ''
        );
    break;
  }

  return str_replace(array_keys($matrix),array_values($matrix),$str);
}


function rexseo_appendToPath($path, $name, $article_id, $clang)
{
  global $REX;

  if ($name != '')
  {
    switch($REX['ADDON']['rexseo']['settings']['urlencode']){
      case 0:
        $name = strtolower(rexseo_parse_article_name($name));
        break;
      case 1:
        $name = rawurlencode(str_replace('/','-',$name));
        break;
      case 2:
        $name = rexseo_replace_special_chars($name);
        $name = rawurlencode(str_replace('/','-',$name));
        break;
    }

    // SANITIZE LAST CHARACTER
    $name = rtrim($name,'-') !== '' ? rtrim($name,'-') : $name;

    $path .= $name.'/';
  }
  return $path;
}


/**
* replaces special chars in article name
* @param $name       (string) article name
* @param $article_id (string) article id
* @param $clang      (string) clang
*/
function rexseo_parse_article_name($name)
{
  global $REX;

  // SANITIZE LAST CHARACTER
  $name = rtrim($name,'-') !== '' ? rtrim($name,'-') : $name;

  return
    // + durch - ersetzen
    str_replace('+',$REX['ADDON']['rexseo']['settings']['url_whitespace_replace'],
        // ggf uebrige zeichen url-codieren
        urlencode(
          // mehrfach hintereinander auftretende spaces auf eines reduzieren
          preg_replace('/ {2,}/',' ',
            // alle sonderzeichen raus
            preg_replace('/[^a-zA-Z_\-0-9 ]/', '',
              // sprachspezifische zeichen umschreiben
              rexseo_replace_special_chars($name)
            )
          )
        )
    );
}


/**
* replaces special chars in string
* @param $str        (string) article name
*/
function rexseo_replace_special_chars($name)
{
  static $firstCall = true;
  static $translation;

  if($firstCall)
  {
    global $REX, $I18N;

    if(!$I18N)
      $I18N = rex_create_lang($REX['LANG']);

    $translation = array(
      'search'  => explode('|', $I18N->msg('special_chars')),
      'replace' => explode('|', $I18N->msg('special_chars_rewrite')),
      );

    // EXTENSION POINT
    $translation = rex_register_extension_point('REXSEO_SPECIAL_CHARS',$translation);

    $firstCall = false;
  }

  return str_replace($translation['search'], $translation['replace'], $name);

}
