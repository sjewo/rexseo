<?php
/**
 * redirects_manager - RexSEO Plugin
 *
 * @link https://github.com/gn2netwerk/rexseo
 *
 * @author http://rexdev.de
 * @author dh[at]gn2-netwerk[dot]de Dave Holloway
 *
 * @package redaxo 4.3.x/4.4.x
 * @version 0.1.0 dev
 */

// RUN IN BACKEND ONLY
////////////////////////////////////////////////////////////////////////////////
if(!$REX['REDAXO']){
  return;
}

$myself = 'redirects_manager';
$myroot = $REX['INCLUDE_PATH'].'/addons/rexseo/plugins/'.$myself;

$REX['ADDON']['rxid'][$myself] = '9999';
$REX['ADDON']['version'][$myself] = '0.1.0 dev';
$REX['ADDON']['author'][$myself] = 'rexdev.de';
$REX['ADDON']['supportpage'][$myself] = 'forum.redaxo.de';

// DYN SETTINGS
////////////////////////////////////////////////////////////////////////////////
// --- DYN
$REX["ADDON"]["redirects_manager"]["settings"] = array (
  'auto_redirects' => 0,
  'default_redirect_expire' => 60,
);
// --- /DYN


// INCLUDES
////////////////////////////////////////////////////////////////////////////////
require_once $myroot.'/classes/class.redirects_manager.inc.php';


// SNEAK INTO REXSEO SUBPAGES
//////////////////////////////////////////////////////////////////////////////
$REX['ADDON']['rexseo']['SUBPAGES'][] = array ('redirects_manager' , 'Redirects Manager');
if (rex_request('page', 'string') == 'rexseo' && rex_request('subpage', 'string') == 'redirects_manager')
{
  $REX['ADDON']['navigation']['rexseo']['path'] = $REX['INCLUDE_PATH'].'/addons/rexseo/plugins/redirects_manager/pages/index.inc.php';
}


// RUN CACHER ON DB CHANGES
////////////////////////////////////////////////////////////////////////////////
if ($REX['REDAXO'])
{
  rex_register_extension('REX_FORM_SAVED','rexseo_ht_update_callback');
  function rexseo_ht_update_callback($params)
  {
    redirects_manager::updateHtaccess();                                         #FB::log($params,__FUNCTION__.' $params');
  }
}


// AUTO CREATE REDIRECTS FROM CHANGED URLS
////////////////////////////////////////////////////////////////////////////////
if ($REX['REDAXO'] && $REX['MOD_REWRITE'] !== false && $REX['ADDON']['redirects_manager']['settings']['auto_redirects']!=0)
{
  rex_register_extension('REXSEO_PATHLIST_BEFORE_REBUILD','rexseo_remember_prior_pathlist');
  function rexseo_remember_prior_pathlist($params)
  {
    global $REX;
    $REX['REXSEO_PRIOR_URLS'] = $params['subject']['REXSEO_URLS'];
  }

  rex_register_extension('REXSEO_PATHLIST_FINAL','rexseo_auto_301');
  function rexseo_auto_301($params)
  {
    global $REX;

    $diff = array();
    $diff = array_diff(array_keys($REX['REXSEO_PRIOR_URLS']),array_keys($params['subject']['REXSEO_URLS']));

    if(is_array($diff) && count($diff)>0)
    {
      $db = rex_sql::factory();
      $qry = 'INSERT INTO `'.$REX['TABLE_PREFIX'].'rexseo_redirects` (`id`, `createdate`, `updatedate`, `expiredate`, `creator`, `status`, `from_url`, `to_article_id`, `to_clang`, `http_status`) VALUES';
      $date = time();
      $expire = $date + ($REX['ADDON']['redirects_manager']['settings']['default_redirect_expire']*24*60*60);
      $status = $REX['ADDON']['redirects_manager']['settings']['auto_redirects']==1 ? 1 : 0;
      foreach($diff as $k=>$url)
      {
        $qry .= PHP_EOL.'(\'\', \''.$date.'\', \''.$date.'\', \''.$expire.'\', \'rexseo\', '.$status.', \''.$url.'\', '.$REX['REXSEO_PRIOR_URLS'][$url]['id'].', '.$REX['REXSEO_PRIOR_URLS'][$url]['clang'].', 301),';
      }
      $qry = rtrim($qry,',').';';
      $db->setQuery($qry);
      redirects_manager::updateHtaccess();
    }
  }
}


