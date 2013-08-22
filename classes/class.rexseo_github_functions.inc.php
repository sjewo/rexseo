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



/**
 * Connect to GITHUB API v3
 **/
class rexseo_github_functions extends rexseo_github_base
{

  public function getLatestVersion($current=false,$return='link',$regex='/([0-9]+\.[0-9]+\.[0-9]+).*/')
  {
    global $REX;
    $valid_returns = array('link','version');

    if(!in_array($return,$valid_returns))
      $this->registerError('wrong return type for getLatest() provided');

    if(!$current)
      $this->registerError('no current version for getLatest() provided');

    if(!$this->error)
    {
      $this->getApiResponse($this->api_baseurl.'tags');

      if(!is_array($this->api_response)){
        $this->registerError('unexpected API response');
        return;
      }

      // SORT VERSIONS FROM API RESPONSE
      $highest_version = '0.0.0';
      foreach($this->api_response as $k => $tag)
      {
        if(preg_match($regex,$tag->name,$match)===1)
        {
          $tag->version = $match[1];
          if(version_compare($highest_version, $tag->version, '<'))
          {
            $highest_version = $tag->version;
            $highest_version_index = $k;
          }
        }
      }

      if(count($this->api_response)>0)
      {
        $latest = $this->api_response[$highest_version_index];
        $match = array();
        preg_match($regex,$latest->name,$match);
        if(count($match)>0)
        {
          if(version_compare($match[1],$current)>0)
          {
            switch($return)
            {
              case 'link':
                return '<a class="jsopenwin" href="'.$latest->zipball_url.'">'.$latest->name.'</a>';
                break;
              default:
                return $match[1];
            }
            break;
          }
        }
      }
    }
    else
    {
      return '';
    }

  }

  public function getList($type=false)
  {
    global $REX;

    if(!$this->error)
    {
      if(!$type || !in_array($type,$this->api_sections))
      {
        $this->registerError('wrong or no list type provided',E_USER_ERROR);
        return '<p>'.$this->error.'</p>';
      }

      $this->getApiResponse($this->api_baseurl.$type);

      switch($type)
      {
        case 'downloads':
            $head  = '<h1>Downloads: <a class="jsopenwin" target="_blank" href="'.$this->html_baseurl.'downloads">'.$this->html_baseurl.'downloads</a></h1>';
        break;

        case 'issues':
            $head  = '<h1>Issues: <a class="jsopenwin" target="_blank" href="'.$this->html_baseurl.'issues">'.$this->html_baseurl.'issues</a></h1>';
        break;

        case 'commits':
            $head  = '<h1>Commits: <a class="jsopenwin" target="_blank" href="'.$this->html_baseurl.'commits">'.$this->html_baseurl.'commits</a></h1>';
        break;

        case 'tags':
            $head  = '<h1>Downloads: <a class="jsopenwin" target="_blank" href="'.$this->html_baseurl.'tags">'.$this->html_baseurl.'tags</a></h1>';
        break;
      }

      $list_items = '<li>no entries</li>';

      if(count($this->api_response)>0)
      {
        $list_items = '';
        $stack      = $this->api_response;

        foreach($stack as $item)
        {
          switch($type)
          {
            case 'downloads':
                $date  = '<strong>'.date('d.m.Y',strtotime($item->created_at)).'</strong> '.date('H:i',strtotime($item->created_at));
                $href  = $item->html_url;
                $title = $item->name;
                $class = '';
                $target = '';
            break;

            case 'issues':
                $date  = '<strong>'.date('d.m.Y',strtotime($item->created_at)).'</strong> '.date('H:i',strtotime($item->created_at));
                $href  = $item->html_url;
                $title = $item->title;
                $class = 'jsopenwin';
                $target = 'target="_blank"';
            break;

            case 'commits':
                $date  = '<strong>'.date('d.m.Y',strtotime($item->commit->committer->date)).'</strong> '.date('H:i',strtotime($item->commit->committer->date));
                $href  = $this->html_baseurl.'commit/'.$item->sha;
                $title = preg_replace('/git-svn-id.*/','',$item->commit->message);
                $class = 'jsopenwin';
                $target = 'target="_blank"';
            break;

            case 'tags':
                // NOTE ENOUGH DATA IN TAGS RESPONSE -> GET DATES FROM INDIVIDUAL COMMITS
                self::getApiResponse($this->api_baseurl.'git/commits/'.$item->commit->sha);

                $date  = '<strong>'.date('d.m.Y',strtotime($this->api_response->author->date)).'</strong> '.date('H:i',strtotime($this->api_response->author->date));
                $href  = $item->zipball_url;
                $title = $item->name.'.zip';
                $class = '';
                $target = '';
            break;
          }
          $list_items .= '<li><span class="github-date">'.$date.'</span><a class="'.$class.'" '.$target.' href="'.$href.'">'.$title.'</a></li>';
        }
      }

      $html = $head;
      $html .= '<ul class="github-api">';
      $html .= $list_items;
      $html .= '</ul>';

      return $html;
    }
    else
    {
      return '<p>'.$this->error.'</p>';
    }
  }

}

?>
