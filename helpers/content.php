<?php
namespace DustPress;

class Content extends Helper {
    public function output() {
        global $post;

        if ( isset( $this->params->data ) ) {
            return apply_filters( 'the_content', $this->params->data );
        } else {
            ob_start();
            setup_postdata( $post );
            the_content();
            wp_reset_postdata();
            return ob_get_clean();
        }
    }
}

$this->add_helper( 'content', new Content() );