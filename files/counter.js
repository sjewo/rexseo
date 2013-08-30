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

jQuery(function($){

  $('textarea[name="art_description"],textarea[name="art_keywords"]').each(function(){
    $(this).addClass("input-count");
    $(this).prev("label").addClass($(this).attr("name"));
    $(this).prev("label").html($(this).prev("label").html() + '<br><em class="label-subline"><span class="wordcount">words: <span id="' + $(this).attr("id")+"_wordcount" + '">0</span><br /></span><span class="keywordcount">keywords: <span id="' + $(this).attr("id")+"_keywordcount" + '">0</span><br /></span><span class="charcount">chars: <span id="' + $(this).attr("id")+"_charcount" + '">0</span></span></em>' );
  });

  $("textarea.input-count").each(function() {
      var input = "#" + this.id;
      update_counter(input);
      $(this).keyup(function() {
        update_counter(input);
      });
  });

  function string_stats(str, type) {
    switch (type) {
      case "words":
        return str.match(/\b/g) ? str.match(/\b/g).length/2 : 0;
      case "keywords":
        return str.length > 0 ? str.replace(/^\s+|(\s|,)+$/g,"").split(",").length : 0;
      case "chars":
        return str.length;
    }
  }

  function update_counter(input) {
      $(input+"_wordcount").text( string_stats($(input).val(), "words") );
      $(input+"_keywordcount").text( string_stats($(input).val(), "keywords") );
      $(input+"_charcount").text( string_stats($(input).val(), "chars") );
  }

});
