<?php

$this->dust->helpers['pagination'] = function (\Dust\Evaluate\Chunk $chunk, \Dust\Evaluate\Context $ctx, \Dust\Evaluate\Bodies $bodies, \Dust\Evaluate\Parameters $params) {
	if ( $bodies->dummy !== true ) {
		// build pagination
		$pagination = new Pagination_Helper( $params );

		// add data into debugger
		dustpress()->set_debugger_data( 'Pagination', $data );

		// print out rendered html
		return $chunk->write( $pagination->get_output() );
	}
};


class Pagination_Helper {

	private $data;
	private $output;
	private $page_label;

	public function __construct( $params ) {

		$data 				= new stdClass();
		$pages				= array();
		$visible 			= 7;
		$neighbours			= 3;
		$hellip_start 		= true;
		$hellip_end 		= true;
	  	$cur_page			= (int) $params->page;
		$prev_page			= $cur_page - 1;
		$next_page 			= $cur_page + 1;
		$offset 			= (int) $params->offset;
		$rows 				= (int) $params->rows;
		$hash				= $params->hash 		? '#' . $params->hash 	: '';
		$this->page_label	= $params->page_label 	? $params->page_label 	: 'page';

		// more rows than the set offset
		if ( ( $rows - $offset ) > 0 ) {
			$page_count = ceil( $rows / $offset );

			$first_page = 1;
			$last_page 	= $page_count;

			$on_first_page 	= false;
			$on_last_page 	= false;

			// on the first page
			if ( $cur_page == $first_page ) {
				$hellip_start = '';
				$on_first_page = true;
				for ( $i = 0; $i < 7; $i++ ) {
					if ( ( $i + 1 ) > $page_count ) {
						$hellip_end = '';
						break;
					}
					$pages[$i] = new stdClass();
					$pages[$i]->page = $i + 1;
					if ( $cur_page == $pages[$i]->page ) $pages[$i]->active = true;
				}
			}
			// on the last page
			elseif ( $cur_page == $last_page ) {
				$hellip_end = '';
				$on_last_page = true;
				if ( $page_count <= $visible ) {
					$hellip_start = '';
					for ( $i = 0; $i < $page_count; $i++ ) {
						$pages[$i] = new stdClass();
						$pages[$i]->page = $i + 1;
						if ( $cur_page == $pages[$i]->page ) $pages[$i]->active = true;
					}
				}
				else {
					$start = $page_count - $visible + 1;
					for ( $i = $start; $i <= $page_count; $i++ ) {
						$pages[$i] = new stdClass();
						$pages[$i]->page = $i;
						if ( $cur_page == $pages[$i]->page ) $pages[$i]->active = true;
					}
				}
			}
			// on a random page
			else {
				$start = $cur_page - $neighbours;
				if ( $start <= 1 ) {
					$start = 1;
					$hellip_start = '';
				}
				$end = $cur_page + $neighbours;
				if ( $end >= $page_count ) {
					$end = $page_count;
					$start = $start - ( ( $cur_page + $neighbours ) - $page_count );
					if ( $start <= 1 ) {
						$start = 1;
						$hellip_start = '';
					}
					$hellip_end = '';
				}

				// display max number of pages
				$max_pages = $start + ( $visible - 1 );
				if ( $max_pages <= $page_count ) {
					for ( $i = $start; $i <= $max_pages; $i++) {
						$pages[$i] = new stdClass();
						$pages[$i]->page = $i;
						if ( $cur_page == $pages[$i]->page ) $pages[$i]->active = true;
					}
				}
				// display less
				else {
					for ( $i = $start; $i <= $end; $i++) {
						$pages[$i] = new stdClass();
						$pages[$i]->page = $i;
						if ( $cur_page == $pages[$i]->page ) $pages[$i]->active = true;
					}
				}
			}

			if ( $prev_page == 0 ) {
				$prev_page = '';
			}
			if ( $next_page > $page_count ) {
				$next_page = '';
			}

		}		

		$page_link = $this->build_page_link();

		// map data
		$data->on_first_page 		= $on_first_page;
		$data->on_last_page 		= $on_last_page;
		$data->first_page 			= $first_page;
		$data->last_page 			= $last_page;
		$data->pages 				= $pages;
		$data->hellip_start 		= $hellip_start;
		$data->hellip_end 			= $hellip_end;
		$data->next_page 			= $next_page;
		$data->prev_page 			= $prev_page;
		$data->hash 				= $hash;
		$data->page_label			= $this->page_label;
		$data->page_link			= apply_filters( 'dustpress/pagination/page_link', $page_link );

		$data->S 					= new stdClass();
		$data->S->prev				= __( 'Previous', 'DustPressPagination' );
		$data->S->next				= __( 'Next', 'DustPressPagination' );
		$data->S->start				= __( 'Start', 'DustPressPagination' );
		$data->S->end				= __( 'End', 'DustPressPagination' );

		$this->data = $data;

		$this->output = dustpress()->render( [
			"partial" 	=> "pagination",
			"data" 		=> $this->data,
			"type" 		=> "html",
			"echo" 		=> false
		]);
	}

	public function build_page_link() {
		$query_string 	= $_SERVER['QUERY_STRING'];
		$page_link 		= '?';
		// user passed get parameters
		if ( $query_string ) {
			// a page queried
			if ( strpos( $query_string, $this->page_label ) !== false ) {
				$idx = 1;
				foreach ( $_GET as $key => $value ) {
					if ( $key != $this->page_label ) {
						if ( $idx == 1 ) {
							$page_link .= $key . '=' . $value;
						}
						else {
							$page_link .= '&' . $key . '=' . $value;
						}
					}
					$idx++;
				}
				$page_link .= '&' . $this->page_label . '=';
			}
			// no page queried
			else {
				$page_link .= $query_string . '&' . $this->page_label . '=';
			}
		}
		// no get parameters
		else {
			$page_link .= $this->page_label . '=';
		}

		return $page_link;
	}

	public function get_output() {
		return $this->output;
	}
}