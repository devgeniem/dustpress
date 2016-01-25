<?php 

/*
 *  DustPressHelper
 *	
 *  Wrapper for bunch of helper functions to use
 *  with DustPress.
 * 
 */

class DustPressHelper {

	/*
	 *  Post functions
	 *	
	 *  Simplify post queries for getting meta 
	 *  data and ACF fields with single function call.
	 * 
	 */

	private static $post;
	private static $posts;
	
	/*
	*  get_post
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
	*  @param	$args (array)
	*
	*  $return  post object as an associative array with meta data
	*/
	public static function get_post( $id, $args = array() ) {
		global $post;

		$defaults = [
			"meta_keys" => null,
			"single" => false,
			"meta_type" => "post"
		];

		$options = array_merge( $defaults, $args );

		extract( $options );

		self::$post = get_post( $id, 'ARRAY_A' );
		if ( is_array( self::$post ) ) {
			self::get_post_meta( self::$post, $id, $meta_keys, $meta_type, $single );
		}

		self::$post['permalink'] = get_permalink($id);

		return self::$post;
	}

	/*
	*  get_acf_post
	*
	*  This function will query a single post and its meta.
	*  
	*  If the args has a key 'recursive' with the value 'true', relational 
	*  post objects are loaded recursively to get the full object.
	*  Meta data is handled the same way as in get_post.
	*
	*  @type	function
	*  @date	20/3/2015
	*  @since	0.0.1
	*
	*  @param	$id (int)
	*  @param	$args (array)
	*
	*  $return  post object as an associative array with acf fields and meta data
	*/
	public static function get_acf_post( $id, $args = array() ) {

		$defaults = [
			"meta_keys" => null,
			"single" => false,
			"meta_type" => "post",
			"whole_fields" => false,
			"recursive" => false
		];

		$options = array_merge( $defaults, $args );

		extract( $options );

		$acfpost = get_post( $id, 'ARRAY_A' );
		
		if ( is_array( $acfpost ) ) {
			$acfpost['fields'] = get_fields( $id );
		
			// Get fields with relational post data as a whole acf object
			if ( $recursive ) {
				foreach ($acfpost['fields'] as &$field) {										
					if ( is_array($field) && isset( $field[0]->post_type ) ) {
						for ( $i=0; $i < count( $field ); $i++ ) { 
							$field[$i] = self::get_acf_post( $field[$i]->ID, [ "meta_keys" => $meta_keys, "single" => $single, "meta_type" => $meta_type, "whole_fields" => $whole_fields, "recursive" => $recursive ] );
						}
					}
					// a repeater field has relational posts
					if ( is_array( $field ) && is_array( $field[0] ) ) {												
						foreach ( $field as $idx => &$repeater ) {													
							if ( is_array( $repeater ) ) {
								foreach ( $repeater as &$row ) {
									if ( isset( $row[0]->post_type ) ) {									
										for ( $i=0; $i < count( $row ); $i++ ) { 												
												$row[$i] = self::get_acf_post( $row[$i]->ID, [ "meta_keys" => $meta_keys, "single" => $single, "meta_type" => $meta_type, "whole_fields" => $whole_fields, "recursive" => $recursive ] );
											}
										}
								}								
							}														
						}
					}
				}						
			}
			elseif ( $wholeFields ) {
				foreach( $acfpost['fields'] as $name => &$field ) {
					$field = get_field_object($name, $id, true);
				}
			}
			self::get_post_meta( $acfpost, $id, $meta_keys, $meta_type, $single );
		}

		$acfpost['permalink'] = get_permalink($id);


		return $acfpost;
	}

	/*
	*  get_posts
	*
	*  This function will query all posts and its meta based on given arguments.
	*  The wanted meta keys should be in an array as strings.
	*  A string 'all' returns all the meta keys and values in an associative array.
	*
	*  @type	function
	*  @date	20/3/2015
	*  @since	0.0.1
	*
	*  @param	$args (array)
	*
	*  @return	array of posts as an associative array with meta data
	*/
	public static function get_posts( $args ) {

        $defaults = [
            "meta_keys" => null,
            "single" => false,
            "meta_type" => "post",
            "whole_fields" => false,
        ];

        $options = array_merge( $defaults, $args );

        extract( $options );

		self::$posts = get_posts( $args );

		// cast post object to associative arrays
		// and get the permalink of the post
		foreach (self::$posts as &$temp) {
			$temp = (array) $temp;
			$temp['permalink'] = get_permalink( $temp['ID'] );
		}
		
		// get meta for posts
		if ( count( self::$posts ) ) {
			self::get_meta_for_posts( self::$posts, $meta_keys, $meta_type, $single );
			wp_reset_postdata();
			return self::$posts;
		}	
		else
			return false;
	}

	/*
	*  get_acf_posts
	*
	*  This function can query multiple posts which have acf fields based on given arguments.
	*  Returns all the acf fields as an array.
	*  Meta data is handled the same way as in get_posts.
	*
	*  @type	function
	*  @date	20/3/2015
	*  @since	0.0.1
	*
	*  @param	$args (array)
	*
	*  @return	array of posts as an associative array with acf fields and meta data
	*/
	public static function get_acf_posts( $args ) {

        $defaults = [
            "meta_keys" => null,
            "single" => false,
            "meta_type" => "post",
            "whole_fields" => false,
        ];

        $options = array_merge( $defaults, $args );

        extract( $options );

		self::$posts = get_posts( $args );

		// cast post object to associative arrays
		foreach (self::$posts as &$temp) {
			$temp = (array) $temp;
		}

		if ( count( self::$posts ) ) {
			// loop through posts and get all acf fields
			foreach ( self::$posts as &$p ) {								
				$p['fields'] = get_fields( $p['ID'] );
				$p['permalink'] = get_permalink( $p['ID'] );
				if( $whole_fields ) {
					foreach($p['fields'] as $name => &$field) {
						$field = get_field_object($name, $p['ID'], true);
					}
				}
			}

			self::get_meta_for_posts( self::$posts, $meta_keys, $meta_type, $single );

			wp_reset_postdata();
			return self::$posts;
		}	
		else
			return false;
	}


	/*
	 *
	 * Private functions
	 *
	 */
    private function get_post_meta( &$post, $id, $meta_keys = NULL, $meta_type, $single ) {
		$meta = array();

		if ( $meta_keys === 'all' ) {
			$meta = get_metadata( $meta_type, $id );
		}
		elseif ( is_array( $meta_keys ) ) {
			foreach ( $meta_keys as $key ) {
				$meta[$key] = get_metadata( $meta_type, $id, $key, $single );
			}
		}

		$post['meta'] = $meta;
	}

	private function get_meta_for_posts( &$posts, $meta_keys = NULL, $meta_type, $single ) {
		if ( $meta_keys === 'all' ) {
			// loop through posts and get the meta values
			foreach ( $posts as &$post ) {
				$post['meta'] = get_metadata( $meta_type, $post['ID'], '', $single );
			}
		}
		elseif ( is_array( $meta_keys ) ) {
			// loop through selected meta keys
			foreach ( $meta_keys as $key ) {
				// loop through posts and get the meta values
				foreach ( $posts as &$post ) {
					$post['meta'][$key] = get_metadata( $meta_type, $post['ID'], $key, $single );
				}	
			}

		}		
	}

	/*
	 *  Menu functions
	 *	
	 *  These functions gather menu data to use with DustPress
	 *  helper and developers' implementations.
	 * 
	 */

	/*
    *  get_menu_as_items
    *
    *  Returns all menu items arranged in a recursive array form that's
    *  easy to use with Dust templates. Menu_name parameter is mandatory.
    *  Parent is used to get only submenu for certaing parent post ID.
    *  Override is used to make some other post than the current "active".
    *
    *  @type        function
    *  @date        16/6/2015
    *  @since       0.0.2
    *
    *  @param       $menu_name (string)
    *  @param   	$parent (integer)
    *  @param       $override (integer)
    *
    *  @return      array of menu items in a recursive array
    */
    public static function get_menu_as_items( $menu_name, $parent = 0, $override = null ) {

            if ( ( $locations = get_nav_menu_locations() ) && isset( $locations[ $menu_name ] ) ) {
                    $menu_object = wp_get_nav_menu_object( $locations[ $menu_name ] );
            }

            $menu_items = wp_get_nav_menu_items( $menu_object );

            if ( $menu_items ) {

                    $menu = self::build_menu( $menu_items, $parent, null, $override );

                    if ( $index = array_search( "active", $menu ) ) {
                            unset( $menu[$index] );
                    }
                    if ( 0 === array_search( "active", $menu ) ) {
                            unset( $menu[0] );
                    }

                    return $menu;
            }
    }

    /*
    *  build_menu
    *
    *  Recursive function that builds a menu downwards from an item. Calls
    *  itself recursively in case there is a submenu under current item.
    *
    *  @type        function
    *  @date        16/6/2015
    *  @since       0.0.2
    *
    *  @param       $menu_items (array)
    *  @param       $parent (integer)
    *  @param       $override (integer)
    *
    *  @return      array of menu items
    */        

    private static function build_menu( $menu_items, $parent = 0, $type = "page", $override = null ) {
            $tempItems = array();
            $parent_id = 0;

            if ( count( $menu_items ) > 0 ) {
                    foreach ( $menu_items as $item ) {
                            if ( $item->object_id == $parent && $item->object == $type ) {
                                    $parent_id = $item->ID;
                                    break;
                            }
                    }
            }

            if ( is_tax() ) {
                $term_id = get_queried_object()->term_id;
            }

            if ( count( $menu_items ) > 0 ) {
                    foreach ( $menu_items as $item ) {
                            if ( $item->menu_item_parent == $parent_id ) {
                                    $item->Submenu = self::build_menu( $menu_items, $item->object_id, $item->object, $override );

                                    $item->classes = array();

                                    if ( is_array( $item->Submenu ) && count( $item->Submenu ) > 0 ) {
                                            $item->classes[] = "menu-item-has-children";
                                    }
                                    if ( is_array( $item->Submenu ) && $index = array_search( "active", $item->Submenu ) ) {
                                            $item->classes[] = "current-menu-parent";
                                            unset( $item->Submenu[$index] );
                                            $tempItems[] = "active";
                                    }
                                    if ( is_array( $item->Submenu ) && 0 === array_search( "active", $item->Submenu ) ) {
                                            $item->classes[] = "current-menu-parent";
                                            unset( $item->Submenu[0] );
                                            $tempItems[] = "active";
                                    }

                                    if ( ( $item->object_id == get_the_ID() && $item->type == "post_type" ) || ( $item->object_id == $term_id && $item->type == "taxonomy" ) || ( $item->object_id == $override ) ) {
                                            $item->classes[] = "current-menu-item";
                                            $tempItems[] = "active";
                                    }

                                    if ( is_array( $item->classes ) ) {
                                            $item->classes = array_filter($item->classes);
                                    }

                                    $item->classes[] = "menu-item";
                                    $item->classes[] = "menu-item-" . $item->object_id;

                                    $tempItems[] = $item;
                            }
                    }

            }

            return $tempItems;
    }
}