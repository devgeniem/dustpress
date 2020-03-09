<?php
/**
 * The Image helper
 */

namespace DustPress;

/**
 * This helper provides a functionality to print
 * an image element with its srcset. The helper can either get
 * the image URLs from WP with a given ID or print the element
 * with custom source URLs.
 */
class Image extends Helper {

    /**
     * Outputs the image markup or an error
     *
     * @return string The HTML markup or an error message.
     */
    public function output() {

        // Store the parameters.
        $image_data = $this->get_image_data( $this->params );

        // If blog_id we are working on the network.
        if ( ! empty( $image_data['blog_id'] ) ) {

            switch_to_blog( (int) $image_data['blog_id'] );

            $image_output = $this->get_image_output( $image_data );
            restore_current_blog();
        }
        else {
            $image_output = $this->get_image_output( $image_data );
        }

        return $image_output;
    }

    /**
     * Get image output.
     *
     * @param  array $image_data Get image output.
     *
     * @return mixed Image output data, error or empty value on failure.
     */
    private function get_image_output( $image_data ) {
        // ID given
        if ( null !== $image_data['id'] ) {

            // SRC also given.
            if ( null !== $image_data['src'] ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
                    return '<p><strong>Dustpress image helper error:</strong>
                        <em>Image id and custom src both given.
                        Only one of these parameters can be used.</em></p>';
                }
                return;

            } else { // Only the ID given as the original image source.

                if ( null === $image_data['size'] ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
                        return '<p><strong>Dustpress image helper error:</strong>
                                <em>No image size attribute given. When
                                using the ID, you have to give a registered size name to
                                the helper. </em></p>';
                    }
                    return;

                } else { // ID and size given.

                    // No custom responsive parameters given
                    if ( null === $image_data['srcset'] && null === $image_data['sizes'] ) {

                        // Return the WordPress default img-tag
                        // from the full-sized image with a source set.
                        $the_image_markup = wp_get_attachment_image(
                            $image_data['id'],
                            $image_data['size'],
                            false,
                            $image_data['attrs']
                        );

                        if ( $the_image_markup ) {

                            return $the_image_markup;

                        } else {
                            if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
                                return '<p><strong>Dustpress image helper error:</strong>
                                        <em>No image found from the database with the given id.</em></p>';
                            }
                            return;
                        }
                    } else { // Custom responsive parameters are given.

                        // SRCSET exists but no SIZES attribute is given.
                        if ( null === $image_data['sizes'] ) {
                            if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
                                return '<p><strong>Dustpress image helper error:</strong>
                                        <em>Srcset exists but no sizes attribute is given.</em></p>';
                                }
                            return;

                        } else { // Both custom responsive parameters and the id is given.
                            return $this->get_image_markup( $image_data );
                        }
                    }
                }
            }
        } else { // No image ID given

            // No SRC given either.
            if ( null === $image_data['src'] ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
                    return '<p><strong>Dustpress image helper error:</strong>
                            <em>Image id or custom src not given.
                            The helper needs at least one of these parameters.</em></p>';
                }
                return;

            } else { // Only the SRC given as the original image source.

                // When using the custom SRC, both SRCSET and SIZES need to be given.
                if ( null === $image_data['srcset'] ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
                        return '<p><strong>Dustpress image helper error:</strong>
                                <em>Srcset not given. Both the srcset and the sizes are
                                needed when using a custom src.</em></p>';
                    }
                    return;
                }

                // When using the custom SRC, both SRCSET and SIZES need to be given.
                if ( null === $image_data['sizes'] ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
                        return '<p><strong>Dustpress image helper error:</strong>
                                <em>Sizes not given. Both the srcset and the sizes are
                                needed when using a custom src.</em></p>';
                    }
                    return;

                }

                return $this->get_image_markup( $image_data );
            }
        }
    }

    /**
     * Gets and formats the data from the parameters given
     * to the image helper tag.
     *
     * @param  array $params     The array object.
     *
     * @return array $image_data The formatted array.
     */
    private function get_image_data( $params ) {

        // Init the settings array and the img attrs array.
        $image_data = [
            'id'      => null,
            'src'     => null,
            'size'    => null,
            'srcset'  => null,
            'sizes'   => null,
            'attrs'   => [],
            'blog_id' => null,
        ];

        // Store the images ID if it is given.
        if ( isset( $params->id ) ) {
            $image_data['id'] = (int) $params->id;
        }

        // Add the src attribute to the data array if it is given.
        if ( isset( $params->src ) ) {
            $image_data['src'] = $params->src;
        }

        // Add the size attribute to the data array if it is given.
        if ( isset( $params->size ) ) {
            $image_data['size'] = $params->size;
        }

        // Add the srcset attribute to the data array if it is given.
        if ( isset( $params->srcset ) ) {
            $image_data['srcset'] = $params->srcset;
        }

        // Add the sizes attribute to the data array if it is given.
        if ( isset( $params->sizes ) ) {
            $image_data['sizes'] = $params->sizes;
        }

        // If a class string is given, store it to the meta params.
        if ( isset( $params->class ) ) {
            $image_data['attrs']['class'] = $params->class;
        }

        // If an alt string is given, store it to the meta params.
        if ( isset( $params->alt ) ) {
            $image_data['attrs']['alt'] = $params->alt;
        }

        // If an alt string is given, store it to the meta params.
        if ( isset( $params->blogid ) ) {
            $image_data['blog_id'] = $params->blogid;
        }

        $image_data = \apply_filters( 'dustpress/image/image_data', $image_data );

        return $image_data;
    }

    /**
     * Get the custom HTML srcset markup with the given settings
     *
     * @param  array $image_data The given srcset and sizes.
     *
     * @return string            The image markup.
     */
    private function get_image_markup( $image_data ) {

        // If the src attribute is given, use it as the original src.
        if ( null !== $image_data['src'] ) {

            $image_src_string = '<img src="' . $image_data['src'] . '"';

        } else { // Else get the images src from WP.

            // Get the image from WP.
            $image_src_array = wp_get_attachment_image_src( $image_data['id'],
            $image_data['size'] );

            // Construct the beginning markup of the image string if the image is found.
            if ( $image_src_array ) {

                $image_src = $image_src_array[0];
                $image_width = $image_src_array[1];
                $image_height = $image_src_array[2];
                $image_src_string = '<img width="' . $image_width .
                                    '" height="' . $image_height .
                                    '" src="' . $image_src . '"';

            } else { // Else return an error.
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
                    return '<p><strong>Dustpress image helper error:</strong>
                    <em>No image found from the database with the given id.</em></p>';
                }
                return;
            }
        }

        // Set the class string.
        $image_class_string = ( isset( $image_data['attrs']['class'] )
                                ? 'class="'. $image_data['attrs']['class'] .'"'
                                : ''
                            );

        // Set the alt string.
        $image_alt_string = ( isset( $image_data['attrs']['alt'] )
                                ? 'alt="'. $image_data['attrs']['alt'] .'"'
                                : ''
                            );

        // Set the sizes attribute string.
        $sizes = $image_data['sizes'];

        // Check that the srcset is given as an array.
        if ( ! is_array( $sizes ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
                return '<p><strong>Dustpress image helper error:</strong>
                        <em>Given sizes attribute is not an array.</em></p>';
                }
            return;

        }

        // Concatenate the given sizes to a comma separated list
        // and construct the sizes string.
        $image_sizes_string = 'sizes="' . implode( ', ', $sizes ) .'"';

        // Either use the srcset array that is given
        // or fetch the urls and widths using the WP sizes.
        $srcset_array = ( isset( $image_data['srcset'] )
                            ? $image_data['srcset']
                            : $this->get_wp_image_sizes_array( $image_data )
                        );

        // Check that the srcset is given as an array.
        if ( ! is_array( $srcset_array ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
                return '<p><strong>Dustpress image helper error:</strong>
                        <em>Given srcset attribute is not an array.</em></p>';
                }
            return;

        }

        // Construct the srcset string.
        $image_srcset_string = 'srcset="' . implode( ', ', $srcset_array ) .'"';

        // Close the img tag.
        $image_close_string = '>';

        // Concatenate all of the images strings together.
        $html = $image_src_string .
                $image_alt_string .
                $image_class_string .
                $image_srcset_string .
                $image_sizes_string .
                $image_close_string;

        $html = apply_filters( "dustpress/image/markup", $html );

        return $html;
    }

    /**
     * Get all the registered image sizes along with their dimensions
     *
     * @global array $_wp_additional_image_sizes
     * @param int $id The image ID.
     *
     * @return array $image_sizes The image sizes
     */
    private function get_wp_image_sizes_array( $image_data ) {

        // The registered image sizes.
        global $_wp_additional_image_sizes;

        // The default wordpress image sizes. Exclude the thumbnail size.
        $default_image_sizes = array( 'medium', 'medium_large', 'large' );

        // Loop through the sizes and get the corresponding options from the db.
        foreach ( $default_image_sizes as $size ) {

            $image_sizes[ $size ]['width'] = intval( get_option( "{$size}_size_w" ) );
            $image_sizes[ $size ]['height'] = intval( get_option( "{$size}_size_h" ) );
            $image_sizes[ $size ]['crop'] = ( get_option( "{$size}_crop" )
                                                ? get_option( "{$size}_crop" )
                                                : false
                                            );
        }

        // Add custom sizes to the array.
        if ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) ) {

            $image_sizes = array_merge( $image_sizes, $_wp_additional_image_sizes );

        }

        // The final array in which we have the properly formatted urls and widths.
        $srcset_array = [];

        // Loop through the sizes in the array and get the urls and widths from WP.
        foreach ( $image_sizes as $size => $size_options ) {

            $url   = wp_get_attachment_image_src( $image_data['id'], $size )[0];
            $width = $size_options['width'];
            $entry = $url . ' ' . $width . 'w';
            $srcset_array[] = $entry;

        }

        return $srcset_array;
    }
}
// Add the helper.
$this->add_helper( 'image', new Image() );
