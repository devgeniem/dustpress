<?php
namespace DustPress;

class Excerpt extends Helper { 
    public function output() {
        global $post;

        if ( isset( $this->params->data ) ) {
            return apply_filters( 'the_excerpt', $this->params->data );
        }
        
        if ( isset( $this->params->id ) ) {
            $post = get_post( $this->params->id );
        }
          
        ob_start();
        setup_postdata( $post );
        the_excerpt();
        wp_reset_postdata();
        return ob_get_clean(); 
    }
}

$this->add_helper( 'excerpt', new Excerpt() );
