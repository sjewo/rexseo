<?php
/**
 * skip_empty_article - RexSEO Plugin
 *
 * @see https://github.com/gn2netwerk/rexseo
 *
 * @package redaxo 4.3.x/4.4.x
 * @package rexseo 1.5.x
 * @version 0.1.0
 */


if (!$REX['REDAXO']) {
  return;
}

$myself = 'skip_empty_article';
$myroot = $REX['INCLUDE_PATH'].'/addons/rexseo/plugins/'.$myself;


// EDIT PATHLIST
////////////////////////////////////////////////////////////////////////////////
rex_register_extension('REXSEO_PATHLIST_CREATED', function($params) use($REX) {

  $REXSEO_IDS  = $params['subject']['REXSEO_IDS'];
  $REXSEO_URLS = $params['subject']['REXSEO_URLS'];

  foreach($REXSEO_IDS as $article_id => $clangs) {
    foreach($clangs as $clang => $url) {                                                                                #FB::group($article_id.'::'.$clang.'::'.$url['url'], array("Collapsed"=>false));
      if(skip_check::isStartArticle($article_id) &&
         skip_check::isEmpty($article_id,$clang) &&
         skip_check::isOnline($article_id,$clang)) {
        $redirect_id = skip_check::getRedirect($article_id,$clang);                                                     #FB::log($redirect_id,' $redirect_id for '.$article_id);
        $params['subject']['REXSEO_URLS'][$REXSEO_IDS[$article_id][$clang]['url']]['id'] = (int) $redirect_id;
        $params['subject']['REXSEO_URLS'][$REXSEO_IDS[$article_id][$clang]['url']]['status'] = 302;
      }
    }                                                                                                                   #FB::groupEnd();
  }
  return $params['subject'];
});



// CLASS
////////////////////////////////////////////////////////////////////////////////
class skip_check
{

  public static function getRedirect($article_id,$clang=false,$ignore_articles=true)
  {                                                                                                                     #FB::group(__CLASS__.'::'.__FUNCTION__.'::'.$article_id.'::'.$clang, array("Collapsed"=>false));
    if(!$ignore_articles){
      foreach(self::getCategoryArticles($article_id,$clang) as $article){                                               #FB::log($article,' $article');
        if($article->_id != $article_id && self::isEmpty($article->_id)===false){                                       #FB::groupEnd();
          return $article->_id;
          break;
        }
      }
    }

    $category_id = self::getCategoryId($article_id,$clang=false);
    foreach (self::getSubcategories($category_id) as $OOCat) {
      if(!self::isEmpty($OOCat->_id,$OOCat->_clang)){                                                                   #FB::log($OOCat->_id,'NOT EMPTY');#FB::groupEnd();
        return $OOCat->_id;
        break;
      }else{                                                                                                            #FB::log($OOCat->_id,'EMPTY');
        return self::getRedirect($OOCat->_id,$OOCat->_clang,$ignore_articles);
      }
    }                                                                                                                   #FB::groupEnd(); #FB::warn('why am i here?');
                                                                                                                        #FB::groupEnd();
    return false;
  }


  public static function isEmpty($article_id,$clang=false)
  {
    global $REX;

    $clang      = !$clang ? $REX['CUR_CLANG'] : $clang;
    $cache_file = $REX['INCLUDE_PATH'].'/generated/articles/'.$article_id.'.'.$clang.'.content';

    if (!file_exists($cache_file)) {
      rex_generateArticleContent($article_id, $clang);
    }

    return (filesize($cache_file)<=2) ? true : false;
  }


  public static function isStartArticle($article_id)
  {
    return OOArticle::getArticleById($article_id)->_startpage==1
           ? true
           : false;
  }


  public static function getCategoryId($article_id,$clang=false)
  {
    return OOArticle::getArticleById($article_id,$clang)->getCategoryId();
  }


  public static function getCategoryArticles($category_id,$clang=false)
  {
    return OOArticle::getArticlesOfCategory($category_id,$clang);
  }


  public static function getSubcategories($category_id)
  {
    return ($category_id < 1)
           ? OOCategory::getRootCategories(true)
           : OOCategory::getChildrenById($category_id, true);
  }


  public static function getStartarticle($category_id)
  {
    return OOCategory::getCategoryById($category_id)->getStartArticle();
  }


  public static function isOnline($article_id, $clang=false, $inheritance=true)
  {
    $OOart  = OOArticle::getArticleById($article_id,$clang);
    $status = $OOart->getValue('status');
    if(!$inheritance || !$status) {
      return $status;
    } else {
      foreach($OOart->getPathAsArray() as $k => $id) {
        if(!self::isOnline($id, $clang, false)) {
          return false;
        }
      }
      return true;
    }
  }


}
