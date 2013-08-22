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

// ADDON PARAMS
////////////////////////////////////////////////////////////////////////////////
$myself = 'rexseo';
$myroot = $REX['INCLUDE_PATH'].'/addons/'.$myself;

$REX['ADDON']['rxid'][$myself]        = '750';
$REX['ADDON']['name'][$myself]        = 'RexSEO';
$REX['ADDON']['version'][$myself]     = '1.6.3';
$REX['ADDON']['author'][$myself]      = 'Markus Staab, Wolfgang Huttegger, Dave Holloway, Jan Kristinus, jdlx';
$REX['ADDON']['supportpage'][$myself] = 'forum.redaxo.de';
$REX['ADDON']['perm'][$myself]        = $myself.'[]';
$REX['PERM'][]                        = $myself.'[]';
$REX['ADDON'][$myself]['SUBPAGES']    = array (
  array ('',          'Einstellungen'),
  array ('help',      'Hilfe')
  );
$REX['ADDON'][$myself]['debug_log']   = 0;
$REX['ADDON'][$myself]['settings']['default_redirect_expire'] = 60;
$REX['PROTOCOL'] = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://';

// FIX FOR IIS: SET REQUEST_URI, IF NOT AVAILABLE
////////////////////////////////////////////////////////////////////////////////
if(!isset($_SERVER['REQUEST_URI']))
{
  $_SERVER['REQUEST_URI'] = substr($_SERVER['PHP_SELF'], 1);
  if(isset($_SERVER['QUERY_STRING']))
    $_SERVER['REQUEST_URI'].='?'.$_SERVER['QUERY_STRING'];
}

// INCLUDES
////////////////////////////////////////////////////////////////////////////////
require_once $myroot.'/functions/function.rexseo_helpers.inc.php';


// DEFAULT SETTINGS
////////////////////////////////////////////////////////////////////////////////
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


// EXTERNAL USER SETTINGS
////////////////////////////////////////////////////////////////////////////////
$user_prefs = $REX['INCLUDE_PATH'].'/data/addons/'.$myself.'/'.$myself.'.settings.php';
if(file_exists($user_prefs)) {
  require_once $user_prefs;
}


// RUN ON ADDONS INLCUDED
////////////////////////////////////////////////////////////////////////////////
if(!$REX['SETUP']){
  rex_register_extension('ADDONS_INCLUDED','rexseo_init');
}

if(!function_exists('rexseo_init')){
  function rexseo_init($params)
  {
    global $REX;

    // MAIN
    require_once $REX['INCLUDE_PATH'].'/addons/rexseo/classes/class.rexseo_meta.inc.php';

    if ($REX['MOD_REWRITE'] !== false)
    {
      // REWRITE
      $levenshtein    = (bool) $REX['ADDON']['rexseo']['settings']['levenshtein'];
      $rewrite_params = (bool) $REX['ADDON']['rexseo']['settings']['rewrite_params'];
      require_once $REX['INCLUDE_PATH'].'/addons/rexseo/classes/class.rexseo_rewrite.inc.php';
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

    // REXSEO POST INIT
    rex_register_extension_point('REXSEO_INCLUDED');

  } // rexseo_init()
}


if(rex_request('page')=='content' && rex_request('mode')=='meta') {
  rex_register_extension('PAGE_HEADER', 'rexseo_article_meta_counter_assets' );
}
