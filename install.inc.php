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

// INSTALL SETTINGS
////////////////////////////////////////////////////////////////////////////////
$myself            = 'rexseo';
$myroot            = $REX['INCLUDE_PATH'].'/addons/'.$myself;

$minimum_REX       = '4.3.0';
$minimum_PHP       = '5.3.0';
$required_addons   = array('textile','metainfo');
$disable_addons    = array('url_rewrite');
$htaccess_search   = array('x-mapp-php','php-cgi_wrapper');

$error             = array();

// CHECK ADDON FOLDER NAME
////////////////////////////////////////////////////////////////////////////////
$addon_folder = basename(dirname(__FILE__));
if($addon_folder != $myself)
{
  $REX['ADDON']['installmsg'][$addon_folder] = '<br />Der Name des Addon-Ordners ist inkorrekt: <code style="color:black;font-size:12px;">'.$addon_folder.'</code>
                                                <br />Addon-Ordner in <code style="color:black;font-size:1.23em;">'.$myself.'</code> umbenennen und Installation wiederholen';
  $REX['ADDON']['install'][$addon_folder] = 0;
  return;
}

// CHECK REDAXO VERSION
////////////////////////////////////////////////////////////////////////////////
if(version_compare($REX['VERSION'].'.'.$REX['SUBVERSION'].'.'.$REX['MINORVERSION'], $minimum_REX, '<'))
{
  $error[] = 'Dieses Addon ben&ouml;tigt Redaxo Version '.$minimum_REX.' oder h&ouml;her.';
}


// CHECK PHP VERSION
////////////////////////////////////////////////////////////////////////////////
if(version_compare(PHP_VERSION, $minimum_PHP, '<'))
{
  $error[] = 'Dieses Addon ben&ouml;tigt mind. PHP '.$minimum_PHP.'!';
}


// CHECK REQUIRED ADDONS
////////////////////////////////////////////////////////////////////////////////
foreach($required_addons as $a)
{
  if (!OOAddon::isInstalled($a))
  {
    $error[] = 'Addon "'.$a.'" ist nicht installiert.  <span style="float:right;">[ <a href="index.php?page=addon&addonname='.$a.'&install=1">'.$a.' installieren</a> ]</span>';
  }
  else
  {
    if (!OOAddon::isAvailable($a))
    {
      $error[] = 'Addon "'.$a.'" ist nicht aktiviert.  <span style="float:right;">[ <a href="index.php?page=addon&addonname='.$a.'&activate=1">'.$a.' aktivieren</a> ]</span>';
    }
  }
}


// CHECK ADDONS TO DISABLE
////////////////////////////////////////////////////////////////////////////////
foreach($disable_addons as $a)
{
  if (OOAddon::isInstalled($a) || OOAddon::isAvailable($a))
  {
    $error[] = 'Addon "'.$a.'" muß erst deinstalliert werden.  <span style="float:right;">[ <a href="index.php?page=addon&addonname='.$a.'&uninstall=1">'.$a.' de-installieren</a> ]</span>';
  }
}


if(count($error)==0)
{
  require_once $myroot.'/functions/function.rexseo_helpers.inc.php';

  // SETUP METAINFO
  //////////////////////////////////////////////////////////////////////////////
  rexseo_setup_metainfo();


  // CHECK ROOT .HTACCESS FILE FOR CRITICAL SETTINGS
  //////////////////////////////////////////////////////////////////////////////
  $autoinstall  = true;
  if (file_exists($REX['FRONTEND_PATH'].'/.htaccess'))
  {
    $matches  = array();
    $htaccess = rex_get_file_contents($REX['FRONTEND_PATH'].'/.htaccess');

    foreach($htaccess_search as $needle)
    {
      if(strpos($htaccess,$needle)!==false)
      {
        $autoinstall = false;
        $matches[] = $needle;
      }
    }

    if(count($matches)>0)
    {
      $msg = 'RexSEO: Die original .htaccess Datei im Root Ordner enth&auml;lt potentiell kritische settings f&uuml;r den Serverbetrieb:<br>';
      foreach($matches as $m)
      {
        $msg .= '<em style="margin:4px 0 4px 10px;color:black;display:inline-block;">'.$m.'</em><br />';
      }
      $msg .= 'Die automatische Installation der .htaccess Dateien wurde deaktiviert,<br /> Details zur manuellen Installation siehe RexSEO Hilfe.';
      echo rex_warning($msg);
    }
  }


  // INSTALL/COPY .HTACCESS FILES
  //////////////////////////////////////////////////////////////////////////////
  if($autoinstall)
  {
    $source = $REX['INCLUDE_PATH'].'/addons/'.$myself.'/install/files/';
    $target = $REX['HTDOCS_PATH'];
    $result = rexseo_recursive_copy($source, $target);
  }

  $REX['ADDON']['install'][$myself] = 1;
}
else

{
  $REX['ADDON']['installmsg'][$myself] = '<br />'.implode($error,'<br />');
  $REX['ADDON']['install'][$myself] = 0;
}

?>
