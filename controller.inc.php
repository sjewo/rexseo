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

global $REX;

if (rex_request('rexseo_func')!='')
{
  $path = $REX['INCLUDE_PATH'].'/addons/rexseo';

  switch (rex_request('rexseo_func'))
  {
    case 'googlesitemap':
      require_once $REX['INCLUDE_PATH'].'/addons/rexseo/classes/class.rexseo_sitemap.inc.php';
      $map = new rexseo_sitemap;

      switch(rex_request('mode'))
      {
        case'json':
          $map->setMode('json');
          $map->send();
        break;
        default:
          $map->send();
      }

      die();
    break;


  case 'robots':
      require_once $REX['INCLUDE_PATH'].'/addons/rexseo/classes/class.rexseo_robots.inc.php';

      $robots = new rexseo_robots;
      if (isset ($REX['ADDON']['rexseo']['settings']['robots']) && $REX['ADDON']['rexseo']['settings']['robots'] != '')
        $robots->setContent($REX['ADDON']['rexseo']['settings']['robots']);
      $robots->addSitemapLink();
      $robots->send();

      die();
    break;

    default:
    break;
  }
}
?>
