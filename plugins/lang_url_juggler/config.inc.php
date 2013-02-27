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


// RUN IN BACKEND ONLY
////////////////////////////////////////////////////////////////////////////////
if (!$REX['REDAXO'] || $REX['SETUP']) {
  return;
}


// SETTINGS
////////////////////////////////////////////////////////////////////////////////
$url_map = array(
// this clang will keep
// it's langslug but will use => this clang's url (part after the langslug)
                            2 => 1,
                            4 => 1,
                            );


// REORDER PATHLIST
////////////////////////////////////////////////////////////////////////////////
rex_register_extension('REXSEO_PATHLIST_CREATED', function($params) use($REX,$url_map) {

  $IDS  = $params['subject']['REXSEO_IDS'];
  $URLS = $params['subject']['REXSEO_URLS'];

  foreach($IDS as $id => $clangs)
  {
    foreach($url_map as $k => $v)
    {
      $from = preg_split('#\/#',$clangs[$k]['url'],2);
      $to   = preg_split('#\/#',$clangs[$v]['url'],2);

      $IDS[$id][$k]['url'] = $from[0].'/'.$to[1];
      if(isset($URLS[$from[0].'/'.$to[1]])){
        $URLS[$from[0].'/'.$to[1]] = array('id'=>$id,'clang'=>$k);
      }else{
        $URLS[$from[0].'/'.$to[1]] = array('id'=>$id,'clang'=>$k);
        unset($URLS[$from[0].'/'.$from[1]]);
      }

    }
  }
  ksort($URLS);
  return array('REXSEO_IDS'=>$IDS,'REXSEO_URLS'=>$URLS);
});
