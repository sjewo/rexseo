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


class rexseo_select extends rex_select
{
  /*
   * addOption(); falls latin/iso verwendet wird, werden UTF8-zeichen dekodiert.
   *
   */
  function addOption($name = '', $value = '', $id = 0, $re_id = 0, $attributes = array())
  {
    global $REX;

    if(!rex_lang_is_utf8())
    {
      $name  = utf8_decode($name);
      $value = utf8_decode($value);
    }
    return parent::addOption($name, $value);
  }
}

?>
