<?php
/**
 * Menu
 */

namespace DustPress;

/**
 * DustPress Menu Helper class
 */
class Menu extends Helper {

    /**
     * The object cache key prefix.
     */
    const CACHE_KEY_PREFIX = 'dustpress_menu_helper_';

    /**
     * Renders and outputs the menu HTML.
     *
     * @return $output (string)
     */
    public function output() {

        if ( ! isset( $this->params->menu_name ) && ! isset( $this->params->menu_id ) ) {
            return $this->chunk->write( 'DustPress menu helper error: No menu specified.' );
        } else if ( isset( $this->params->menu_id ) ) {
            $menu_id = $this->params->menu_id;
            $id_given = true;
        } else {
            $menu_name = $this->params->menu_name;
        }

        if ( isset( $this->params->parent ) ) {
            $parent = $this->params->parent;
        } else {
            $parent = 0;
        }

        if ( isset( $this->params->depth ) ) {
            $depth = $this->params->depth;
        } else {
            $depth = PHP_INT_MAX;
        }

        if ( isset( $this->params->override ) ) {
            $override = $this->params->override;
        } else {
            $override = null;
        }

        if ( isset( $this->params->ul_classes ) ) {
            $ul_classes = $this->params->ul_classes;
        } else {
            $ul_classes = '';
        }

        if ( isset( $this->params->ul_id ) ) {
            $ul_id = $this->params->ul_id;
        } else {
            $ul_id = '';
        }

        if ( isset( $this->params->show_submenu ) ) {
            $show_submenu = $this->params->show_submenu;
        } else {
            $show_submenu = true;
        }

        if ( isset( $this->params->menu_partial ) ) {
            $menu_partial = $this->params->menu_partial;
        } else {
            $menu_partial = "menu";
        }

        if ( isset( $this->params->menuitem_partial ) ) {
            $menuitem_partial = $this->params->menuitem_partial;
        } else {
            $menuitem_partial = "menuitem";
        }

        if ( isset( $this->params->data ) ) {
            $custom_data = $this->params->data;
        } else {
            $custom_data = null;
        }

        $menu = new \stdClass();

        if ( isset( $menu_name ) ) {
            $menu_data = self::get_menu_data( $menu_name, $parent, $override );
        } else {
            $menu_data = self::get_menu_data( $menu_id, $parent, $override, true );
        }

        $menu->menu_object = $menu_data['menu_object'];
        $menu->items = $menu_data['menu_items'];
        $menu->ul_classes = $ul_classes;
        $menu->ul_id = $ul_id;
        $menu->show_submenu = $show_submenu;

        $menu->menuitem_partial = $menuitem_partial;
        $menu->depth = $depth;

        $menu->data = $custom_data;

        $menu = apply_filters( "dustpress/menu/data", $menu );

        $output = dustpress()->render( [
            'partial' => $menu_partial,
            'data' => $menu,
            'type' => 'html',
            'echo' => false,
        ]);

        return apply_filters( "dustpress/menu/output", $output );
    }

    /*
     * The following functions gather menu data to use with DustPress
     * helper and developers' implementations.
     */

    /**
     * Returns all menu items arranged in a recursive array form that's
     * easy to use with Dust templates. Menu_name parameter is mandatory.
     * Parent is used to get only submenu for certaing parent post ID.
     * Override is used to make some other post than the current 'active'.
     *
     * @type        function
     * @date        16/6/2015
     * @since       0.0.2
     *
     * @param string  $menu_name
     * @param integer $parent
     * @param integer $override
     * @param boolean $menu_id_given
     *
     * @return      array of menu object and menu items in a recursive array
     */
    public static function get_menu_data( $menu_name, $parent = 0, $override = null, $menu_id_given = false ) {

        $locations = \get_nav_menu_locations();

        if ( $menu_id_given ) {
            $menu_object = \wp_get_nav_menu_object( $menu_name );
        } else {
            if ( ( $locations ) && isset( $locations[ $menu_name ] ) ) {
                $menu_object = \wp_get_nav_menu_object( $locations[ $menu_name ] );
            }
        }

        if ( ! empty( $menu_object ) ) {
            $menu_items = static::get_cached_menu_items( $menu_object->term_id );

            if ( empty( $menu_items ) || \is_customize_preview() ) {
                $menu_items = \wp_get_nav_menu_items( $menu_object );

                if ( ! empty( $menu_items ) ) {
                    static::set_cached_menu_items( $menu_object->term_id, $menu_items );
                }
            }

            // Add menu object location to the menu object.
            // You can use this to filter specific menu items.
            if ( ! empty( $menu_object->term_id ) && is_array( $locations ) ) {
                $menu_object->location = array_search( $menu_object->term_id, $locations );
            }
        }
        else {
            return null;
        }

        $parent_id = 0;

        if ( $parent ) {
            if ( count( $menu_items ) > 0 ) {
                foreach ( $menu_items as $item ) {
                    if ( $item->object_id == $parent ) {
                        $parent_id = $item->ID;
                        break;
                    }
                }
            }
        }

        if ( $menu_items ) {

            $built_menu_items = self::build_menu( $menu_items, $parent_id, null, $override );

            $active = array_keys( $built_menu_items, 'active' );

            foreach( $active as $index ) {
                unset( $built_menu_items[ $index ] );
            }

            if ( 0 === array_search( 'active', $built_menu_items ) ) {
                unset( $built_menu_items[0] );
            }

            // return menu object and menu items
            $menu = [];
            $menu['menu_object'] = apply_filters( 'dustpress/menu/object/location=' . $menu_object->location, $menu_object );
            $menu['menu_items'] = apply_filters( 'dustpress/menu/items/location=' . $menu_object->location, $built_menu_items );

            return apply_filters( 'dustpress/menu', $menu );
        }
    }

    /**
     * Recursive function that builds a menu downwards from an item. Calls
     * itself recursively in case there is a submenu under current item.
     *
     * @type        function
     * @date        16/6/2015
     * @since       0.0.2
     *
     * @param  array    $menu_items Queried menu items.
     * @param  integer  $parent
     * @param  string   $type
     * @param  integer  $override   An id to match for current queried object.
     * @return [type]
     */
    private static function build_menu( $menu_items, $parent = 0, $type = 'page', $override = null ) {
        $temp_items = [];

        if ( empty( $type ) ) {
            $type = 'page';
        }

        if ( count( $menu_items ) > 0 ) {
            foreach ( $menu_items as $item ) {
                if ( $item->menu_item_parent == $parent ) {
                    $item->sub_menu = self::build_menu( $menu_items, $item->ID, $item->object, $override );

                    // Make sure $item->classes is an array. Needed to work with the Customizer.
                    if ( ! is_array( $item->classes ) ) {
                        $item->classes = [];
                    }

                    if ( is_array( $item->sub_menu ) && count( $item->sub_menu ) > 0 ) {
                        $item->classes[] = 'menu-item-has-children';
                    }
                    if ( is_array( $item->sub_menu ) && $index = array_search( 'active', $item->sub_menu ) ) {
                        $item->classes[] = 'current-menu-parent';
                        unset( $item->sub_menu[ $index ] );
                        $temp_items[] = 'active';
                    }
                    if ( is_array( $item->sub_menu ) && 0 === array_search( 'active', $item->sub_menu ) ) {
                        $item->classes[] = 'current-menu-parent';
                        unset( $item->sub_menu[0] );
                        $temp_items[] = 'active';
                    }

                    if ( in_array( $item->object_id, get_post_ancestors( get_the_ID() ) ) ) {
                        $item->classes[] = 'current-menu-parent';
                    }

                    if ( self::is_current( $item, $override ) ) {
                        $item->classes[] = 'current-menu-item';
                        $temp_items[] = 'active';
                    }

                    if ( is_array( $item->classes ) ) {
                        $item->classes = array_filter( $item->classes );
                    }

                    $item->classes = (array) apply_filters( "dustpress/menu/item/classes", $item->classes, $item );
                    $item = apply_filters( "dustpress/menu/item", $item );

                    $item->classes[] = 'menu-item';
                    $item->classes[] = 'menu-item-' . $item->object_id;

                    $temp_items[] = $item;
                }
            }
        }
        return $temp_items;
    }

    /**
     * Checks whether the current menu item is the queried object.
     *
     * @param  object  $item       A menu item.
     * @param  integer $override   An id to match with the current object.
     * @return boolean
     */
    private static function is_current( $item, $override ) {
        if ( \is_category() || \is_tag() || \is_tax() ) {
            $term_id = \get_queried_object()->term_id;

            $return = ( $item->object_id == $term_id && 'taxonomy' == $item->type )
                      ||  ( $item->object_id == $override );
        }
        else {
            $return = ( \get_the_ID() == $item->object_id
                        &&  'post_type' == $item->type )
                      // Check if on a static page that shows posts.
                      ||  ( \is_home() && ! \is_front_page() && \get_queried_object_id() == $item->object_id )
                      ||  ( $item->object_id == $override );
        }

        return apply_filters( "dustpress/menu/is_current", $return, $item );
    }

    /**
     * Get the cached menu items.
     *
     * @param mixed $menu_id The menu id.
     *
     * @return bool|array
     */
    protected static function get_cached_menu_items( ?int $menu_id ) {
        if ( static::disable_cache() ) {
            return false;
        }
        $cache = wp_cache_get( static::CACHE_KEY_PREFIX . $menu_id );
        return $cache;
    }

    /**
     * Set menu items into cache.
     *
     * @param mixed $menu_id    The menu id.
     * @param array $menu_items The menu items.
     *
     * @return bool
     */
    protected static function set_cached_menu_items( ?int $menu_id, ?array $menu_items = [] ) {
        if ( static::disable_cache() ) {
            return false;
        }

        // Define the cache expiration time in secods.
        $expire = defined( 'DUSTPRESS_MENU_HELPER_CACHE_EXPIRE' ) ? DUSTPRESS_MENU_HELPER_CACHE_EXPIRE : 15 * MINUTE_IN_SECONDS;

        return wp_cache_set( static::CACHE_KEY_PREFIX . $menu_id, $menu_items, null, $expire );
    }

    /**
     * Delete menu items cache when a menu is updated.
     *
     * @param int $menu_id The menu id.
     *
     * @return bool
     */
    public static function delete_cached_menu_items( $menu_id = 0 ) {
        if ( static::disable_cache() ) {
            return false;
        }

        return wp_cache_delete( static::CACHE_KEY_PREFIX . $menu_id );
    }

    /**
     * Defines whether to disable caching.
     *
     * @return bool
     */
    protected static function disable_cache() {
        $disable = defined( 'DUSTPRESS_MENU_HELPER_CACHE_DISABLE' ) && DUSTPRESS_MENU_HELPER_CACHE_DISABLE === true;
        return $disable;
    }
}

$this->add_helper( 'menu', new Menu() );

// Add hook to delete menu items cache if a menu is updated.
add_action( 'wp_update_nav_menu', [ Menu::class, 'delete_cached_menu_items' ], 1, 1 );
