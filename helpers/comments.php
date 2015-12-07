<?php

/*  
	Creates an easy to use helper for displaying WordPress comments and comments form.
	Extends form functionalities with additional arguments:
		- 'replace_input': array( 'field_name' => html )
				replace comment form input by assigning array key to match the input name field
		- 'remove_input': array( 'field_name' )
				remove comment form input by assigning array key to match the input name field
		- 'status_div': html
				container div to hold status messages
		- 'status_id': string
				status divs id to locate with js
		- 'loader': html
				html to display while processing comments ajax

*/
function comments($helpers) {	

	$helpers['comments'] = function (\Dust\Evaluate\Chunk $chunk, \Dust\Evaluate\Context $ctx, \Dust\Evaluate\Bodies $bodies, \Dust\Evaluate\Parameters $params) {
		global $dustpress;

		$handler = new Comments_Helper( $params, $dustpress );

		return $chunk->write( $handler->get_output() );
	};

	return $helpers;
}
add_action('dustpress/helpers', 'comments', 10, 1);


class Comments_Helper {

	private $params;
	private $data;
	private $input_class;
	private $input_attrs;
	private $replacements;
	private $remove;
	private $status_div;
	private $echo_form;	
	private $load_comments;
	private $reply;
	private $comment_class;
	private $form_args;
	private $comments_args;
	private $reply_args;
	private $avatar_args;
	private $form_id;
	private $threaded;
	private $comments;
	private $form;
	private $output;
	private $dp;

	public function get_output() {
		return $this->output;
	}

	public function __construct( $params ) {		
		global $post;		

		// store params
		$this->params = $params;

		// check if not doing ajax
		if ( $params !== 'handle_ajax' ) {			
			$this->init();
		}
			
	}

	// fired after comment is succesfully saved in wp-comments-post.php
	public function handle_ajax( $comment_id ) {	
		global $dustpress;

		if ( ! defined('DUSTPRESS_AJAX') ) {
			define("DUSTPRESS_AJAX", true);	
		}	

		$comment 		= get_comment( $comment_id );
		$comments_model = new Comments();
		// TODO: korjaa yleistettäväksi
		$comments_model->bind_Content();
		$this->params 	= $comments_model->get_helper_params();
		
		if ( $comment->comment_approved ) {
			$this->params->message = ['success' => __( 'Comment sent.', 'DustPress-Comments' )];
		}
		else {
			$this->params->message = ['warning' => __( 'Comment is waiting for approval.', 'DustPress-Comments' )];	
		}

		$this->init();

		$return = [
			'success'  	=> true,
			'html'		=> $this->output
		];
		
		die( json_encode( $return ) );
	}

	private function init() {
		global $dustpress;
		global $post;

		$c_data 		 		= new stdClass();
		$params 	 			= $this->params;
		$this->section_title	= $params->section_title;
		$this->comment_class	= $params->comment_class;
		$this->form_args		= $params->form_args;
		$this->comments_args	= $params->comments_args;		
		$this->avatar_args		= $params->avatar_args;		
		$this->post_id			= $params->post_id ? $params->post_id : $post->ID;

		// get_comment_reply_link functions arguments
		$this->reply_args		= $params->reply_args;

		// comments' arguments		
		$this->load_comments  	= $this->comments_args['load_comments'] ? $this->comments_args['load_comments'] : true;
		$this->after_comments 	= $this->comments_args['after_comments'] ? $this->comments_args['after_comments'] : null;
		$this->reply 			= $this->comments_args['reply'] !== null ? $this->comments_args['reply'] : true;
		$this->threaded 		= $this->comments_args['threaded'] ? $this->comments_args['threaded'] : get_option('thread_comments');

		// form loading and modification arguments
		$this->replacements 	= $this->form_args['replace_input'];
		$this->remove 			= $this->form_args['remove_input'];
		$this->status_div 		= $this->form_args['status_div'];
		$this->status_id 		= $this->form_args['status_id'];
		$this->input_class		= $this->form_args['input_class'];
		$this->input_attrs		= $this->form_args['input_attrs'];
		$this->echo_form  		= $this->form_args['echo_form'] ? $this->form_args['echo_form'] : true;
		$this->form_id 			= $this->form_args['form_id'] ? $form_args['form_id'] : 'commentform';		

		// default args
		$reply_defaults = [
			'depth' 	=> 1,
			'max_depth' => get_option('thread_comments_depth')
		];
		$this->reply_args = array_merge( $reply_defaults, (array) $this->reply_args );
		$comments_defaults = [
			'status' => current_user_can('moderate_comments') ? 'all' : 'approve'
		];
		$this->comments_args = array_merge( $comments_defaults, (array) $this->comments_args );

		if ( $this->echo_form ) {
			$this->get_form();
		}

		// get comments 
		if ( $this->load_comments ) {
			$this->get_comments();		
		}

		// add additional data for comments
		if ( is_array( $this->comments ) ) {
			$this->extend_comments();
		}		

		// map data
		$c_data->title 			= $this->section_title;		
		$c_data->form 			= $this->form;
		$c_data->comments 		= $this->comments;
		$c_data->form_id 		= $this->form_id;
		$c_data->message  		= $params->message;
		$c_data->after_comments = apply_filters( 'dustpress/comments/after-comments', $this->after_comments );

		// add data into debugger
		$dustpress->set_debugger_data( 'Comments', $c_data );

		// filters
		$c_data->comments 	= apply_filters( 'dustpress/comments/comments', $c_data->comments );
		$c_data->form 		= apply_filters( 'dustpress/comments/form', $c_data->form );		
		$partial 			= apply_filters( 'dustpress/comments/partial', 'comments' );							  

		$this->output = $dustpress->render( [
			"partial" 	=> $partial,
			"data" 		=> $c_data,
			"type" 		=> "html",
			"echo" 		=> false
		]);	
	}

	private function get_form() {
		// add input classes
		if ( $this->input_class || isset( $this->form_args['replace_input'] ) || isset( $this->form_args['remove_input'] ) ) {
			add_filter('comment_form_default_fields', array( $this, 'modify_fields' ) );
			add_filter('comment_form_field_comment', array( $this, 'modify_comment_field' ) );				
		}

		// insert status div
		add_filter( 'comment_form_top', array( $this, 'form_status_div' ) );

		// insert hidden field to identify dustpress helper
		add_filter( 'comment_id_fields', array( $this, 'insert_identifier' ), 1 );
		
		// compile form and store it
		ob_start();
		comment_form( $this->form_args, $this->post_id );
		$this->form = ob_get_clean();
	}

	private function get_comments() {
		if ( ! isset( $this->comments_args['post_id'] ) ) {
			$this->comments_args['post_id'] = $this->post_id;
		}
		$this->comments = get_comments( $this->comments_args );	
	}

	private function extend_comments() {
		foreach ( $this->comments as &$comment ) {
			$cid = $comment->comment_ID;

			// get author link
			$comment->author_link = get_comment_author_link( $cid );

			// set comment classes
			$classes = $this->has_parent( $comment ) ? 'reply ' . $this->comment_class : $this->comment_class;
			switch ( $comment->comment_approved ) {
				case 1:
					$classes .= 'comment-approved';
					break;
				case 0:
					$classes .= 'comment-hold';
					break;
				case 'spam':
					$classes .= 'comment-spam';
					break;
			}
			$comment->comment_class = comment_class( $classes, $cid, $post_id, false );
			
			// load reply link
			if ( $this->reply ) {									
				$comment->reply_link 	= get_comment_reply_link( $this->reply_args, $cid );									
				$comment->reply			= true;
			}

			// set avatar
			if ( is_array( $this->avatar_args ) ) {
				extract( $this->avatar_args );
				$comment->avatar = get_avatar( $id_or_email, $size, $default, $alt );
			}

			// load a custom profile picture
			$pic = apply_filters( 'dustpress/comments/profile_picture', $comment );				
			if ( is_string( $pic ) ) {
				$comment->profile_pic = $pic;
			}
			
			// filter comment
			$comment = apply_filters( 'dustpress/comments/comment', $comment );
		}
		// sort replies
		if ( $this->threaded ) {
			$this->comments = $this->threadify_comments( $this->comments );				
		}
	}

	public function modify_fields( $fields ) {
		$input_class	= $this->input_class;
		$input_attrs	= $this->input_attrs;
		$replacements	= $this->replacements;
		$remove 		= $this->remove;

		foreach ( $fields as $key => &$field ) {
			
			if ( isset( $replacements[$key] ) ) {
				$field = $replacements[$key];
			} 
			elseif ( array_search( $key, $remove ) !== false ) {
				unset( $fields[$key] );
			} 
			elseif ( $input_class ) {
				$field = preg_replace( '/<input/', '<input class="' . $input_class . '"' . $input_attrs, $field );							
			}
			elseif ( $input_attrs ) {
				$field = preg_replace( '/<input/', '<input ' . $input_attrs, $field );							
			}

		}		

		return $fields;
	}

	public function modify_comment_field( $textarea ) {
		$input_class	= $this->input_class;
		$input_attrs	= $this->input_attrs;
		$replacements	= $this->replacements;
		$remove 		= $this->remove;

		if ( isset( $replacements['comment'] ) ) {
			return $replacements['comment'];
		} 
		elseif ( array_search( 'comment', $remove ) !== false ) {
			return '';
		} 
		elseif ( $input_class ) {
			return preg_replace( '/<textarea/', '<textarea class="' . $input_class . '"', $textarea );				
		}
		elseif ( $input_attrs ) {
			return preg_replace( '/<textarea/', '<textarea ' . $input_attrs, $textarea );				
		}

		return $textarea;
	}

	public function form_status_div() {
		if ( $this->status_div ) {			
			echo $this->status_div;
		} 
		else {
			if ( $this->loader ) {
				echo $this->loader;
			} 
			else {
				echo '<div class="comments_loader"><span>' . __('Processing comments...', 'DustPress-Comments') . '<span></div>';
			}
			echo '<div id="comment-status"></div>';
		}
	}

	public function insert_identifier( $id_elements ) {
		return $id_elements . "<input type='hidden' name='dustpress_comments' id='dustpress_comments_identifier' value='1' />\n";
	}

	public function threadify_comments( $comments, $parent = 0 ) {	
		$threaded = array();

		foreach ( $comments as $key => $c ) {
			if ( $c->comment_parent == $parent ) {
				$c->replies = $this->threadify_comments( $comments, $c->comment_ID );
				$threaded[] = $c;
				unset( $comments[$key] );
			}			
		}

		return $threaded;
	}

	public function has_parent( $c ) {
		$parent = (int) $c->comment_parent;
		if ( $parent > 0 ) {
			return true;
		}
		else {
			return false;
		}
	}

	public function get_error_handler() {
		return array($this, 'handle_error');
	}

	public function handle_error(  $message, $title, $args ) {
		$return = [
			'error'  	=> true,
			'title'		=> $title,
			'message'	=> $message
		];		

		die( json_encode( $return ) );
	}

}