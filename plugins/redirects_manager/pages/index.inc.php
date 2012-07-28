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


// GET PARAMS
////////////////////////////////////////////////////////////////////////////////
$myself   = 'redirects_manager';
$myroot   = $REX['INCLUDE_PATH'].'/addons/rexseo/plugins/'.$myself.'/';
$subpage  = rex_request('subpage', 'string');
$func     = rex_request('func', 'string');
$prefix   = rex_request('prefix', 'string');
$name     = rex_request('name', 'string');
$field_id = rex_request('field_id', 'int');
$type     = rex_request('type', 'string');


// INCLUDES
////////////////////////////////////////////////////////////////////////////////
//require_once $myroot.'/classes/class.redirects_manager.inc.php';


// PAGE HEAD
////////////////////////////////////////////////////////////////////////////////
require $REX['INCLUDE_PATH'] . '/layout/top.php';

rex_title('redirects_manager',$REX['ADDON']['rexseo']['SUBPAGES']);


// ACTIONS
////////////////////////////////////////////////////////////////////////////////



// PAGE BODY
////////////////////////////////////////////////////////////////////////////////


require $REX['INCLUDE_PATH'] . '/layout/bottom.php';
