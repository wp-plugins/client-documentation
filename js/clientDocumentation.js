(function($) {
  $.stripslashes = function (str) {
    str = str.replace(/\\'/g,'\'');
    str = str.replace(/\\"/g,'"');
    str = str.replace(/\\\\/g,'\\');
    str = str.replace(/\\0/g,'\0');
    return str;
  };
})(jQuery);
jQuery(document).ready(function($){

	/* =========================
	 	Documentation Page
	   ========================= */

	// Expand the item view to show the content
	$( '.cd-list' ).on( 'click' , '.cd-list-el', function(){

		$( this ).children( '.cd_expand' ).animate( {
		height: [ 'toggle' , 'swing' ],
		opacity: [ 'toggle' , 'swing' ],
		paddingTop: [ 'toggle' , 'swing' ],
		paddingBottom: [ 'toggle' , 'swing' ]
		}, 700, 'swing' );

	});

	// Remove items on Documentation page.
	$('.cd-list').on('click','i.remove_field',function(){

		var data = {
			action: 'cd_ajax',
			cd_action: 'remove',
			cd_id: $(this).data('itemid')
		};

		jQuery.post( ajax_object.ajax_url , data , function(response){ remove_response(response) });

	});

	// Handle the Ajax response
	function remove_response(response){

		var d = $.parseJSON(response);

		if(d.issue == 'success-remove') $( '#cd_list_'+d.data['ID'] ).animate({
			height: ['toggle', 'swing'],
			opacity: ['toggle', 'swing']
		},500);
		else alert(d.data);

	}

	/* -- ADD CONTENT MODAL -- */

	// Handle the tab switch on Add content Modal
	// on Documentation page
	function tabhandle( element ){
		$( '.cd_tick_' + element ).on( { focus: function(){
			if($(this).addClass() != 'active'){
				$( '.cd_tick_body fieldset' ).fadeOut( 300 );
				$( '.cd_tick_header ul li' ).removeClass( 'active' );
				$( '#cd_' + element ).delay( 300 ).fadeIn( 600 );
				$( this ).addClass( 'active' );
			}
		}});
	}
	tabhandle( 'video' );
	tabhandle( 'note' );
	tabhandle( 'link' );
	tabhandle( 'file' );

	// Submit new content through AJAX
	$('.submit_button').each(function(){

		$(this).click(function(){

			var add = {};
			if( $( '.cd_tick_video' ).hasClass( 'active' )) add.type = 'video';
			else if( $( '.cd_tick_note' ).hasClass( 'active' )) add.type = 'note';
			else if( $( '.cd_tick_link' ).hasClass( 'active' )) add.type = 'link';
			else if( $( '.cd_tick_file' ).hasClass( 'active' )) add.type = 'file';

			add.title = $( '#cd_title_' + add.type ).val();
			add.content = $( '#cd_content_' + add.type ).val();

			add.cd_type = add.type;

			var data = {
				action: 'cd_ajax',
				cd_action: 'add_content',
				cd_type: add.cd_type,
				cd_title: add.title,
				cd_content: add.content
			};


			jQuery.post( ajax_object.ajax_url , data , function(response){ add_response(response) });

		});

	});

	// Handle the ajax response
	function add_response(response){

		var d = $.parseJSON(response);

		switch(d.issue){

			case 'success':

				var content = '<li class="cd-list-el" id="cd_list_'+d.data['ID']+'"><div class="cd_title">';
				content += '<i class="icon-'+icon(d.data['type'])+'"></i><span class="cd_list_title">'+$.stripslashes(d.data['title'])+'</span>';
				content += '<span class="cd_field_action">';
				content += '<a href="#TB_inline?width=350&height=550&inlineId=cd_edit_field" class="thickbox edit_field" data-itemid="'+d.data['ID']+'" data-itemtype="'+d.data['type']+'"><i class="icon-pencil edit_field"></i></a>';
				content += '<i class="icon-remove remove_field" data-itemid="'+d.data['ID']+'"></i>';
				content += '</span></div><div class="cd_expand">'+$.stripslashes(d.data['content'])+'</div></li>';

				$(function(){
					tb_remove();
				});
				$('.cd-list').prepend(content);
				$('#cd_list_'+d.data['ID']+' .cd_expand').hide();

				// reset
				var elements = ['video','note','link','file'];
				for(var i=0;i<elements.length;i++){
					$('#cd_tick_'+elements[i]).removeClass('active');
					$('#cd_title_'+elements[i]).val('');
					$('#cd_content_'+elements[i]).val('');
          $('#cd_'+elements[i]).hide();
				}

        $('.cd_default').show();



			break;

			case 'missing-fields':
				var info = '';
				if(d.data.length == 1){
					info = d.data[0]+' '+ajax_object.is_missing;
				}
				else{
					info = ajax_object.fields_missings;
					$.each(d.data, function(key, value){
						if(key!=0) info += ' - ';
						info += value;
					});
				}
				alert(info);
			break;

		}

	}

	// Define an icon for each type of item
	function icon(type){

		switch(type){

			case 'note':
				return 'align-left';
			break;
			case 'link':
				return 'link';
			break;
			case 'video':
				return 'youtube-play';
			break;
			default:
				return 'file';
			break;

		}

	}

	// Media manager
	var file_frame;

	$('.cd_button_upload').on('click',function(event){
		var text = $(this).siblings('.cd_text_upload');

		event.preventDefault();

	    if ( file_frame ) {
	      file_frame.open();
	      return;
	    }

	    file_frame = wp.media.frames.file_frame = wp.media({
	      title: $( this ).data( 'uploader_title' ),
	      button: {
	        text: $( this ).data( 'uploader_button_text' ),
	      },
	      multiple: false
	    });

	    file_frame.on( 'select', function() {

	      attachment = file_frame.state().get('selection').first().toJSON();
		  text.attr('value', attachment.url);

	    });

	    file_frame.open();

		return false;
	});

	/* -- MANAGE SETTINGS MODAL -- */

	// Submit setting updates through Ajax
	$('#cd_setting_submit').click(function(){

		var cd = {};
		cd.clientRole = $('#clientDocumentation_clientRole').val();
		cd.widgetTitle = $('#clientDocumentation_widget_title').val();
    cd.welcomeTitle = $('#clientDocumentation_welcomeTitle').val();
		cd.welcomeMessage = $('#clientDocumentation_welcomeMessage').val();
		cd.itemNumber = $('#clientDocumentation_items_number').val();
    cd.allitems = $('#clientDocumentation_allitems').val();
    cd.pinned = $('#clientDocumentation_pinned').val();

		var data = {
			action: 'cd_ajax',
			cd_action: 'manage_settings',
			cd_clientRole: cd.clientRole,
			cd_widgetTitle: cd.widgetTitle,
      cd_welcomeTitle: cd.welcomeTitle,
			cd_welcomeMessage: cd.welcomeMessage,
			cd_itemNumber: cd.itemNumber,
      cd_allitems: cd.allitems,
      cd_pinned: cd.pinned
		};

		jQuery.post( ajax_object.ajax_url , data , function(response){ settings_response(response) });

	});

	// Handle the ajax response
	function settings_response(response){

		var d = $.parseJSON(response);

		if(d.issue == 'success'){

			$(function(){
				tb_remove();
			});
			alert(d.data);

		}else{
			alert(d.data);
		}
	}

	/* -- FIELD EDITION MODAL -- */

	$( '.cd-list' ).on( 'click', '.edit_field' , function(){

    $( '#cd_edit_textarea' ).hide(0);
    $( '#cd_edit_file' ).hide(0);
    $( '#cd_edit_text' ).hide(0);
    $( '#cd_edit_textarea' ).val('');
    $( '#cd_edit_file' ).val('');
    $( '#cd_edit_text' ).val('');
    $( '#cd_edit_title' ).val('');
    $( '#cd_edition_submit' ).attr('data-itemid',0);
    $( '#cd_edition_submit' ).attr('data-itemtype',0);


    var edit_id = $(this).data('itemid');
    var edit_type = $(this).data('itemtype');

    var edit_li = $('#cd_list_'+edit_id);

    $('#cd_edition_submit').attr('data-itemid', edit_id);
    $('#cd_edition_submit').attr('data-itemtype', edit_type);
    $( '#cd_edit_title' ).val( edit_li.children('.cd_title').children( '.cd_list_title' ).html() );

    if(edit_type == 'note' || edit_type == 'video'){
      $( '#cd_edit_textarea' ).show();
      var textarea = edit_li.children( '.cd_expand' ).html();
      $( '#cd_edit_content_textarea' ).val( textarea.replace('<br>','') );
    }else if( edit_type == 'file' ){
      $( '#cd_edit_file' ).show();
      $( '#cd_edit_content_file' ).val( edit_li.children( '.cd_expand' ).html() );
    }else{
      $( '#cd_edit_text' ).show();
      $( '#cd_edit_content_text' ).val( edit_li.children( '.cd_expand' ).html() );
    }

	});

  $('.edition_submit').on('click', function(){

    var edit_id = $('#cd_edition_submit').attr('data-itemid');

    var edit_type = $('#cd_edition_submit').attr('data-itemtype');

    var edit_title = $('#cd_edit_title').val();

    if(edit_type == 'note' || edit_type == 'video') var edit_content = $('#cd_edit_content_textarea').val();
    else if(edit_type == 'file') var edit_content = $('#cd_edit_content_file').val();
      else var edit_content = $('#cd_edit_content_text').val();

    var data = {
      action: 'cd_ajax',
      cd_action: 'edit_field',
      cd_itemid: edit_id,
      cd_title: edit_title,
      cd_content: edit_content,
      cd_type: edit_type
    };

    jQuery.post( ajax_object.ajax_url , data , function(response){ edit_response(response) });

  });


	// Handle field change
	function edit_response(response){

		var d = $.parseJSON(response);

		if(d.issue == 'error'){
			alert(d.data);
		}else{

			$( '#cd_list_'+d.data['ID'] ).children( '.cd_title' ).children( '.cd_list_title' ).html( $.stripslashes(d.data['title']) );
			$( '#cd_list_'+d.data['ID'] ).children( '.cd_expand' ).html( $.stripslashes(d.data['content']));

			$(function(){
				tb_remove();
			});

		}
	}



	/* =========================
	 	Dashboard Widget
	   ========================= */

	// Expand the item to show the content when its not a link or file
	$( '.cd_widget_title' ).on( 'click', function(){
		$( this ).siblings( '.cd_widget_content' ).animate( {
		height: [ 'toggle' , 'swing' ],
		opacity: [ 'toggle' , 'swing' ],
		paddingTop: [ 'toggle' , 'swing' ],
		paddingBottom: [ 'toggle' , 'swing' ]
		}, 700, 'swing' );
	});

	// Manage Pin on item (star)
	$('.cdpin').each(function(){
		$(this).on('click',function(){

			var cd = {};
			cd.itemid = $(this).data('itemid');

			var data = {
				action: 'cd_ajax',
				cd_action: 'manage_stars',
				cd_itemid: cd.itemid
			};
			$(this).removeClass('icon-star');
			$(this).removeClass('icon-star-empty');
			$(this).removeClass('cd_star');
			$(this).addClass('loading');

			var element = $(this);

			jQuery.post( ajax_object.ajax_url , data , function(response){ stars_response(response, element) });

		});
	});

	// Change the star status depends on Ajax request
	function stars_response(response, element){

		var d = $.parseJSON(response);

		if( d.issue == 'success' ){

			console.log(element);

			element.removeClass('loading');

			if(d.data['etoile_b']){
				element.addClass('icon-star');
			}else{
				element.addClass('icon-star-empty');
			}

		}else if(d.issue == 'error'){
			alert(d.data);
		}

	}

  /*
    Import / Export
  */

  $('.cd_import_button').click(function(){
    var data = {
      action: 'cd_ajax',
      cd_action: 'import',
      cd_content: $('#cd_import_content').val()
    };

    jQuery.post( ajax_object.ajax_url , data , function(response){
      alert(response);
      $(function(){
				tb_remove();
			});
    });
  });
  $('.cd_export_button').click(function(){
    var data = {
      action: 'cd_ajax',
      cd_action: 'export',
      cd_spec: $('#cd_export_options').val()
    };

    jQuery.post( ajax_object.ajax_url , data , function(response){
      $('#cd_export_action').html(response);
    });
  });

});