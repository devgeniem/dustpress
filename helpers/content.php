<?php
namespace DustPress;

class Content extends Helper
{
    public function output() {
        if ( $this->bodies->dummy !== true ) {
			global $post;
			
			if ( $this->params->data ) {		
				return apply_filters( 'the_content', $params->data );
			}
			else {
				ob_start();
				setup_postdata( $post );
				the_content();
				wp_reset_postdata();
				return ob_get_clean();
			}
		}
    }
}

$this->dust->helpers['content'] = new Content();