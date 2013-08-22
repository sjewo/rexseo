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


class rexseo_meta {

  private $article_id;
  private $is_cur_article;
  private $clang;
  private $start_article_id;
  private $def_keys;
  private $def_desc;
  private $title_schema;
  private $base_url;
  private $install_subdir;
  private $params_starter;
  private $servername;
  private $http_host;
  private $protocol;
  private $rex_is_iso;
  private $encoder;


  /**
   * constructor
   *
   * @param int $article_id optional, else current article used
   * @param int $clang      optional, else current clang used
   * @return void
   */
  public function rexseo_meta($article_id=null,$clang=null)
  {
    global $REX;
    $this->article_id       = !$article_id ? $REX['ARTICLE_ID'] : (int) $article_id;
    $this->clang            = !$clang      ? $REX['CUR_CLANG']  : (int) $clang;
    $this->is_cur_article   = $this->article_id == $REX['ARTICLE_ID'] && $this->clang == $REX['CUR_CLANG'] ? true : false;
    $this->start_article_id = $REX['START_ARTICLE_ID'];
    $this->title_schema     = $REX['ADDON']['rexseo']['settings']['title_schema'];
    $this->install_subdir   = $REX['ADDON']['rexseo']['settings']['install_subdir'];
    $this->params_starter   = $REX['ADDON']['rexseo']['settings']['params_starter'];
    $this->servername       = $REX['SERVERNAME'];
    $this->http_host        = $_SERVER['HTTP_HOST'];
    $this->def_keys         = $REX['ADDON']['rexseo']['settings']['def_keys'];
    $this->def_desc         = $REX['ADDON']['rexseo']['settings']['def_desc'];
    $this->protocol         = isset($REX['PROTOCOL']) ? $REX['PROTOCOL'] : self::get_protocol();
    $this->base_url         = $this->protocol.$this->http_host.'/'.$this->install_subdir;
    $this->rex_is_iso       = rex_lang_is_utf8() ? false : true;
    $this->encoder          = 'htmlspecialchars';
  }


  /**
   * returns meta title of article
   *
   * @param string $title_schema optional
   *
   * TITLE SCHEME PRIORITIES:
   * 1. art_rexseo_title
   * 2. scheme from method param
   * 3. default scheme from addon
   *
   * Title Scheme Placeholders:
   * %B -> breadcrumb
   * %N -> article name
   * %C -> article category name
   * %S -> REX SERVERNAME (http_host as fallback)
   *
   * @return string meta title
   */
  public function get_title($title_schema = null)
  {
    $title_schema = !$title_schema ? $this->title_schema : $title_schema;
    $curart = OOArticle::getArticleById($this->article_id);
    $art_rexseo_title = $curart->getValue('art_rexseo_title');

    // GET PARRENT CATS
    $parents = $curart->getParentTree();
    if ($curart->getValue('name') != $curart->getValue('catname'))
    {
      array_push($parents, $curart);
    }

    if (empty($parents))
    {
      $parents[0]=$curart;
    }
    else
    {
      $parents = array_reverse($parents);
    }

    // BREADCRUMB TITLE
    $B = '';
    foreach ($parents as $parent)
    {
      if (OOArticle::isValid($parent))
      {
        $B .= ' - '.$parent->getValue('name');
      }
      elseif (OOCategory::isValid($parent))
      {
        $B .= ' - '.$parent->getValue('catname');
      }
    }
    $B = trim($B," -");

    // CATEGORY NAME
    $C = $curart->getValue('catname');

    // ARTICLE NAME
    $N = $curart->getValue('name');

    // REX SERVERNAME (HTTP_HOST AS FALLBACK)
    $S = $this->servername!='' ? $this->servername : $this->http_host;

    // CUSTOM REXSEO TITLE (OVERRIDES ANY OTHER TITLE/SCHEME)
    if($art_rexseo_title!='')
    {
        $title_schema = $art_rexseo_title;
    }

    // EXTENSION POINT
    $title_schema = rex_register_extension_point('REXSEO_META_TITLE', $title_schema, array('article_id'=>$this->article_id,'%B'=>$B,'%N'=>$N,'%S'=>$S,'%C'=>$C));

    // REPLACE PLACEHOLDERS
    $title = str_replace(array('%B','%N','%S','%C'),array($B,$N,$S,$C),$title_schema);

    return self::encode_string($title);
  }


  /**
   * returns meta keywords of article
   *
   * @return string meta keywords
   */
  public function get_keywords()
  {
    $keys = self::getMetaField($this->article_id,"art_keywords",$this->def_keys[$this->clang]);

    // EXTENSION POINT
    $keys = rex_register_extension_point('REXSEO_META_KEYWORDS', $keys, array('article_id'=>$this->article_id,'default'=>$this->def_keys[$this->clang]));

    $keys = self::sanitize_keywords($keys);

    return self::encode_string($keys);
  }


  /**
   * returns meta description of article
   *
   * @return string meta description
   */
  public function get_description()
  {
    $desc = self::getMetaField($this->article_id,"art_description",$this->def_desc[$this->clang]);

    // EXTENSION POINT
    $desc = rex_register_extension_point('REXSEO_META_DESCRIPTION', $desc, array('article_id'=>$this->article_id,'default'=>$this->def_desc[$this->clang]));

    $desc = str_replace(array("\r","\n"),' ',$desc);
    $desc = trim($desc);

    return self::encode_string($desc);
  }


  /**
   * returns meta canonical url of article
   *
   * @return string meta canonical url
   */
  public function get_canonical()
  {
    if(isset($_SERVER['REQUEST_URI']) && $this->is_cur_article==true)
    {
      $canonical = preg_replace('/[?|'.$this->params_starter.'].*/','',$_SERVER['REQUEST_URI']);
    }
    else
    {
      $canonical = rex_getURL($article_id,$this->clang);
    }
    $canonical = self::getMetaField($this->article_id,'art_rexseo_canonicalurl',$canonical);

    return $this->protocol.$this->http_host.'/'.ltrim($canonical,'/');
  }


  /**
   * returns meta base url of article
   *
   * @return string meta base url
   */
  public function get_base()
  {
    return $this->base_url;
  }


  /**
   * returns metas as html
   *
   * @return string  all metas as html code
   */
  public function get_html($indent='  ')
  {
    $html  = PHP_EOL.$indent.'<base href="'.self::get_base().'" />';
    $html .= PHP_EOL.$indent.'<title>'.self::get_title().'</title>';
    $html .= PHP_EOL.$indent.'<meta name="keywords" content="'.self::get_keywords().'" />';
    $html .= PHP_EOL.$indent.'<meta name="description" content="'.self::get_description().'" />';
    $html .= PHP_EOL.$indent.'<link rel="canonical" href="'.self::get_canonical().'" />'.PHP_EOL;
    return $html;
  }


  /**
   * returns connection protocol
   *
   * @return string protocol [https:// or http://]
   */
  private function get_protocol()
  {
    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://';
  }


  /**
   * encodes meta strings according to settings and class used:
   * default for static rexseo methods: htmlentities
   * default for rexseo_meta methods: htmlspecialchars
   *
   * @param string meta text
   * @return string encoded text
   */
  private function encode_string($str)
  {
    switch($this->encoder)
    {
      case'htmlentities':
          return $this->rex_is_iso ? htmlentities($str,ENT_QUOTES) : htmlentities($str,ENT_QUOTES,'UTF-8');
      break;
      case'htmlspecialchars':
          return $this->rex_is_iso ? htmlspecialchars($str,ENT_QUOTES) : htmlspecialchars($str,ENT_QUOTES,'UTF-8');
      break;
    }
  }


  /**
   * sets encode type
   *
   * @param string encode type [htmlentities or htmlspecialchars]
   */
  public function set_encode($type)
  {
    switch($type)
    {
      case'htmlentities':
        $this->encoder = 'htmlentities';
      break;

      default:
        $this->encoder = 'htmlspecialchars';
    }
  }


  /**
   * replaces linebreaks, unnecessary whitespace, etc. in keywords
   *
   * @param string keywords
   * @return string sanitized keywords
   */
  private function sanitize_keywords($keys)
  {
    $keys = str_replace(array("\r","\n"),' ',$keys);
    $keys = explode(',',$keys);
    foreach ($keys as $k=>$v)
    {
      $keys[$k] = trim($v);
      if($keys[$k]=='')
      {
        unset($keys[$k]);
      }
    }
    $keys = implode(',',$keys);
    return $keys;
  }


  /**
   * returns values of article meta fields
   *
   * @param int $article_id sets article to get meta from
   * @param int $metafield specifies which meta filed to return
   * @param string $default default value returned if original value empty
   * @param bool $get_parrent if set to TRUE & article's meta value empty: get value from parent article
   * @return string meta field value
   */
  private function getMetaField($article_id,$metafield='file',$default='',$get_parrent=null)
  {
    $meta = OOArticle::getArticleById($article_id);
    $value = '';

    if(($meta->getValue($metafield))!="")
    {
      $value=$meta->getValue($metafield);
    }
    else
    {
      if($get_parrent==true)
      {
        $cat = OOCategory::getCategoryById($article_id);
        if ($cat->getParent())
        {
          $cat   = $cat->getParent();
          $value = self::getMetaField($cat->getValue('id'),$metafield,$default,$get_parrent);
        }
      }
    }

    return ($value == '') ? $default : $value;
  }

}


/**
 * COMPAT CLASS REXSEO: re-implements deprecated static methods
 */
class rexseo
{
  static public function title($article_id=null)
  {
    $meta = new rexseo_meta($article_id);
    $meta->set_encode('htmlentities');
    return $meta->get_title();
  }

  static public function keywords($article_id=null)
  {
    $meta = new rexseo_meta($article_id);
    $meta->set_encode('htmlentities');
    return $meta->get_keywords();
  }

  static public function description($article_id=null)
  {
    $meta = new rexseo_meta($article_id);
    $meta->set_encode('htmlentities');
    return $meta->get_description();
  }

  static public function canonical($article_id=null)
  {
    $meta = new rexseo_meta($article_id);
    return $meta->get_canonical();
  }

  static public function base()
  {
    $meta = new rexseo_meta;
    return $meta->get_base();
  }

  static public function metas()
  {
    $meta = new rexseo_meta;
    return $meta->get_html();
  }
}
