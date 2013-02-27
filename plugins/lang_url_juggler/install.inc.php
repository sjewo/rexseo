<?php
/**
 * lang_url_juggler - RexSEO Plugin
 *
 * @link https://github.com/gn2netwerk/rexseo
 *
 * @author http://rexdev.de
 * @author dh[at]gn2-netwerk[dot]de Dave Holloway
 *
 * @package redaxo 4.3.x/4.4.x
 * @version 0.8.0
 */

$myself      = 'lang_url_juggler';
$minimum_REX = '4.3.0';
$minimum_PHP = 5.3;


// CHECK REDAXO VERSION
////////////////////////////////////////////////////////////////////////////////
if(version_compare($REX['VERSION'].'.'.$REX['SUBVERSION'].'.'.$REX['MINORVERSION'], $minimum_REX, '<'))
{
  $REX['ADDON']['installmsg'][$myself] = 'Requires Redaxo '.$minimum_REX.' or higher.';
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
