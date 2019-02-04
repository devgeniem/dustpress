<?php
/**
 * Pagination
 */

namespace DustPress;

/**
 * DustPress Pagination Helper class
 */
class Pagination extends Helper {

    /**
     * An object for binding the helper data.
     *
     * @var object
     */
    private $data;

    /**
     * The query variable to identify the current page.
     *
     * @var string
     */
    private $page_var;

    /**
     * Output the helper html.
     *
     * @return string
     */
    public function output() {

        $params         = $this->params;
        $data           = (object) [];
        $pages          = array();
        $neighbours     = isset( $params->neighbours ) ? (int) $params->neighbours : 3;
        $visible        = 1 + ( 2 * $neighbours );
        $hellip_start   = true;
        $hellip_end     = true;
        $strings        = isset( $params->strings ) ? $params->strings    : [];
        $cur_page       = isset( $params->page )    ? (int) $params->page : 1;
        $prev_page      = $cur_page - 1;
        $next_page      = $cur_page + 1;
        $per_page       = (int) $params->per_page;
        $items          = (int) $params->items;
        $hash           = $params->hash     ? '#' . $params->hash : '';
        $this->page_var = $params->page_var ? $params->page_var   : 'paged';

        // Setup strings
        $defaults = [
            'previous' => __( 'Previous', 'dustpress' ),
            'next'     => __( 'Next', 'dustpress' ),
            'start'    => __( 'Start', 'dustpress' ),
            'end'      => __( 'End', 'dustpress' ),
        ];
        $strings  = wp_parse_args( $strings, $defaults );

        // Prevent dividing if there are zero items.
        if ( $per_page > 0 ) {
            $page_count = (int) ceil( $items / $per_page );
        }
        else {
            $page_count = 1;
        }

        $first_page = 1;
        $last_page  = $page_count;

        $on_first_page = false;
        $on_last_page  = false;

        // There are more items than one page can hold.
        if ( ( $items - $per_page ) > 0 ) {
            // On the first page.
            if ( $cur_page === $first_page ) {
                $hellip_start  = '';
                $on_first_page = true;
                for ( $i = 0; $i < 7; $i++ ) {
                    if ( ( $i + 1 ) > $page_count ) {
                        $hellip_end = '';
                        break;
                    }
                    $pages[ $i ]       = (object) [];
                    $pages[ $i ]->page = $i + 1;
                    if ( $cur_page === $pages[ $i ]->page ) {
                        $pages[ $i ]->active = true;
                    }
                }
            }
            // On the last page.
            elseif ( $cur_page === $last_page ) {
                $hellip_end   = '';
                $on_last_page = true;
                if ( $page_count <= $visible ) {
                    $hellip_start = '';
                    for ( $i = 0; $i < $page_count; $i++ ) {
                        $pages[ $i ]       = (object) [];
                        $pages[ $i ]->page = $i + 1;
                        if ( $cur_page === $pages[ $i ]->page ) {
                            $pages[ $i ]->active = true;
                        }
                    }
                }
                else {
                    $start = $page_count - $visible + 1;
                    for ( $i = $start; $i <= $page_count; $i++ ) {
                        $pages[ $i ]       = (object) [];
                        $pages[ $i ]->page = $i;
                        if ( $cur_page === $pages[ $i ]->page ) {
                            $pages[ $i ]->active = true;
                        }
                    }
                }
            }
            // On a random page.
            else {
                $start = $cur_page - $neighbours;
                if ( $start <= 1 ) {
                    $start        = 1;
                    $hellip_start = '';
                }
                $end = $cur_page + $neighbours;
                if ( $end >= $page_count ) {
                    $end   = $page_count;
                    $start = $start - ( ( $cur_page + $neighbours ) - $page_count );
                    if ( $start <= 1 ) {
                        $start        = 1;
                        $hellip_start = '';
                    }
                    $hellip_end = '';
                }

                // display max number of pages
                $max_pages = $start + ( $visible - 1 );
                if ( $max_pages <= $page_count ) {
                    for ( $i = $start; $i <= $max_pages; $i++ ) {
                        $pages[ $i ]       = (object) [];
                        $pages[ $i ]->page = $i;
                        if ( $cur_page === $pages[ $i ]->page ) {
                            $pages[ $i ]->active = true;
                        }
                    }
                }
                // display less
                else {
                    for ( $i = $start; $i <= $end; $i++ ) {
                        $pages[ $i ]       = (object) [];
                        $pages[ $i ]->page = $i;
                        if ( $cur_page === $pages[ $i ]->page ) {
                            $pages[ $i ]->active = true;
                        }
                    }
                }
            }

            if ( $prev_page === 0 ) {
                $prev_page = '';
            }
            if ( $next_page > $page_count ) {
                $next_page = '';
            }
        }

        $page_link = $this->build_page_link();

        // map data
        $data->on_first_page = $on_first_page;
        $data->on_last_page  = $on_last_page;
        $data->first_page    = $first_page;
        $data->last_page     = $last_page;
        $data->pages         = $pages;
        $data->hellip_start  = $hellip_start;
        $data->hellip_end    = $hellip_end;
        $data->next_page     = $next_page;
        $data->prev_page     = $prev_page;
        $data->hash          = $hash;
        $data->page_var      = $this->page_var;
        $data->page_link     = apply_filters( 'dustpress/pagination/page_link', $page_link );

        $data->S        = (object) [];
        $data->S->prev  = $strings['previous'];
        $data->S->next  = $strings['next'];
        $data->S->start = $strings['start'];
        $data->S->end   = $strings['end'];

        $this->data = $data;

        $this->data = $data;

        // Add this data to DustPress debuggers output.
        $debugger_class = __NAMESPACE__ . '\Debugger';
        if ( \class_exists( $debugger_class ) && \method_exists( $debugger_class, 'set_debugger_data' )  ) {
            Debugger::set_debugger_data( 'Pagination', $data );
        }

        return dustpress()->render(
            [
                'partial' => 'pagination',
                'data'    => $this->data,
                'type'    => 'html',
                'echo'    => false,
            ]
        );
    }

    /**
     * Format the pagination page link by adding the pagination
     * parameter to the current query string.
     *
     * @return string
     */
    public function build_page_link() {
        $query_string = filter_var( $_SERVER['QUERY_STRING'], FILTER_SANITIZE_STRING );
        $page_link    = '?';
        // User passed get parameters
        if ( $query_string ) {
            // A page queried.
            if ( strpos( $query_string, $this->page_var ) !== false ) {
                $idx = 1;
                foreach ( $_GET as $key => $value ) {
                    if ( $key !== $this->page_var ) {
                        if ( is_array( $value ) ) {
                            foreach ( $value as $v ) {
                                $page_link .= rawurlencode( $key ) . '%5B%5D=';
                                $page_link .= rawurlencode( $v ) . '&';
                            }
                        }
                        else {
                            $page_link .= rawurlencode( $key ) . '=' . rawurlencode( $value ) . '&';
                        }
                    }
                    $idx++;
                }
                $page_link .= $this->page_var . '=';
            }
            // No page queried.
            else {
                $page_link .= $query_string . '&' . $this->page_var . '=';
            }
        }
        // No get parameters
        else {
            $page_link .= $this->page_var . '=';
        }

        return $page_link;
    }

    /**
     * Set helper parameters.
     *
     * @param object $params Helper parameters.
     */
    public function set_params( $params ) {
        $this->params = $params;
    }
}

$this->add_helper( 'pagination', new Pagination() );
