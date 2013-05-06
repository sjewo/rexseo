<?php
/**
 * version_check - RexSEO Plugin
 *
 * @link https://github.com/gn2netwerk/rexseo
 *
 * @author http://rexdev.de
 * @author dh[at]gn2-netwerk[dot]de Dave Holloway
 *
 * @package redaxo 4.3.x/4.4.x
 * @version 0.0.0
 */


// RUN IN BACKEND ONLY
////////////////////////////////////////////////////////////////////////////////
if(!$REX['REDAXO'] || rex_request('page','string')!='rexseo'){
  return;
}

$myself = 'version_check';
$myroot = $REX['INCLUDE_PATH'].'/addons/rexseo/plugins/'.$myself;

// INCLUDES
////////////////////////////////////////////////////////////////////////////////
require_once $REX['INCLUDE_PATH'].'/addons/rexseo/classes/class.rexseo_socket.inc.php';
require_once $REX['INCLUDE_PATH'].'/addons/rexseo/classes/class.rexseo_github_base.inc.php';
require_once $REX['INCLUDE_PATH'].'/addons/rexseo/classes/class.rexseo_github_functions.inc.php';

// NOTIFY DOWNLOADABLE UPDATE
////////////////////////////////////////////////////////////////////////////////
rex_register_extension('ADDONS_INCLUDED',
  function() use($REX)
  {
    try {
      $gc = new rexseo_github_functions('gn2netwerk','rexseo');
      $new_rexseo_version = $gc->getLatestVersion($REX['ADDON']['version']['rexseo'],'link');
    } catch (Exception $e) {
      $new_rexseo_version = '';
    }

    if($new_rexseo_version!='') {
      rex_register_extension('OUTPUT_FILTER',
        function($params) use($new_rexseo_version)
        {
          return str_replace('<div id="rex-output">',
                             '<div id="rex-output">'.rex_info('Eine neue Version ist als Download verf&uuml;gbar: '.$new_rexseo_version),
                             $params['subject']);
        });
    }

  });
