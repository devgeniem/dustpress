<?php 

/*
 *  Post
 *	
 *  Wrapper for WP post functions.
 *  Simplifies post queries for getting meta 
 *  data and acf fields with single function call.
 * 
 */
class Post {

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
	*  @return	$metaType (string)
	*/
	public function getPost( $id, $metaKeys = NULL, $single = false, $metaType = 'post' ) {
		global $post;

		$this->post = get_post( $id, 'ARRAY_A' );
		if ( is_array( $this->post ) ) {
			$this->post['meta'] = getPostMeta( $id, $metaKeys, $single, $metaType );
		}

		return $this->post;
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
	*  @return	$metaType (string)
	*/
	public function getPosts( $args, $metaKeys = NULL, $metaType = 'post' ) {

		$this->posts = get_posts( $args );
		
		// get meta for posts
		if ( count( $this->posts ) ) {
			$this->posts['meta'] = $this->getMetaForPosts( $this->posts, $metaKeys, $metaType );
			
			wp_reset_postdata();
			return $this->posts;
		}	
		else
			return false;
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
	*  @return	$metaType (string)
	*/
	public function getAcfPost( $id, $metaKeys = NULL, $single = false, $metaType = 'post' ) {

		$this->post = get_post( $id, 'ARRAY_A' );
		
		if ( is_array( $this->post ) ) {
			$this->post['fields'] = get_fields( $id );
			$this->post['meta']	= $this->getPostMeta( $id, $metaKeys, $single, $metaType );
		}

		return $this->post;
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
	*  @return	$metaType (string)
	*/
	public function getAcfPosts( $args, $metaKeys = NULL, $metaType = 'post' ) {

		$this->posts = get_posts( $args );
		
		if ( count( $this->posts ) ) {
			// loop through posts and get all acf fields
			foreach ($this->posts as $post) {
				$post->fields = get_fields( $post->ID );
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
	private function getPostMeta( $id, $metaKeys = NULL, $single = false, $metaType = 'post' ) {
		$meta = array();

		if ($metaKeys === 'all') {
			$meta = get_metadata( $metaType, $id );
		}
		elseif (is_array($metaKeys)) {
			foreach ($metaKeys as $key) {
				$meta[$key] = get_metadata( $metaType, $id, $key, $single );
			}
		}

		return $meta;
	}

	private function getMetaForPosts( &$posts, $metaKeys = NULL, $metaType = 'post' ) {
		if ($metaKeys === 'all') {
			// loop through posts and get the meta values
			foreach ($posts as $post) {				
				$post->meta = get_metadata( $metaType, $post->ID );				
			}				
		}
		elseif (is_array($metaKeys)) {
			// loop through selected meta keys
			foreach ($metaKeys as $key) {
				// loop through posts and get the meta values
				foreach ($posts as $post) {					
					$post->meta[$key] = get_metadata( $metaType, $post->ID, $key, $single = false);	
				}	
			}

		}		
	}

}