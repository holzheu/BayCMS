<?php

namespace BayCMS\Util;

class TinyMCE extends \BayCMS\Base\BayCMSBase {
    public function __construct(\BayCMS\Base\BayCMSContext $context) {
        $this->context=$context;
    }
    
    public function getInitFull($ids,$ask='false',$id_parent=0){
		$this->context->initTemplate();
        return '
	tinyMCE.init({
	  selector : "'.$ids.'",
      ask : '.$ask.',
	  forced_root_block : "p",
	  image_advtab: true,
	  extended_valid_elements: "script[language|type|src]",
	  convert_urls: false,
	  visualblocks_default_state: '.($this->context->TE->isBootstrap()?'false':'true').',
	  body_'.($this->context->TE->isBootstrap()?"class: 'container'":"id: 'tinymce_body'").',
	  paste_word_valid_elements: "p,b,strong,i,em,h1,h2,h3,h4,sup,sub,u,pre,table,tr,td,th,ul,ol,li,img",
	  paste_data_images: true,
	  content_css: "'.$this->context->TE->tiny_css.'",
	  style_formats_merge: true,
      style_formats: '.$this->context->TE->tiny_style_formats.', 
	  templates: '.$this->context->TE->tiny_template.',
	  '.($this->context->TE->tiny_image_class? $this->context->TE->tiny_image_class.',':'').'
	  file_picker_types: "'.($this->context->getPower()>=1000?'file ':'').'image",
	  file_picker_callback: function(callback, value, meta) {
	  if (meta.filetype == "image") {
			  myImagePicker(callback, value, meta);
	  }
	  if (meta.filetype == "file") {
			  myFilePicker(callback, value, meta);
			}    
	  },
	  fontsize_formats: "xx-small x-small small medium large x-large xx-large",
	  images_upload_url: "/'.$this->context->getOrgLinkLang().'/intern/gru/image.php?tinyupload=1&id_parent='.$id_parent.'",
	  images_upload_credentials: true,
	  plugins: [
	            "advlist autolink lists link image colorpicker charmap print preview anchor contextmenu hr",
	            "searchreplace visualblocks code fullscreen template",
	            "insertdatetime media table contextmenu paste code textcolor"
	          ],
	  contextmenu: "removeformat  | link image inserttable | cell row column deletetable",
	  toolbar: "removeformat bold italic forecolor backcolor fontsizeselect | bullist numlist | link image | fullscreen code template",
	  language : "'.$this->context->lang.'"
	 });

	function myImagePicker(callback, value, meta){
	    tinymce.activeEditor.windowManager.open({
	        title: "Image Browser",
	        url: "/'.$this->context->getOrgLinkLang().'/intern/gru/image.php?js_select=tiny&id_parent='.$id_parent.'",
	        width: 800,
	        height: 550,
	    }, {
	        oninsert: function (url, objVals) {
	            callback(url, objVals);
	        }
	    });
	}

	function myFilePicker(callback, value, meta){
	    tinymce.activeEditor.windowManager.open({
	        title: "File Browser",
	        url: "/'.$this->context->getOrgLinkLang().'/admin/gru/index_file.php/html?js_select=tiny&id_parent='.$id_parent.'",
	        width: 800,
	        height: 550,
	    }, {
	        oninsert: function (url, objVals) {
	            callback(url, objVals);
	        }
	    });
	}

';
    }



}