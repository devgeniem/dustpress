<?php
namespace DustPress;

class Password extends Helper {
    public function init() {
        if ( isset( $this->params->id ) ) {
            $id = $this->params->id;
        }
        else {
            $id = get_the_ID();
        }

        $post = get_post( $id );

        // If a password is not needed, render the content block as is
        if ( empty( $post->post_password ) || ! post_password_required( $id ) ) {
            return $this->chunk->render( $this->bodies->block, $this->context );
        }
        else {
            if ( post_password_required( $id ) ) {
                // Populate data object to be passed to the password form template
                $data = new \stdClass();
                $data->url = esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) );
                $data->label = 'pwbox-'. $id;
                return $this->chunk->write( dustpress()->render([
                    'partial' => 'password_form',
                    'data'    => $data,
                    'echo'    => false
                ]) );
            }
        }
    }
}

$this->dust->helpers['password'] = new Password();