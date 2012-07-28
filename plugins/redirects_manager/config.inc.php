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


}
