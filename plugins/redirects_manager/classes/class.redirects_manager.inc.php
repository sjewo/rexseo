<?php
/**
 * redirects_manager - RexSEO Plugin
 *
 * @link https://github.com/gn2netwerk/rexseo
 *
 * @author http://rexdev.de
 * @author dh[at]gn2-netwerk[dot]de Dave Holloway
 *
 * @package redaxo 4.3.x/4.4.x/4.5.x
 * @version 1.0.0
 */

/**
 * Handle RexSEO Redirects
 **/
class redirects_manager
{
  private static $initialized = false;

  function __construct()
  {
    self::init();
  }

  private function init()
  {
    if (self::$initialized){
      return;
    }

    global $REX;
    self::$initialized = true;
  }


  /**
   * UPDATE REDIRECTS IN HTACCESS
   *
   * @param $param
   * @return void
   * @author
   **/
  public static function updateHtaccess($param='')
  {
    if(self::$initialized===false){
      self::init();
    }

    global $REX;

    $table = $REX['TABLE_PREFIX'].'rexseo_redirects';
    $db = rex_sql::factory();
    $redirects = array();
    $now = time();
    #$qry = 'SELECT * FROM `'.$table.'` WHERE `status`=1 ORDER BY `createdate` DESC;';
    $qry = 'SELECT * FROM `'.$table.'` ORDER BY `createdate` DESC;';

    foreach($db->getDBArray($qry) as $r)
    {
      $target_url = rex_getUrl($r['to_article_id'],$r['to_clang']);
      $from_url   = $r['from_url'];

      if($from_url==$target_url) /*1:1 loop*/
      {
        redirects_manager::updateRedirect($r['id'],2,'delete');
        continue;
      }
      elseif($from_url=='/' || $from_url=='' || $target_url=='/') /*root loop*/
      {
        redirects_manager::updateRedirect($r['id'],2,'delete');
        continue;
      }
      elseif(isset($redirects[$from_url])) /*duplicate*/
      {
        redirects_manager::updateRedirect($r['id'],3,'update');
        continue;
      }
      elseif(isset($redirects[$target_url])) /*2nd level loop*/
      {
        redirects_manager::updateRedirect($r['id'],4,'update');
        continue;
      }
      elseif($r['expiredate']<$now) /*expired*/
      {
        redirects_manager::updateRedirect($r['id'],5,'update');
        continue;
      }
      elseif($r['status']==1)
      {
        $redirects[$from_url]=array('http_status'=>$r['http_status'],'target_url'=>$target_url);
      }
    }

    $ht_path = $REX['HTDOCS_PATH'].'.htaccess';

    if(!file_exists($ht_path))
      {
        echo rex_warning('FEHLER: .htaccess wurde nicht unter folgendem Pfad gefunden:<br />
                          Pfad: "'.$ht_path.'"');
        return false;
      }

    if(count($redirects)>0)
    {
      $new_redirects = '### REXSEO REDIRECTS BLOCK'.PHP_EOL;
      foreach($redirects as $k=>$v)
      {
        $new_redirects .= 'Redirect '.$v['http_status'].' /'.$k.' '.$REX['SERVER'].$v['target_url'].PHP_EOL;
      }
      $new_redirects .= '### /REXSEO REDIRECTS BLOCK';
    }
    else
    {
      $new_redirects = '### REXSEO REDIRECTS BLOCK'.PHP_EOL.'### /REXSEO REDIRECTS BLOCK';
    }

    if($ht_content = rex_get_file_contents($ht_path))
    {
      if(preg_match("@(### REXSEO REDIRECTS BLOCK.*### /REXSEO REDIRECTS BLOCK)@s",$ht_content)!=1)
        echo rex_warning('ACHTUNG: redirects konnten nicht geschrieben werden!<br />
                          Bitte die .htaccess auf korrektes redirects delimiter Paar überprüfen.<br />
                         (Siehe original RexSEO htaccess: <em style="color:black;">./addons/rexseo/install/files/.htaccess</em> )');

      $new_ht = preg_replace("@(### REXSEO REDIRECTS BLOCK.*### /REXSEO REDIRECTS BLOCK)@s", $new_redirects, $ht_content);
      return rex_put_file_contents($ht_path, $new_ht);
    }
    return false;

  } // END updateHtaccess



  /**
   * UPDATE SINGLE REDIRECT
   *
   * @param $param
   * @return void
   * @author
   **/
  public static function updateRedirect($id,$status=2,$func='update')
  {
    if(self::$initialized===false){
      self::init();
    }
    global $REX;

    $table = $REX['TABLE_PREFIX'].'rexseo_redirects';
    $db = new rex_sql;
    switch($func)
    {
    case 'delete':
      #$qry = 'DELETE FROM `'.$table.'` WHERE `id`='.$id.' AND `creator`=\'rexseo\';';
      $qry = 'DELETE FROM `'.$table.'` WHERE `id`='.$id.';';
      return $db->setQuery($qry);
      break;

    default:
      $qry = 'UPDATE `'.$table.'` SET `status`='.$status.' WHERE `id`='.$id.';';
      return $db->setQuery($qry);
    }
  }





  /**
   * INJECT 301 URLS INTO REXSEO PATHLIST
   *
   * @param $param
   * @return void
   * @author
   **/
  function pathlistInject301($params)
  {
    global $REX;
    $redirects = $REX['ADDON']['rexseo']['settings']['301s'];

    if(count($redirects)>0)
    {
      foreach($redirects as $url => $v)
      {
        if(!isset($params['subject']['REXSEO_URLS'][$url]))
        {
          $params['subject']['REXSEO_URLS'][$url] = array('id'    =>$v['article_id'],
                                                          'clang' =>$v['clang'],
                                                          'status'=>301);
        }
      }
    }

    return $params['subject'];
  }

} // END class redirects_manager
