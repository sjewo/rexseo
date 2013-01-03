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
    foreach($clangs as $clang => $url) {
      if(skip_check::isStartArticle($article_id) && skip_check::isEmpty($article_id,$clang)){
        $redirect_id = skip_check::getRedirect($article_id,$clang);
        $params['subject']['REXSEO_URLS'][$REXSEO_IDS[$article_id][$clang]['url']]['id'] = (int) $redirect_id;
        $params['subject']['REXSEO_URLS'][$REXSEO_IDS[$article_id][$clang]['url']]['status'] = 302;
      }
    }
  }
  return $params['subject'];
});



// CLASS
////////////////////////////////////////////////////////////////////////////////
class skip_check
{

  public static function getRedirect($article_id,$clang=false,$ignore_articles=true)
  {
    if(!$ignore_articles){
      foreach(skip_check::getCategoryArticles($article_id,$clang) as $article){
        if($article->_id != $article_id && skip_check::isEmpty($article->_id)===false){
          return $article->_id;
          break;
        }
      }
    }

    $category_id = skip_check::getCategoryId($article_id,$clang=false);
    foreach (skip_check::getSubcategories($category_id) as $OOCat) {
      if(!skip_check::isEmpty($OOCat->_id,$OOCat->_clang)){
        return $OOCat->_id;
        break;
      }
    }

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

}
