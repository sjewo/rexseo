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

// CHECK INSTALL AS PLUGIN
////////////////////////////////////////////////////////////////////////////////
if(!isset($ADDONSsic) || !isset($ADDONSsic['plugins']['rexseo']['install']['redirects_manager']))
{
  $REX['ADDON']['installmsg'][$myself] .= 'Redirect Manager is not an Addon - it\'s a RexSEO Plugin!';
  $REX['ADDON']['install'][$myself] = 0;
  return;
}

// REQUIRE CRONJOB
////////////////////////////////////////////////////////////////////////////////
if(!isset($ADDONSsic['version']['cronjob']))
{
  $REX['ADDON']['installmsg'][$myself] = 'Cronjob Addon required!';
  $REX['ADDON']['install'][$myself] = 0;
  return;
}


$REX['ADDON']['install'][$myself] = 1;
