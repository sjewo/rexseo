<?php
/**
 * redirects_manager - RexSEO Plugin
 *
 * @link https://github.com/gn2netwerk/rexseo
 *
 * @author http://rexdev.de
 * @author dh[at]gn2-netwerk[dot]de Dave Holloway
 *
 * @package redaxo 4.3.x/4.4.x
 * @version 0.1.0 dev
 */


// GET PARAMS
////////////////////////////////////////////////////////////////////////////////
$myself    = 'redirects_manager';
$myroot    = $REX['INCLUDE_PATH'].'/addons/rexseo/plugins/'.$myself.'/';
$subpage   = rex_request('subpage', 'string');
$minorpage = rex_request('minorpage', 'string');
$func      = rex_request('func'   , 'string');
$id        = rex_request('id', 'int');



// SETTINGS
/////////////////////////////////////////////////////////////////////////////////
$table      = $REX['TABLE_PREFIX'].'rexseo_redirects';
$pagination = 15;

// BATCH SUBMIT FUNCTION
/////////////////////////////////////////////////////////////////////////////////
if($func=='batch-submit')
{
  $batch = rex_request('redirects','string','false');
  if($batch!='false')
  {
    $db = rex_sql::factory();
    $qry = 'INSERT INTO `'.$table.'` (`id`, `createdate`, `updatedate`, `expiredate`, `creator`, `status`, `from_url`, `to_article_id`, `to_clang`, `http_status`) VALUES';
    $batch = rexseo_301_2_array($batch);
    $date = time();
    $expire = $date + ($REX['ADDON']['rexseo']['settings']['default_redirect_expire']*24*60*60);
    foreach($batch as $k=>$v)
    {
      $qry .= PHP_EOL.'(\'\', \''.$date.'\', \''.$date.'\', \''.$expire.'\', \''.$REX['USER']->getValue('login').'\', 1, \''.$k.'\', '.$v['article_id'].', '.$v['clang'].', 301),';
    }
    $qry = rtrim($qry,',').';';

    if($db->setQuery($qry))
      echo rex_info('Weiterleitungen wurden in Tabelle gespeichert.');

    if(rexseo_htaccess_update_redirects())
      echo rex_info('Weiterleitungen wurden in die .htaccess geschrieben.');
  }
}


// INCLUDES
////////////////////////////////////////////////////////////////////////////////
require_once $myroot.'../../functions/function.rexseo_helpers.inc.php';
require_once $myroot.'../../classes/class.rexseo_select.inc.php';
#require_once $myroot.'../../classes/class.rexseo_rewrite.inc.php';


// BACKEND CSS
////////////////////////////////////////////////////////////////////////////////
$includes = '
<!-- REXSEO -->
  <link rel="stylesheet" type="text/css" href="../files/addons/rexseo/backend.css" media="screen, projection, print" />
  <script type="text/javascript" src="../files/addons/rexseo/jquery.highlight-3.yui.js"></script>
  <script type="text/javascript" src="../files/addons/rexseo/jquery.autogrow-textarea.js"></script>
  <script type="text/javascript" src="../files/addons/rexseo/jquery.scrollTo-1.4.2-min.js"></script>
<!-- /REXSEO -->
';
$include_func = 'return $params["subject"].\''.$includes.'\';';
rex_register_extension('PAGE_HEADER', create_function('$params',$include_func));


// PAGE HEAD
////////////////////////////////////////////////////////////////////////////////
require $REX['INCLUDE_PATH'] . '/layout/top.php';

rex_title('RexSEO <span class="addonversion">'.$REX['ADDON']['version']['rexseo'].'</span>',$REX['ADDON']['rexseo']['SUBPAGES']);


// PAGE BODY
////////////////////////////////////////////////////////////////////////////////
if($func == '' || $func=='batch-submit')
{
   echo '<div class="rex-addon-output">
   <h2 class="rex-hl2">Redirects <span style="color:silver;font-size:12px;">(DB Tabelle: '.$table.')</span></h2>';

  $query = 'SELECT `id`, `from_url`, `status`, `to_article_id`, `to_clang`, `http_status`, `expiredate`, `createdate`, `updatedate`, `creator` FROM '.$table.' ORDER BY `createdate` DESC';
  $list = new rex_list($query,$pagination,'data');
  $list->debug = false;


  $imgHeader = '<a href="'. $list->getUrl(array('func' => 'add')) .'"><img src="media/metainfo_plus.gif" alt="add" title="add" /></a>';
  $list->addColumn($imgHeader,'<img src="media/metainfo.gif" alt="field" title="field" />',0,array('<th class="rex-icon">###VALUE###</th>','<td class="rex-icon">###VALUE###</td>'));
  $list->setColumnParams($imgHeader,array('func' => 'edit', 'id' => '###id###'));


  $list->removeColumn     ('id'            );
  $list->setColumnSortable('from_url'      );
  $list->setColumnSortable('status'        );

  $list->addColumn        ('target','',3);

  $list->setColumnSortable('to_article_id' );
  $list->setColumnSortable('to_clang'      );
  $list->setColumnSortable('http_status'   );
  $list->removeColumn     ('createdate'    );
  $list->removeColumn     ('updatedate'    );
  $list->setColumnSortable('expiredate'    );
  $list->removeColumn     ('creator'       );


  $list->setColumnLabel('id'            ,'ID');
  $list->setColumnLabel('from_url'      ,'alte URL');
  $list->setColumnLabel('status'        ,'Status');
  $list->setColumnLabel('expiredate'    ,'Expire');
  $list->setColumnLabel('target'        ,'Ziel-Artikel');
  $list->setColumnLabel('to_article_id' ,'ID');
  $list->setColumnLabel('to_clang'      ,'CLANG');
  $list->setColumnLabel('http_status'   ,'HTTP Status');
  $list->setColumnLabel('creator'       ,'Erzeuger');

  function list_status()
  {
    global $list;
    $str = $list->getValue('status')==1 ? '<span style="color:#107C2C;">aktiv</span>' : '<span style="color:#EA1144;">inaktiv</span>';
    return $str;
  }
  $list->setColumnFormat('status'  ,'custom', 'list_status');

  function list_target()
  {
    global $list;
    return urldecode(rex_getUrl($list->getValue('to_article_id'),$list->getValue('to_clang')));
  }
  $list->setColumnFormat('target'  ,'custom', 'list_target');

  function list_from_url()
  {
    global $list;
    return urldecode($list->getValue('from_url'));
  }
  $list->setColumnFormat('from_url'  ,'custom', 'list_from_url');

  function list_expiredate()
  {
    global $list;
    return date('d.m.y',$list->getValue('expiredate'));
  }
  $list->setColumnFormat('expiredate'  ,'custom', 'list_expiredate');

  $list->setColumnParams('id'            ,array('func' => 'edit', 'id' => '###id###'));
  $list->setColumnParams('from_url'      ,array('func' => 'edit', 'id' => '###id###'));
  $list->setColumnParams('status'        ,array('func' => 'edit', 'id' => '###id###'));
  $list->setColumnParams('target'        ,array('func' => 'edit', 'id' => '###id###'));
  $list->setColumnParams('expiredate'    ,array('func' => 'edit', 'id' => '###id###'));
  $list->setColumnParams('to_article_id' ,array('func' => 'edit', 'id' => '###id###'));
  $list->setColumnParams('to_clang'      ,array('func' => 'edit', 'id' => '###id###'));
  $list->setColumnParams('http_status'   ,array('func' => 'edit', 'id' => '###id###'));
  $list->show();

  echo '</div>';

// SETTINGS FORM
////////////////////////////////////////////////////////////////////////////////
$default_redirect_expire = !isset($REX['ADDON'][$myself]['settings']['default_redirect_expire'])
                         ? 60
                         : $REX['ADDON'][$myself]['settings']['default_redirect_expire'];

$auto_redirects = !isset($REX['ADDON'][$myself]['settings']['auto_redirects'])
                ? ''
                : $REX['ADDON'][$myself]['settings']['auto_redirects'];

$auto_redirects_select = new rexseo_select();
$auto_redirects_select->setSize(1);
$auto_redirects_select->setName('auto_redirects');
$auto_redirects_select->addOption('Inaktiv',0);
$auto_redirects_select->addOption('Vollautomatisch (Redirects anlegen & aktivieren)',1);
$auto_redirects_select->addOption('Halbautomatisch (Redirects anlegen aber inaktiv setzen)',2);
$auto_redirects_select->setSelected($auto_redirects);



echo '
<div class="rex-addon-output">
  <div class="rex-form">

  <form action="index.php" method="post">
    <input type="hidden" name="page"                   value="rexseo" />
    <input type="hidden" name="subpage"                value="redirect_manager" />
    <input type="hidden" name="func"                   value="update_redirect_settings" />

      <fieldset class="rex-form-col-1">
        <legend style="font-size: 1.333em;color: #336699;">Settings</legend>
        <div class="rex-form-wrapper">

          <div class="rex-form-row">
            <p class="rex-form-col-a rex-form-select">
              <label for="auto_redirects" class="helptopic">Auto-Redirects:</label>
                '.$auto_redirects_select->get().'
            </p>
          </div><!-- /rex-form-row -->


          <div class="rex-form-row">
            <p class="rex-form-col-a rex-form-text">
              <label for="default_redirect_expire" class="helptopic">Default Expire:</label>
              <input id="default_redirect_expire" class="rex-form-text" style="width:50px;" type="text" name="default_redirect_expire" value="'.stripslashes($default_redirect_expire).'" /> Tage
            </p>
          </div><!-- /rex-form-row -->

        </div><!-- /rex-form-wrapper -->
      </fieldset>


      <fieldset class="rex-form-col-1">
        <legend>&nbsp;</legend>
        <div class="rex-form-wrapper">

          <div class="rex-form-row rex-form-element-v2">
            <p class="rex-form-submit">
              <input class="rex-form-submit" type="submit" id="sendit" name="sendit" value="Einstellungen speichern" />
            </p>
          </div><!-- /rex-form-row -->

        </div><!-- /rex-form-wrapper -->
      </fieldset>

  </form>
  </div><!-- /rex-addon-output -->
</div><!-- /rex-form -->

<script type="text/javascript">
<!--
jQuery(function($) {

  jQuery(document).ready(function() {
    if($("#rewrite_params").val()!=1)
    {
      $("#params_starter_span").hide();
      $("#params_starter").hide();
    }

    // AUTOMATIC HELP TOPIC LINK
    $(".helptopic").each(function() {
    var p = $(this).html().split(":");
    p[1] = \' <a class="help-icon" title="Hilfe zum Thema anzeigen" href="index.php?page=rexseo&subpage=help&chapter=settings&highlight=\'+escape(p[0]+\':\')+\'">?</a>\'+p[1];
    $(this).html(p.join(":"));
    });
  });

});
//-->
</script>

';

// BATCH SUBMIT FORM
////////////////////////////////////////////////////////////////////////////////
echo '

<div class="rex-addon-output">
  <div class="rex-form">

  <form action="index.php" method="post">
    <input type="hidden" name="page"                   value="rexseo" />
    <input type="hidden" name="subpage"                value="redirects" />
    <input type="hidden" name="func"                   value="batch-submit" />

      <fieldset class="rex-form-col-1">
        <legend style="font-size: 1.333em;color: #336699;">Legacy Batch Submit</legend>
        <div class="rex-form-wrapper">

          <div class="rex-form-row">
            <p class="rex-form-col-a rex-form-select">
              <label for="robots" class="helptopic">Weiterleitungen:<br /> <br /><em style="color:gray;font-size:10px;">url article_id clang<br />z.B. foo/bar.html 4 0</em></label>
              <textarea id="rexseo_redirects" name="redirects"></textarea>
            </p>
          </div><!-- /rex-form-row -->

        </div><!-- /rex-form-wrapper -->

        <div class="rex-form-wrapper">

          <div class="rex-form-row rex-form-element-v2">
            <p class="rex-form-submit">
              <input class="rex-form-submit" type="submit" id="sendit" name="sendit" value="Weiterleitungen einfügen" />
            </p>
          </div><!-- /rex-form-row -->

        </div><!-- /rex-form-wrapper -->
      </fieldset>

  </form>
  </div><!-- /rex-addon-output -->
</div><!-- /rex-form -->
';



}

// ADD/EDIT FORM
////////////////////////////////////////////////////////////////////////////////
elseif($func == 'edit' || $func == 'add')
{
  echo '<div class="rex-addon-output">';

  if($func == 'edit')
  {
    echo '<h2 class="rex-hl2">Redirect bearbeiten <span style="color:silver;font-size:12px;">(ID: '.$id.')</span></h2>';
  }
  else
  {
    echo '<h2 class="rex-hl2">Neuen Datensatz anlegen</h2>';
  }


  $form = new rex_form($table,'Redirect','id='.$id,'post',false);

  $field =& $form->addSelectField('status');
  $field->setLabel('Status');
  $select =& $field->getSelect();
  $select->setSize(1);
  $select->addOption('aktiv',1);
  $select->addOption('inaktiv',0);

  $field = &$form->addTextField('from_url');
  $field->setLabel('alte URL');

  $field = &$form->addLinkmapField('to_article_id');
  $field->setLabel('umleiten nach');


  $field =& $form->addSelectField('http_status');
  $field->setLabel('HTTP Status');
  $select =& $field->getSelect();
  $select->setSize(1);
  $select->addOption('301 Moved Permanently',301);
  $select->addOption('302 Found',302);
  $select->addOption('303 See Other',303);
  $select->addOption('307 Temporary Redirect',307);

  $field = &$form->addTextField('to_clang',null,array('style'=>'display:none;'));
  $stored_clang = $form->getElement('Redirect','to_clang')->value;

  $form->addFieldset('Infos');

  if($func == 'edit')
  {
    $field = &$form->addReadOnlyField('createdate',null,array('class'=>'rex-form-read unix-date'));
    $field->setLabel('Erstellungsdatum');

    $field = &$form->addReadOnlyField('updatedate',null,array('class'=>'rex-form-read unix-date'));
    $field->setLabel('Änderungsdatum');
  }

  $expire = ($func == 'edit') ? null : time() + ($REX['ADDON']['rexseo']['settings']['default_redirect_expire']*24*60*60);

  $field = &$form->addTextField('expiredate',$expire,array('class'=>'rex-form-text unix-date-picker','id'=>'rex_rexseo_redirects_Infos_expiredate'));
  $field->setLabel('Verfallsdatum');

  if($func == 'edit')
  {
    $field = &$form->addReadOnlyField('creator');
    $field->setLabel('Ersteller');
  }


  if($func == 'edit')
  {
    $form->addParam('id', $id);
    $form->addHiddenField('updatedate', time());
  }

  if($func == 'add')
  {
    $form->addHiddenField('creator', $REX['USER']->getValue('login'));
    $form->addHiddenField('updatedate', time());
    $form->addHiddenField('createdate', time());
  }

  $form->show();

  echo '</div>

<div id="clang-hack" clang="" article_id="" style="/*display:none*/">
<p id="clang-link-buttons" class="rex-widget-icons rex-widget-1col">
  <span class="rex-widget-column rex-widget-column-first">
  <span style="float:left;margin-top:3px;">Sprache:</span>
  ';

foreach($REX['CLANG'] as $id=>$name)
{
  $add_css = $stored_clang==$id ? ' current':'';
  echo '  <a tabindex="35" title="'.$name.'" clang="'.$id.'" onclick="openLinkMap(\'LINK_1\', \'&amp;clang='.$id.'&amp;category_id=0\');return false;" class="rex-icon-file-open open-clang-linkmap'.$add_css.'" href="#">'.$id.'</a>
';
}

echo '
  <a tabindex="36" title="Ausgew&auml;hlten Link l&ouml;schen" onclick="deleteREXLink(1);return false;" class="rex-icon-file-delete" href="#"></a>
  </span>
  </p>
</div>
';
}


require $REX['INCLUDE_PATH'] . '/layout/bottom.php';
