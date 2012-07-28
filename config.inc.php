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
 * @package redaxo 4.3.x/4.4.x
 * @version 1.4.285 dev
 */

// ADDON PARAMS
////////////////////////////////////////////////////////////////////////////////
$myself = 'rexseo';
$myroot = $REX['INCLUDE_PATH'].'/addons/'.$myself;

$REX['ADDON'][$myself]['VERSION'] = array
(
'VERSION'      => 1,
'MINORVERSION' => 4,
'SUBVERSION'   => '285 dev',
);

$REX['ADDON']['rxid'][$myself]        = '750';
$REX['ADDON']['name'][$myself]        = 'RexSEO';
$REX['ADDON']['version'][$myself]     = implode('.', $REX['ADDON'][$myself]['VERSION']);
$REX['ADDON']['author'][$myself]      = 'Markus Staab, Wolfgang Huttegger, Dave Holloway, Jan Kristinus, jdlx';
$REX['ADDON']['supportpage'][$myself] = 'forum.redaxo.de';
$REX['ADDON']['perm'][$myself]        = $myself.'[]';
$REX['PERM'][]                        = $myself.'[]';
$REX['ADDON'][$myself]['SUBPAGES']    = array (
  array ('',          'Einstellungen'),
  array ('redirects', 'Redirects'),
  array ('help',      'Hilfe')
  );
$REX['ADDON'][$myself]['debug_log']   = 0;
$REX['ADDON'][$myself]['settings']['default_redirect_expire'] = 60;
$REX['PROTOCOL'] = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://';


// INCLUDES
////////////////////////////////////////////////////////////////////////////////
require_once $myroot.'/functions/function.rexseo_helpers.inc.php';


// USER SETTINGS
////////////////////////////////////////////////////////////////////////////////
// --- DYN
$REX["ADDON"]["rexseo"]["settings"] = array (
  'rexseo_version' => $REX['ADDON']['version'][$myself],
  'first_run' => 1,
  'alert_setup' => 1,
  'install_subdir' => rexseo_subdir(),
  'url_whitespace_replace' => '-',
  'compress_pathlist' => 1,
  'def_desc' =>
  array (
    0 => '',
  ),
  'def_keys' =>
  array (
    0 => '',
  ),
  'title_schema' => '%B - %S',
  'url_schema' => 'rexseo',
  'url_ending' => '.html',
  'hide_langslug' => -1,
  'rewrite_params' => 0,
  'params_starter' => '++',
  'homeurl' => 1,
  'homelang' => 0,
  'urlencode' => 0,
  'allow_articleid' => 0,
  'levenshtein' => 0,
  'auto_redirects' => 2,
  'default_redirect_expire' => 60,
  'robots' => 'User-agent: *
Disallow:',
  'expert_settings' => 0,
);
// --- /DYN


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


// RUN ON ADDONS INLCUDED
////////////////////////////////////////////////////////////////////////////////
rex_register_extension('ADDONS_INCLUDED','rexseo_init');

function rexseo_init($params)
{
  global $REX,$myroot,$myself;

  // MAIN
  require_once $REX['INCLUDE_PATH'].'/addons/rexseo/classes/class.rexseo_meta.inc.php';

  if ($REX['MOD_REWRITE'] !== false)
  {
    // REWRITE
    $levenshtein    = (bool) $REX['ADDON'][$myself]['settings']['levenshtein'];
    $rewrite_params = (bool) $REX['ADDON'][$myself]['settings']['rewrite_params'];
    require_once $myroot.'/classes/class.rexseo_rewrite.inc.php';
    $rewriter = new RexseoRewrite($levenshtein,$rewrite_params);
    $rewriter->resolve();
    rex_register_extension('URL_REWRITE', array ($rewriter, 'rewrite'));

    // FIX TEXTILE/TINY LINKS @ REX < 4.3
    if(intval($REX['VERSION']) == 4 && intval($REX['SUBVERSION']) < 3)
    {
      rex_register_extension('GENERATE_FILTER', 'rexseo_fix_42x_links');
    }
  }

  // CONTROLLER
  include $REX['INCLUDE_PATH'].'/addons/rexseo/controller.inc.php';
}


?>
