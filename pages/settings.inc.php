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
 * @version 1.5.beta_28.7
 */

// MAIN PARAMS
////////////////////////////////////////////////////////////////////////////////
$myself  = rex_request('page',            'string');
$subpage = rex_request('subpage',         'string');
$func    = rex_request('func',            'string');
$backup  = $REX['INCLUDE_PATH'].'/backup/addons/rexseo/config.inc.php';
$table   = $REX['TABLE_PREFIX'].'rexseo_redirects';

// SETTINGS PARAMS
////////////////////////////////////////////////////////////////////////////////
$CAST = array (
      'page'                       => 'unset',
      'subpage'                    => 'unset',
      'func'                       => 'unset',
      'submit'                     => 'unset',
      'sendit'                     => 'unset',
      'def_desc'                   => 'array',
      'def_keys'                   => 'array',
      'homeurl'                    => 'int',
      'homelang'                   => 'int',
      'allow_articleid'            => 'int',
      'levenshtein'                => 'int',
      'expert_settings'            => 'int',
      'alert_setup'                => 'int',
      'first_run'                  => 'int',
      'rewrite_params'             => 'int',
      'hide_langslug'              => 'int',
      'compress_pathlist'          => 'int',
      'urlencode'                  => 'int',
      );


// RUN REDIRECTS CACHER
////////////////////////////////////////////////////////////////////////////////
if(OOPlugin::isAvailable('rexseo','redirects_manager')){
  redirects_manager::updateHtaccess();
}


// CHECK METAINFO
//////////////////////////////////////////////////////////////////////////////
rexseo_setup_metainfo();


// UPDATE/SAVE SETTINGS
////////////////////////////////////////////////////////////////////////////////
if ($func == 'update')
{
  // NO BACKUP FILE -> INSTALL
  if(!file_exists($backup))
  {
    $source = $REX['INCLUDE_PATH'].'/addons/'.$myself.'/install/backup/';
    $target = $REX['INCLUDE_PATH'].'/';
    rexseo_recursive_copy($source, $target);
  }

  // GET ADDON SETTINGS FROM REQUEST
  $myCONF = rexseo_batch_cast($_POST,$CAST);

  // UPDATE REX
  $REX['ADDON'][$myself]['settings'] = $myCONF;

  // SAVE ADDON SETTINGS
  $DYN    = '$REX["ADDON"]["'.$myself.'"]["settings"] = '.stripslashes(var_export($myCONF,true)).';';
  $config = $REX['INCLUDE_PATH'].'/addons/'.$myself.'/config.inc.php';
  rex_replace_dynamic_contents($config, $DYN);
  rex_replace_dynamic_contents($backup, $DYN);
  echo rex_info('Einstellungen wurden gespeichert.');
  rexseo_generate_pathlist('');
  echo rex_info('Pathlist wurden aktuallisiert.');
}


// FIRST RUN NOTIFY
////////////////////////////////////////////////////////////////////////////////
if($REX['ADDON'][$myself]['settings']['alert_setup'] == 1)
{
  echo rex_warning('WICHTIG: RexSEO erfordert f&uuml;r den Betrieb zwingend Anpassungen, die im Kapitel <a href="index.php?page=rexseo&subpage=help&chapter=&func=setup_alert_disable&highlight=Quickstart">Quickstart</a> der Hilfe beschrieben sind. <em>(Diese Meldung verschwindet - unabh&auml;ngig davon ob RexSEO schonmal installiert war - erst wenn die Quickstart Seite einmal aufgesucht wurde.)</em>');

  $subdir = rexseo_subdir();
  if($subdir != '')
  {
    echo rex_warning('HINWEIS: Redaxo scheint in einem Unterordner installiert zu sein (./'.$subdir.') - dieser muß in der .htaccess entsprechend <a href="index.php?page=rexseo&subpage=help&chapter=&func=alert_setup&highlight='.urlencode('Installation in Unterverzeichnissen:').'">eingetragen</a> werden!');
  }
}


// RESTORE SETTINGS FROM BACKUP FILE
////////////////////////////////////////////////////////////////////////////////
if($REX['ADDON'][$myself]['settings']['first_run'] == 1 && file_exists($backup))
{
  require_once $backup;
  echo rex_info('Daten wurden aus Backup ins Formular &uuml;bernommen - bitte Einstellungen speichern!');

  // IMPORT REDIRECTS FROM BACKUP CONFIG TO DB
  $db = new rex_sql;
  $db->setQuery('SELECT * FROM `'.$table.'`;');

  if(isset($REX['ADDON']['rexseo']['settings']['301s']) &&
     count($REX['ADDON']['rexseo']['settings']['301s'])>0 &&
     $db->getRows()==0)
  {
    $qry = 'INSERT INTO `'.$table.'` (`id`, `createdate`, `updatedate`, `expiredate`, `creator`, `status`, `from_url`, `to_article_id`, `to_clang`, `http_status`) VALUES';
    $date = time();
    if(!isset($REX['ADDON'][$myself]['settings']['default_redirect_expire']))
      $REX['ADDON'][$myself]['settings']['default_redirect_expire'] = 60;
    $expire = $date + ($REX['ADDON']['rexseo']['settings']['default_redirect_expire']*24*60*60);
    foreach($REX['ADDON']['rexseo']['settings']['301s'] as $k=>$v)
    {
      $qry .= PHP_EOL.'(\'\', \''.$date.'\', \''.$date.'\', \''.$expire.'\', \''.$REX['USER']->getValue('login').'\', 1, \''.$k.'\', '.$v['article_id'].', '.$v['clang'].', 301),';
    }
    $qry = rtrim($qry,',').';';
    if($db->setQuery($qry))
    {
      echo rex_info('Weiterleitungen wurden aus Backup in die DB importiert.');
    }
  }
}


// SUBDIR CHANGE NOTIFY
////////////////////////////////////////////////////////////////////////////////
if($REX['ADDON'][$myself]['settings']['install_subdir'] != rexseo_subdir())
{
  echo rex_warning('ACHTUNG: Das aktuelle Installationsverzeichnis von Redaxo scheint sich ge&auml;ndert zu haben.<br />
                   Zum aktualisieren einmal die RexSEO settings speichern.<br />
                   Evtl. notwendige <a href="index.php?page=rexseo&subpage=help&chapter=&func=alert_setup&highlight='.urlencode('Installation in Unterverzeichnissen:').'">Anpassung der RewriteBase</a> in der .htaccess beachten!');
}


// TOGGLE REDIRECT
////////////////////////////////////////////////////////////////////////////////
if(rex_request('func','string')=='toggle_redirect' && intval(rex_request('id','int'))>0)
{
  $db = new rex_sql;
  $db->setQuery('UPDATE `'.$table.'` SET `status` = IF(status=1, 0, 1) WHERE `id`='.rex_request('id','int').';');
  rexseo_htaccess_update_redirects();
}


// DELETE REDIRECT
////////////////////////////////////////////////////////////////////////////////
if(rex_request('func','string')=='delete_redirect' && intval(rex_request('id','int'))>0)
{
  $db = new rex_sql;
  $db->setQuery('DELETE FROM `'.$table.'` WHERE `id`='.rex_request('id','int').';');
  rexseo_htaccess_update_redirects();
}


// URL_SCHEMA SELECT BOX
////////////////////////////////////////////////////////////////////////////////
$url_schema_select = new rexseo_select();
$url_schema_select->setSize(1);
$url_schema_select->setName('url_schema');
$url_schema_select->addOption('RexSEO','rexseo');
$url_schema_select->addOption('url_rewrite','url_rewrite');
$url_schema_select->setAttribute('style','width:250px');
$url_schema_select->setSelected($REX['ADDON'][$myself]['settings']['url_schema']);

// URL_ENDING SELECT BOX
////////////////////////////////////////////////////////////////////////////////
$url_ending_select = new rexseo_select();
$url_ending_select->setSize(1);
$url_ending_select->setName('url_ending');
$url_ending_select->addOption('.html','.html');
$url_ending_select->addOption('/','/');
$url_ending_select->addOption('(ohne)','');
$url_ending_select->setAttribute('style','width:70px;margin-left:20px;');
$url_ending_select->setSelected($REX['ADDON'][$myself]['settings']['url_ending']);


// HOMEURL SELECT BOX
////////////////////////////////////////////////////////////////////////////////
$ooa = OOArticle::getArticleById($REX['START_ARTICLE_ID']);
if($ooa)
{
  $homename = strtolower($ooa->getName());
}
else
{
  $homename = 'Startartikel';
}
unset($ooa);

$homeurl_select = new rexseo_select();
$homeurl_select->setSize(1);
$homeurl_select->setName('homeurl');
$homeurl_select->addOption($REX['SERVER'].$homename.'.html',0);
$homeurl_select->addOption($REX['SERVER'],1);
$homeurl_select->addOption($REX['SERVER'].'lang-slug/',2);
$homeurl_select->setAttribute('style','width:250px;');
$homeurl_select->setSelected($REX['ADDON'][$myself]['settings']['homeurl']);


// LANGSLUG SELECT BOX
////////////////////////////////////////////////////////////////////////////////
if(count($REX['CLANG']) > 1)
{
  $hide_langslug_select = new rexseo_select();
  $hide_langslug_select->setSize(1);
  $hide_langslug_select->setName('hide_langslug');
  $hide_langslug_select->addOption('Bei allen Sprachen einfügen',-1);
  foreach($REX['CLANG'] as $id => $str)
  {
    $hide_langslug_select->addOption('Kein lang slug für Sprache: '.$str,$id);
  }
  $hide_langslug_select->setSelected($REX['ADDON'][$myself]['settings']['hide_langslug']);
  $hide_langslug_select = '
          <div class="rex-form-row">
            <p class="rex-form-col-a rex-form-select">
              <label for="hide_langslug">Lang slug: <a class="help-icon" title="Hilfe zum Thema anzeigen" href="index.php?page=rexseo&subpage=help&chapter=settings&highlight='.urlencode('Lang slug:').'">?</a></label>
                '.$hide_langslug_select->get().'
                </p>
          </div><!-- /rex-form-row -->';
}
else
{
  $hide_langslug_select = '';
}


// HOMELANG SELECT BOX
////////////////////////////////////////////////////////////////////////////////
if(count($REX['CLANG']) > 1)
{
  $homelang_select = new rexseo_select();
  $homelang_select->setSize(1);
  $homelang_select->setName('homelang');
  foreach($REX['CLANG'] as $id => $str)
  {
    $homelang_select->addOption($str,$id);
  }
  $homelang_select->setSelected($REX['ADDON'][$myself]['settings']['homelang']);
  $homelang_select->setAttribute('style','width:70px;margin-left:20px;');
  $homelang_box = '
              <span style="margin:0 4px 0 4px;display:inline-block;width:100px;text-align:right;">
                Sprache: <a class="help-icon" title="Hilfe zum Thema anzeigen" href="index.php?page=rexseo&subpage=help&chapter=settings&highlight='.urlencode('Sprache:').'">?</a>
              </span>
              '.$homelang_select->get().'
              ';
}
else
{
  $homelang_box = '';
}

// ARTICLE_ID SELECT BOX
////////////////////////////////////////////////////////////////////////////////
$allow_articleid_select = new rexseo_select();
$allow_articleid_select->setSize(1);
$allow_articleid_select->setName('allow_articleid');
$allow_articleid_select->addOption('Nicht zulässig, nur rewrite URLs'           ,0);
$allow_articleid_select->addOption('Zulässig, 301 Weiterleitung auf korrekte URL (ohne Parameter)',1);
$allow_articleid_select->addOption('Zulässig ohne Weiterleitung'                ,2);
$allow_articleid_select->setSelected($REX['ADDON'][$myself]['settings']['allow_articleid']);


// LEVENSHTEIN SELECT BOX
////////////////////////////////////////////////////////////////////////////////
$levenshtein_select = new rexseo_select();
$levenshtein_select->setSize(1);
$levenshtein_select->setName('levenshtein');
$levenshtein_select->addOption('Strikte URL-Übereinstimmung, sonst Fehlerseite (404)',0);
$levenshtein_select->addOption('Artikel mit ähnlichster URL anzeigen',1);
$levenshtein_select->setSelected($REX['ADDON'][$myself]['settings']['levenshtein']);


// PARAMS REWRITE SELECT BOX
////////////////////////////////////////////////////////////////////////////////
$params_rewrite_select = new rexseo_select();
$params_rewrite_select->setSize(1);
$params_rewrite_select->setName('rewrite_params');
$params_rewrite_select->setAttribute('style','width:250px;');
$params_rewrite_select->setAttribute('id','rewrite_params');
$params_rewrite_select->addOption('Aus : ?param1=wert1&param2=wert2',0);
$params_rewrite_select->addOption('Ein : '.$REX['ADDON'][$myself]['settings']['params_starter'].'/param1/wert1/param2/wert2',1);
$params_rewrite_select->setSelected($REX['ADDON'][$myself]['settings']['rewrite_params']);


// URL ENCODE SELECT BOX
////////////////////////////////////////////////////////////////////////////////
$urlencode_select = new rexseo_select();
$urlencode_select->setSize(1);
$urlencode_select->setName('urlencode');
$urlencode_select->setAttribute('id','rewrite_params');
$urlencode_select->addOption('Zeichenersetzung per lang Datei',0);
$urlencode_select->addOption('Kodierung per urlencode',1);
$urlencode_select->setSelected($REX['ADDON'][$myself]['settings']['urlencode']);


// EXPERT SETTINGS CHECKBOX OPTIONS
////////////////////////////////////////////////////////////////////////////////
if($REX['ADDON'][$myself]['settings']['expert_settings'] == 1)
{
  $expert_display = '';
  $expert_checked = 'checked="checked"';
}
else
{
  $expert_display = 'display:none;';
  $expert_checked = '';
}


// FORM
////////////////////////////////////////////////////////////////////////////////
echo '

<div class="rex-addon-output">
  <div class="rex-form">

  <form action="index.php" method="post">
    <input type="hidden" name="page"                   value="rexseo" />
    <input type="hidden" name="subpage"                value="settings" />
    <input type="hidden" name="func"                   value="update" />
    <input type="hidden" name="rexseo_version"         value="'.$REX['ADDON']['version'][$myself].'" />
    <input type="hidden" name="first_run"              value="0" />
    <input type="hidden" name="alert_setup"            value="'.$REX['ADDON'][$myself]['settings']['alert_setup'].'" />
    <input type="hidden" name="install_subdir"         value="'.rexseo_subdir().'" />
    <input type="hidden" name="url_whitespace_replace" value="-" />
    <input type="hidden" name="compress_pathlist"      value="1" />
';


foreach ($REX['CLANG'] as $id => $str)
{
  $def_desc = isset($REX['ADDON'][$myself]['settings']['def_desc'][$id]) ? stripslashes($REX['ADDON'][$myself]['settings']['def_desc'][$id]) : '';
  $def_keys = isset($REX['ADDON'][$myself]['settings']['def_keys'][$id]) ? stripslashes($REX['ADDON'][$myself]['settings']['def_keys'][$id]) : '';

  echo '
    <fieldset class="rex-form-col-1">
      <legend>Meta Defaults ('.$str.')</legend>
      <div class="rex-form-wrapper">

        <div class="rex-form-row">
          <p class="rex-form-col-a rex-form-select">
          <label for="def_desc" class="helptopic">Description:<br /><br /><em style="color:gray;font-size:10px;">z.B. My super description</em></label>
            <textarea id="def_desc_'.$id.'" name="def_desc['.$id.']">'.$def_desc.'</textarea>

          </p>
        </div><!-- /rex-form-row -->

        <div class="rex-form-row">
          <p class="rex-form-col-a rex-form-select">
            <label for="def_keys" class="helptopic">Keywords:<br /><br /><em style="color:gray;font-size:10px;">z.B. My, list, of, keywords</em></label>
            <textarea id="def_keys_'.$id.'" name="def_keys['.$id.']">'.$def_keys.'</textarea>
          </p>
        </div><!-- /rex-form-row -->

      </div><!-- /rex-form-wrapper -->
    </fieldset>';
}

echo '
    <div id="expert_block" style="'.$expert_display.'margin:0;padding:0;">

      <fieldset class="rex-form-col-1">
        <legend>Page Title</legend>
        <div class="rex-form-wrapper">

          <div class="rex-form-row">
            <p class="rex-form-col-a rex-form-text">
              <label for="title_schema" class="helptopic">Title Elemente:</label>
              <input id="title_schema" class="rex-form-text" type="text" name="title_schema" value="'.stripslashes($REX['ADDON'][$myself]['settings']['title_schema']).'" /><br />
              <em style="color:gray;font-size:10px;">%B = breadcrumb | %N = article name | %C = category name | %S = server/host</em>
            </p>
          </div><!-- /rex-form-row -->

        </div><!-- /rex-form-wrapper -->
      </fieldset>

      <fieldset class="rex-form-col-1">
        <legend>URL Rewrite Optionen</legend>
        <div class="rex-form-wrapper">

          <div class="rex-form-row">
            <p class="rex-form-col-a rex-form-select">
              <label for="url_schema" class="helptopic">Schema:</label>
                '.$url_schema_select->get().'

              <span style="margin:0 4px 0 4px;display:inline-block;width:100px;text-align:right;" class="helptopic">Endung:</span>
                '.$url_ending_select->get().'
            </p>
          </div><!-- /rex-form-row -->

          '.$hide_langslug_select.'

          <div class="rex-form-row">
            <p class="rex-form-col-a rex-form-select">
              <label for="url_schema" class="helptopic">Parameter Rewrite:</label>
                '.$params_rewrite_select->get().'

              <span id="params_starter_span" style="margin:0 4px 0 4px;display:inline-block;width:100px;text-align:right;" class="helptopic">Abtrenner:</span>
              <input style="width:80px;" id="params_starter" class="rex-form-text" type="text" name="params_starter" value="'.stripslashes($REX['ADDON'][$myself]['settings']['params_starter']).'" />
            </p>
          </div><!-- /rex-form-row -->

          <div class="rex-form-row">
            <p class="rex-form-col-a rex-form-select">
              <label for="homeurl" class="helptopic">Startseite:</label>
                '.$homeurl_select->get().'
                '.$homelang_box.'
            </p>
          </div><!-- /rex-form-row -->

          <div class="rex-form-row">
            <p class="rex-form-col-a rex-form-select">
              <label for="urlencode" class="helptopic">URL-Encoding:</label>
                '.$urlencode_select->get().'
            </p>
          </div><!-- /rex-form-row -->

        </div><!-- /rex-form-wrapper -->
      </fieldset>

      <fieldset class="rex-form-col-1">
        <legend>URL Resolve Optionen</legend>
        <div class="rex-form-wrapper">

          <div class="rex-form-row">
            <p class="rex-form-col-a rex-form-select">
              <label for="allow_articleid" class="helptopic">Aufruf via article_id:</label>
                '.$allow_articleid_select->get().'
                </p>
          </div><!-- /rex-form-row -->

          <div class="rex-form-row">
            <p class="rex-form-col-a rex-form-select">
              <label for="levenshtein" class="helptopic">Genauigkeit:</label>
                '.$levenshtein_select->get().'
            </p>
          </div><!-- /rex-form-row -->

        </div><!-- /rex-form-wrapper -->
      </fieldset>

      <fieldset class="rex-form-col-1">
        <legend>robots.txt</legend>
        <div class="rex-form-wrapper">

          <div class="rex-form-row">
            <p class="rex-form-col-a rex-form-select">
              <label for="robots" class="helptopic">robots.txt:</label>
              <textarea id="rexseo_robots" name="robots">'.stripslashes($REX['ADDON'][$myself]['settings']['robots']).'</textarea>
            </p>
          </div><!-- /rex-form-row -->

        </div><!-- /rex-form-wrapper -->
      </fieldset>

    </div><!-- /expert -->

      <fieldset class="rex-form-col-1">
        <legend>&nbsp;</legend>
        <div class="rex-form-wrapper">

          <div class="rex-form-row rex-form-element-v2">
            <p  class="rex-form-checkbox"style="display:inline !important;">
              <label for="expert_settings" style="width:145px !important;">Erweiterte Einstellungen</label>
              <input type="checkbox" '.$expert_checked.' value="1" id="expert_settings" name="expert_settings">
            </p>

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

  $("#expert_settings").click(function() {
    $("#expert_block").slideToggle("slow");
  });

  if($("#expert_settings").is(":checked")) {
    $("#expert_block").show();
  }

  // toggle params_starter input
  $("#rewrite_params").change(function() {
    if($("#rewrite_params").val()==1)
    {
      $("#params_starter_span").show();
      $("#params_starter_span").css("display:inline-block");
      $("#params_starter").show();
    }
    else
    {
      $("#params_starter_span").hide();
      $("#params_starter").hide();
    }
  });

    $(function() {';
foreach ($REX['CLANG'] as $id => $str)
{
  echo '
      $("#def_desc_'.$id.'").autogrow();
      $("#def_keys_'.$id.'").autogrow();';
}

echo '
      $("#rexseo_redirects").autogrow();
      $("#rexseo_robots").autogrow();
  });

  $(document).ready(function(){
    $("a.new").attr("target","_redirects");
    });

});
//-->
</script>

';

unset($levenshtein_select,$allow_articleid_select,$homeurl_select,$url_ending_select,$url_schema_select);
?>
