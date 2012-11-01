<?php
/**
 * skip_empty_article - RexSEO Plugin
 *
 * @see https://github.com/gn2netwerk/rexseo
 *
 * @package redaxo 4.3.x/4.4.x
 * @package rexseo 1.5.x
 * @version 0.1.0
 */

$myself         = 'skip_empty_article';
$minimum_REX    = '4.3.0';
$minimum_PHP    = '5.3';
$minimum_REXSEO = '1.5';


// CHECK REDAXO VERSION
////////////////////////////////////////////////////////////////////////////////
if(version_compare($REX['VERSION'].'.'.$REX['SUBVERSION'].'.'.$REX['MINORVERSION'], $minimum_REX, '<'))
{
  $REX['ADDON']['installmsg'][$myself] = 'Requires Redaxo '.$minimum_REX.' or higher.';
  $REX['ADDON']['install'][$myself] = 0;
  return;
}


// CHECK REXSEO VERSION
////////////////////////////////////////////////////////////////////////////////
if(version_compare(preg_replace('/[^0-9.]/','',$ADDONSsic['version']['rexseo']), $minimum_REXSEO, '<'))
{
  $REX['ADDON']['installmsg'][$myself] = 'Requires RexSEO '.$minimum_REXSEO.' or higher.';
  $REX['ADDON']['install'][$myself] = 0;
  return;
}


// CHECK PHP VERSION
////////////////////////////////////////////////////////////////////////////////
if(version_compare(PHP_VERSION, $minimum_PHP, '<'))
{
  $REX['ADDON']['installmsg'][$myself] = 'Requires PHP '.$minimum_PHP.' or higher.';
  $REX['ADDON']['install'][$myself] = 0;
  return;
}

$REX['ADDON']['install'][$myself] = 1;
