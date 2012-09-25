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
 * @version 1.5.0
 */

class github_connect
{
  private $error;
  private $access_method;
  private $repo_owner;
  private $repo_name;
  private $html_baseurl;
  private $api_baseurl;
  private $api_sections;

  public  $api_response;


  /* private */
  private function registerError($err=false,$err_type=false)
  {
      $err = 'CLASS GITHUB_CONNECT: '.$err.'.';
      $this->error .= $err.'<br />'.PHP_EOL;
      if($err_type)
        trigger_error($err, $err_type);
  }


  /* private */
  private function getApiResponse($url)
  {
    switch($this->access_method)
    {
      case'curl':
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        curl_close ($ch);
      break;

      case'fopen':
        $response = file_get_contents($url, 'r');
      break;

      case'socket':
        $socket = rex_socket::createByUrl($url);
        $socket->doGet();
        $response = $socket->getBody();
      break;
    }

    $this->api_response = json_decode($response);
  }


  /* constructor */
  public function __construct($repo_owner=false, $repo_name=false)
  {
    global $REX;

    $this->access_method = ini_get('allow_url_fopen')   ? 'fopen' : false;
    $this->access_method = function_exists('curl_init') ? 'curl'  : $this->access_method;
    $this->access_method = class_exists('rex_socket')   ? 'socket': $this->access_method;

    $this->error = $this->access_method==false ? 'no access method available' : false;

    $this->repo_owner = !$repo_owner ? $this->registerError('no repo owner provided',E_USER_ERROR) : $repo_owner;
    $this->repo_name  = !$repo_name  ? $this->registerError('no repo name provided' ,E_USER_ERROR) : $repo_name;

    $this->api_baseurl = 'https://api.github.com/repos/'.$this->repo_owner.'/'.$this->repo_name.'/';
    $this->api_sections = array('downloads','commits','issues');

    $this->html_baseurl = 'https://github.com/'.$this->repo_owner.'/'.$this->repo_name.'/';
  }


  public function getLatestVersion($current=false,$return='link',$regex='/([0-9]+\.[0-9]+\.[0-9]+).*\.zip/')
  {
    global $REX;
    $valid_returns = array('link','version');

    if(!in_array($return,$valid_returns))
      $this->registerError('wrong return type for getLatest() provided');

    if(!$current)
      $this->registerError('no current version for getLatest() provided');

    if(!$this->error)
    {
      $this->getApiResponse($this->api_baseurl.'downloads');

      if(count($this->api_response)>0)
      {
        $latest = $this->api_response[0];
        $match = array();
        preg_match($regex,$latest->name,$match);
        if(count($match)>0)
        {
          if(version_compare($match[1],$current)>0)
          {
            switch($return)
            {
              case 'link':
                return '<a class="jsopenwin" href="'.$latest->html_url.'">'.$latest->name.'</a>';
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
      }

      $list_items = '<li>no entries</li>';

      if(count($this->api_response)>0)
      {
        $list_items = '';

        foreach($this->api_response as $item)
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
