<?php

/**
 *  Replaces wp-activate.php and offers user way to make custom partial for it.
 */
class UserActivate extends \DustPress\Model {

    private $state;
    private $print;
    private $signup;

    /**
     * Returns the state of the page and sets the right strings for printing.
     * States:
     *     no-key                   No activation key is set. Normally outputs form for user to give the key.
     *     site-active-mail         Site has been activated and mail sent to user.
     *     account-active-mail      Account has been activated and mail sent to user.
     *     account-active-no-mail   Account has been activated but no mail is sent. Normally outputs username and password.
     *     error                    Error occurred during activation. Sets error message to print['error'].
     *
     * @param   N /A
     *
     * @return  $state (string)    State of the view.
     */
    public function State() {
        $valid_error_codes = [ 'already_active', 'blog_taken' ];

        // Get the key from cookie if set
        $activate_cookie = 'wp-activate-' . COOKIEHASH;

        $key = '';

        if ( isset( $_COOKIE[ $activate_cookie ] ) ) {
            $key = $_COOKIE[ $activate_cookie ];
        }

        if ( ! $key ) {
            // activation key required
            $state                           = "no-key";
            $this->print['title']            = apply_filters( 'dustpress/activate/key_required', __( 'Activation Key Required' ) );
            $this->print['wp-activate-link'] = network_site_url( 'wp-activate.php' );
        }
        else {
            $result = wpmu_activate_signup( $key );

            if ( is_wp_error( $result ) && in_array( $result->get_error_code(), $valid_error_codes ) ) {
                $signup = $result->get_error_data();

                $this->signup = $signup;
                $this->print['title'] = apply_filters( 'dustpress/activate/account_active',  __( 'Your account is now active!' ) );

                if ( $signup->domain . $signup->path == '' ) {
                    // account active and email sent
                    $state = "account-active-mail";
                    $this->print['message'] = apply_filters(
                        'dustpress/activate/user_activated',
                        sprintf( /* translators: 1: login URL, 2: username, 3: user email, 4: lost password URL */
                            __( 'Your account has been activated. You may now <a href="%1$s">log in</a> to the site using your chosen username of &#8220;%2$s&#8221;. Please check your email inbox at %3$s for your password and login instructions. If you do not receive an email, please check your junk or spam folder. If you still do not receive an email within an hour, you can <a href="%4$s">reset your password</a>.' ),
                            network_site_url( 'wp-login.php', 'login' ),
                            $signup->user_login,
                            $signup->user_email,
                            wp_lostpassword_url()
                        ),
                        $signup->user_login,
                        $signup->user_email
                    );
                }
                else {
                    // site active and email sent
                    $state = "site-active-mail";
                    /* translators: 1: site URL, 2: site domain, 3: username, 4: user email, 5: lost password URL */
                    $this->print['message'] = apply_filters(
                        'dustpress/activate/site_activated',
                        sprintf( /* translators: 1: site URL, 2: site domain, 3: username, 4: user email, 5: lost password URL */
                            __( 'Your site at <a href="%1$s">%2$s</a> is active. You may now log in to your site using your chosen username of &#8220;%3$s&#8221;. Please check your email inbox at %4$s for your password and login instructions. If you do not receive an email, please check your junk or spam folder. If you still do not receive an email within an hour, you can <a href="%5$s">reset your password</a>.' ),
                            'http://' . $signup->domain,
                            $signup->domain,
                            $signup->user_login,
                            $signup->user_email,
                            wp_lostpassword_url()
                        ),
                        $signup->domain,
                        $signup->user_login,
                        $signup->user_email
                    );
                }
            }
            elseif ( $result === null || is_wp_error( $result ) ) {
                $state                  = "error";

                $this->print['title'] = apply_filters( 'dustpress/activate/error_occurred', __( 'An error occurred during the activation' ) );
                $this->print['error'] = $result->get_error_message();
            }
            else {
                $state = "account-active-no-mail";

                $url  = isset( $result['blog_id'] ) ? get_home_url( (int) $result['blog_id'] ) : '';
                $user = get_userdata( (int) $result['user_id'] );

                $this->print['title']     = apply_filters( 'dustpress/activate/account_active',  __( 'Your account is now active!' ) );
                $this->print['username']  = $user->user_login;
                $this->print['useremail'] = $user->user_email;
                $this->print['password']  = $result['password'];

                if ( $url && $url != network_home_url( '', 'http' ) ) {
                    switch_to_blog( (int) $result['blog_id'] );
                    $login_url = wp_login_url();
                    restore_current_blog();
                    // log in link to blog
                    $this->print['message'] = apply_filters(
                        'dustpress/activate/account_activated',
                        sprintf( /* translators: 1: site URL, 2: login URL */
                            __( 'Your account is now activated. <a href="%1$s">View your site</a> or <a href="%2$s">Log in</a>' ),
                            $url,
                            esc_url( $login_url )
                        ),
                        $url,
                        $login_url,
                        $user,
                        $result['blog_id']
                    );
                }
                else {
                    //log in link to main site
                    $this->print['message'] = apply_filters(
                        'dustpress/activate/account_activated',
                        sprintf( /* translators: 1: login URL, 2: network home URL */
                            __( 'Your account is now activated. <a href="%1$s">Log in</a> or go back to the <a href="%2$s">homepage</a>.' ),
                            network_site_url( 'wp-login.php', 'login' ),
                            network_home_url()
                        ),
                        $user
                    );
                }
            }
        }
        $this->state = $state;

        return $state;
    }

    /**
     * Returns strings for printing.
     *
     * Available strings:
     *  title - Site header
     *  wp-activate-link - Activation link
     *  message - Translated message.
     *  error - Possible error
     *  username - User's loginname
     *  password - Translated string of "Your chosen password".
     *
     *  @return  $this->print (string) Messages for the view.
     */
    public function Print() {
        return $this->print;
    }

}
