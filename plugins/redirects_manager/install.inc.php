<?php
/**
 * redirects_manager - RexSEO Plugin
 *
 * @link https://github.com/gn2netwerk/rexseo
 *
 * @author http://rexdev.de
 * @author dh[at]gn2-netwerk[dot]de Dave Holloway
 *
 * @package redaxo 4.3.x/4.4.x/4.5.x
 * @version 1.0.0
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


// SETUP REDIRECTS TABLE
////////////////////////////////////////////////////////////////////////////////
$db = rex_sql::factory();
$qry = 'CREATE TABLE IF NOT EXISTS `'.$REX['TABLE_PREFIX'].'rexseo_redirects` (
  `id` int(4) NOT NULL AUTO_INCREMENT,
  `createdate` int(11) NOT NULL,
  `updatedate` int(11) NOT NULL,
  `expiredate` int(11) NOT NULL,
  `creator` varchar(32) NOT NULL,
  `status` int(1) NOT NULL DEFAULT \'1\',
  `from_url` text NOT NULL,
  `to_article_id` int(4) NOT NULL,
  `to_clang` tinyint(2) NOT NULL,
  `http_status` int(3) NOT NULL DEFAULT \'301\',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
if(!$db->setQuery($qry))
  $error[] = 'Tabelle für RexSEO Redirects konnte nicht angelegt werden.';


// INSTALL REXSEO REDIRECTS CRONJOB
////////////////////////////////////////////////////////////////////////////////
$clean = 'DELETE from `'.$REX['TABLE_PREFIX'].'630_cronjobs` WHERE `createuser`=\'rexseo\'';
$db->setQuery($clean);

$install = 'INSERT INTO `'.$REX['TABLE_PREFIX'].'630_cronjobs`
      (`id`, `name`                   , `type`                 , `parameters`                                                                                                                                                                , `interval`, `nexttime`, `environment`, `status`, `createdate`, `createuser`, `updatedate`, `updateuser`)
VALUES(\'\',\'RexSEO Redirect Expire\', \'rex_cronjob_phpcode\', \'a:1:{s:24:"rex_cronjob_phpcode_code";s:105:"  if(OOPlugin::isAvailable(\'\'rexseo\'\',\'\'redirects_manager\'\')){\r\n    redirects_manager::updateHtaccess();\r\n  }";}\', \'|1|d|\' , \'\'      , \'|0|1|\'    , 1       , \'\'        , \'rexseo\'  , \'\'        , \'rexseo\')';
$db->setQuery($install);



$REX['ADDON']['install'][$myself] = 1;
