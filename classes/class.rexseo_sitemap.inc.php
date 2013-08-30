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

class rexseo_sitemap
{
  private $host;
  private $mode;
  private $db_articles;


  /**
   * GET SITEMAP ARTICLES FROM DB
   *
   * @return (array) sitemap articles
   */
  private function get_db_articles()
  {
    global $REX;

    $db_articles = array();
    $db = new rex_sql;
    $qry = 'SELECT `id`,`clang`,`updatedate`,`path`,`art_rexseo_priority`,`art_rexseo_changefreq`
            FROM `'.$REX['TABLE_PREFIX'].'article`
            WHERE `art_rexseo_sitemap_out`=\'show\'
            OR    (`art_rexseo_sitemap_out`=\'\' AND `status`=1);';
    foreach($db->getDbArray($qry) as $art)
    {
      $db_articles[$art['id']][$art['clang']] = array('loc'        => rex_getUrl($art['id'],$art['clang']),
                                                       'lastmod'    => date('Y-m-d\TH:i:s',$art['updatedate']).'+00:00',
                                                       'changefreq' => self::calc_article_changefreq($art['updatedate'],$art['art_rexseo_changefreq']),
                                                       'priority'   => self::calc_article_priority($art['id'],$art['clang'],$art['path'],$art['art_rexseo_priority'])
                                                       );
    }

    // EXTENSIONPOINT REXSEO_SITEMAP_ARRAY_CREATED
    $db_articles = rex_register_extension_point('REXSEO_SITEMAP_ARRAY_CREATED',$db_articles);

    // EXTENSIONPOINT REXSEO_SITEMAP_ARRAY_FINAL (READ ONLY)
    rex_register_extension_point('REXSEO_SITEMAP_ARRAY_FINAL',$db_articles);

    $this->db_articles = $db_articles;
  }


  /**
   * CALCULATE ARTICLE PRIORITY
   *
   * @param $article_id           (int)     rex_article.article_id
   * @param $clang                (int)     rex_article.clang
   * @param $path                 (string)  rex_article.path
   * @param $art_rexseo_priority  (float)   rex_article.art_rexseo_priority
   *
   * @return                      (float)   priority
   */
  private function calc_article_priority($article_id,$clang,$path,$art_rexseo_priority='')
  {
    global $REX;

    if($art_rexseo_priority!='')
      return $art_rexseo_priority;

    if($article_id==$REX['START_ARTICLE_ID'] && $clang==$REX['START_CLANG_ID'])
      return 1.0;

    return pow(0.8,count(explode('|',$path))-1);
  }


  /**
   * CALCULATE ARTICLE CHANGEFREQ
   *
   * @param $updatedate            (int)    rex_article.updatedate
   * @param $art_rexseo_changefreq (string) rex_article.art_rexseo_changefreq
   *
   * @return                       (string) change frequency  [never|yearly|monthly|weekly|daily|hourly|always]
   */
  private function calc_article_changefreq($updatedate,$art_rexseo_changefreq='')
  {
    if($art_rexseo_changefreq!='')
      return $art_rexseo_changefreq;

    $age = time() - $updatedate;

    switch($age)
    {
      case($age<604800):
        return 'daily';
      case($age<2419200):
        return 'weekly';
      default:
        return 'monthly';
    }
  }


  /**
   * BUILD SINGLE XML LOC FRAGMENT
   *
   * @param $loc        (string) article url  [including lang, excluding host]
   * @param $lastmod    (string) article last modified date  [UNIX date]
   * @param $changefreq (string) change frequency  [never|yearly|monthly|weekly|daily|hourly|always]
   * @param $priority   (float)  priority  [maximum: 1.0]
   *
   * @return            (string) xml location fragment
   */
  private function xml_loc_fragment($loc,$lastmod,$changefreq,$priority)
  {
    $xml_loc = '  <url>'.PHP_EOL.
    '    <loc>'.$this->host.$loc.'</loc>'.PHP_EOL.
    '    <lastmod>'.$lastmod.'</lastmod>'.PHP_EOL.
    '    <changefreq>'.$changefreq.'</changefreq>'.PHP_EOL.
    '    <priority>'.number_format($priority, 2, ".", "").'</priority>'.PHP_EOL.
    '  </url>'.PHP_EOL;

    return $xml_loc;
  }


  /**
   * CONSTRUCTOR
   */
  public function rexseo_sitemap()
  {
    global $REX;

    $this->db_articles = array();
    $this->mode         = 'xml';
    $this->host         = rtrim($REX['SERVER'],'/');

    self::get_db_articles();
  }


  /**
   * SET HOST
   *
   * @param $host  (string)  http://DOMAIN.TLD
   */
  public function setHost($host)
  {
    $this->host = rtrim($host,'/');
  }


  /**
   * SET MODE
   *
   * @param $mode  (string)  [xml|json]
   */
  public function setMode($mode)
  {
    $this->mode = $mode;
  }


  /**
   * RETURN SITEMAP
   *
   * @return            (string) sitemap [xml|json]
   */
  public function get()
  {
    switch($this->mode)
    {
      case'json':
        return json_encode($this->db_articles);
      break;

      default:
        $xml_sitemap = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL.
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;

        foreach($this->db_articles as $id=>$clangs)
        {
          foreach($clangs as $art)
          {
            $xml_sitemap .= self::xml_loc_fragment($art['loc'],$art['lastmod'],$art['changefreq'],$art['priority']);
          }
        }

        $xml_sitemap .= '</urlset>';

        return $xml_sitemap;
    }
  }


  /**
   * SEND SITEMAP
   */
  public function send()
  {
    $map = self::get();

    while(ob_get_level()){
      ob_end_clean();
    }

    switch($this->mode)
    {
      case'json':
        header('Content-Type: application/json');
      break;
      case'xml':
        header('Content-Type: application/xml');
      break;
      default:
    }
    header('Content-Length: '.strlen($map));
    echo $map;
    die();
  }


}

?>
