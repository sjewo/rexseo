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

class rexseo_robots
{
  private $host;
  private $robots_txt;


  /**
   * CONSTRUCTOR
   */
  public function rexseo_robots()
  {
    global $REX;
    $this->host = rtrim($REX['SERVER'],'/').'/';
    $this->robots_txt = 'User-agent: *
    Disallow:';
  }


  /**
   * SET HOST
   *
   * @param $host  (string)  http://DOMAIN.TLD
   */
  public function setHost($host)
  {
    $this->host = rtrim($host,'/').'/';
  }


  /**
   * SET CONTENT OF ROBOTS.TXT
   *
   * @return  (string) robots.txt
   */
  public function setContent($content)
  {
    $this->robots_txt = $content;
  }


  /**
   * INSERT SITEMAP LINK INTO ROBOTS.TXT
   *
   * @return  (string) robots.txt
   */
  public function addSitemapLink()
  {
    $this->robots_txt = 'Sitemap: '.$this->host.'sitemap.xml'.PHP_EOL.PHP_EOL.$this->robots_txt;
  }


  /**
   * RETURN ROBOTS.TXT
   *
   * @return  (string) robots.txt
   */
  public function get()
  {
    return $this->robots_txt;
  }


  /**
   * SEND ROBOTS.TXT
   */
  public function send()
  {
    $robots = self::get();

    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Length: '.strlen($robots));
    echo $robots;
    die();
  }

}

?>
