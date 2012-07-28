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

$myself = 'redirects_manager';
$myroot = $REX['INCLUDE_PATH'].'/addons/rexseo/plugins/'.$myself;


// MAIN
////////////////////////////////////////////////////////////////////////////////
rex_register_extension('ADDONS_INCLUDED', 'rexseo_redirects_manager_init');
function rexseo_redirects_manager_init($params)
{
  global $REX;

  // SNEAK INTO REXSEO SUBPAGES
  //////////////////////////////////////////////////////////////////////////////
  $REX['ADDON']['pages']['rexseo'][] = array ('redirects_manager' , 'Redirects Manager');
  $REX['ADDON']['rexseo']['SUBPAGES'] = $REX['ADDON']['pages']['rexseo'];
  unset($REX['ADDON']['pages']['rexseo']);
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
      rexseo_htaccess_update_redirects();                                         #FB::log($params,__FUNCTION__.' $params');
    }
  }


  // AUTO CREATE REDIRECTS FROM CHANGED URLS
  ////////////////////////////////////////////////////////////////////////////////
  if ($REX['REDAXO'] && $REX['MOD_REWRITE'] !== false && $REX['ADDON'][$myself]['settings']['auto_redirects']!=0)
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
        $db = new rex_sql;
        $qry = 'INSERT INTO `'.$REX['TABLE_PREFIX'].'rexseo_redirects` (`id`, `createdate`, `updatedate`, `expiredate`, `creator`, `status`, `from_url`, `to_article_id`, `to_clang`, `http_status`) VALUES';
        $date = time();
        $expire = $date + ($REX['ADDON']['rexseo']['settings']['default_redirect_expire']*24*60*60);
        $status = $REX['ADDON']['rexseo']['settings']['auto_redirects']==1 ? 1 : 0;
        foreach($diff as $k=>$url)
        {
          $qry .= PHP_EOL.'(\'\', \''.$date.'\', \''.$date.'\', \''.$expire.'\', \'rexseo\', '.$status.', \''.$url.'\', '.$REX['REXSEO_PRIOR_URLS'][$url]['id'].', '.$REX['REXSEO_PRIOR_URLS'][$url]['clang'].', 301),';
        }
        $qry = rtrim($qry,',').';';
        $db->setQuery($qry);
        rexseo_htaccess_update_redirects();
      }
    }
  }


}
