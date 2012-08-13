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
 * @package redaxo 4.3.x/4.4.x
 * @version 1.5.beta_13.8
 */


class rexseo_select extends rex_select {


  /*
   * addOption(); falls latin/iso verwendet wird, werden UTF8-zeichen dekodiert.
   *
   */
  function addOption($key='',$value='') {
    global $REX;


    if(!strpos($REX['LANG'],'utf'))	{
      $key = utf8_decode($key);
      $value = utf8_decode($value);
    }
    return parent::addOption($key,$value);
  }

}

?>
