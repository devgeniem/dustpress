<?php 

/*
 *  Post
 *	
 *  Wrapper for WP post functions.
 *  Simplifies post queries for getting meta 
 *  data and acf fields with single function call.
 * 
 */
class DustPressHelper {

	private $post;
	private $posts;
	
	/*
	*  getPost
	*
	*  This function will query single post and its meta.
	*  The wanted meta keys should be in an array as strings.
	*  A string 'all' returns all the meta keys and values in an associative array.
	*  If 'single' is set to true then the functions returns only the first value of the specified meta_key.
	*
	*  @type	function
	*  @date	20/3/2015
	*  @since	0.0.1
	*
	*  @param	$id (int)
	*  @param	$metaKeys (array/string)
	*  @param	$single (boolean)
	*  @param	$metaType (string)
	*
	*  $return  post object as an associative array with meta data
	*/
	public function getPost( $id, $metaKeys = NULL, $single = false, $metaType = 'post' ) {
		global $post;

		$this->post = get_post( $id, 'ARRAY_A' );
		if ( is_array( $this->post ) ) {
			$this->getPostMeta( $this->post, $id, $metaKeys, $single, $metaType );
		}

		return $this->post;
	}

	/*
	*  getAcfPost
	*
	*  This function will query single post and its meta.
	*  Meta data is handled the same way as in getPost.
	*
	*  @type	function
	*  @date	20/3/2015
	*  @since	0.0.1
	*
	*  @param	$id (int)
	*  @param	$metaKeys (array/string)
	*  @param	$single (boolean)
	*  @param	$metaType (string)
	*  @param 	$wholeFields (boolean)
	*  @param 	$recursive (boolean)
	*
	*  $return  post object as an associative array with acf fields and meta data
	*/
	public function getAcfPost( $id, $metaKeys = NULL, $single = false, $metaType = 'post', $wholeFields = false, $recursive = false ) {

		$acfpost = get_post( $id, 'ARRAY_A' );
		
		if ( is_array( $acfpost ) ) {
			$acfpost['fields'] = get_fields( $id );

			// Get fields with relational post data as whole acf object
			if ( $recursive ) {
				foreach ($acfpost['fields'] as &$field) {
					if ( is_array($field) && is_object($field[0]) ) {
						for ($i=0; $i < count($field); $i++) { 
							$field[$i] = $this->getAcfPost( $field[$i]->ID, $metaKeys, $single, $metaType, $wholeFields, $recursive );
						}
					}
				}
				
			}
			elseif ( $wholeFields ) {
				foreach($acfpost['fields'] as $name => &$field) {
					$field = get_field_object($name, $id, true);
				}
			}
			$this->getPostMeta( $acfpost, $id, $metaKeys, $single, $metaType );
		}

		return $acfpost;
	}

	/*
	*  getPosts
	*
	*  This function will query all posts and its meta based on given arguments.
	*  The wanted meta keys should be in an array as strings.
	*  A string 'all' returns all the meta keys and values in an associative array.
	*
	*  @type	function
	*  @date	20/3/2015
	*  @since	0.0.1
	*
	*  @param	$id (int)
	*  @param	$metaKeys (array/string)	
	*  @param	$metaType (string)
	*
	*  @return	array of posts as an associative array with meta data
	*/
	public function getPosts( $args, $metaKeys = NULL, $metaType = 'post' ) {

		$temps = get_posts( $args );

		foreach ($temps as $temp) {
			$this->posts[] = (array) $temp;
		}
		
		// get meta for posts
		if ( count( $this->posts ) ) {
			$this->getMetaForPosts( $this->posts, $metaKeys, $metaType );
			
			wp_reset_postdata();
			return $this->posts;
		}	
		else
			return false;
	}

	/*
	*  getAcfPosts
	*
	*  This function can query multiple posts which have acf fields based on given arguments.
	*  Returns all the acf fields as an array.
	*  Meta data is handled the same way as in getPosts.
	*
	*  @type	function
	*  @date	20/3/2015
	*  @since	0.0.1
	*
	*  @param	$id (int)
	*  @param	$metaKeys (array/string)	
	*  @param	$metaType (string)
	*
	*  @return	array of posts as an associative array with acf fields and meta data
	*/
	public function getAcfPosts( $args, $metaKeys = NULL, $metaType = 'post', $wholeFields = false ) {

		$temps = get_posts( $args );

		foreach ($temps as $temp) {
			$this->posts[] = (array) $temp;
		}
		
		if ( count( $this->posts ) ) {
			// loop through posts and get all acf fields
			foreach ( $this->posts as &$p ) {								
				$p['fields'] = get_fields( $p['ID'] );
				if($wholeFields) {
					foreach($p['fields'] as $name => &$field) {
						$field = get_field_object($name, $p['ID'], true);
					}
				}
			}

			$this->getMetaForPosts( $this->posts, $metaKeys, $metaType );

			wp_reset_postdata();
			return $this->posts;
		}	
		else
			return false;
	}


	/*
	 *
	 * Private functions
	 *
	 */
	private function getPostMeta( &$post, $id, $metaKeys = NULL, $single = false, $metaType = 'post' ) {
		$meta = array();

		if ($metaKeys === 'all') {
			$meta = get_metadata( $metaType, $id );
		}
		elseif (is_array($metaKeys)) {
			foreach ($metaKeys as $key) {
				$meta[$key] = get_metadata( $metaType, $id, $key, $single );
			}
		}

		$post['meta'] = $meta;
	}

	private function getMetaForPosts( &$posts, $metaKeys = NULL, $metaType = 'post' ) {
		if ($metaKeys === 'all') {
			// loop through posts and get the meta values
			foreach ($posts as $post) {				
				$post['meta'] = get_metadata( $metaType, $post->ID );				
			}				
		}
		elseif (is_array($metaKeys)) {
			// loop through selected meta keys
			foreach ($metaKeys as $key) {
				// loop through posts and get the meta values
				foreach ($posts as &$post) {					
					$post['meta'][$key] = get_metadata( $metaType, $post->ID, $key, $single = false);	
				}	
			}

		}		
	}

}