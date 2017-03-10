### Image

The `image` helper returns a markup for `img` tags with proper `srcset` and `sizes` attributes for responsive use.

You can either give the helper the ID of an image uploaded to WordPress or alternatively use a completely custom URL as the source.

#### An image from WP

When using the helper with an `id`, you also have to provide a registered WP image size as the `size` parameter. Additional parameters are not needed but can be given optionally. The helper fetches the image url from the database and constructs the srcset from the specified WP image sizes.

``` {@image id=image.id size="large" /} ```

By default it constructs the same `sizes` attribute for the image as WordPress would. You can also give a custom `sizes` parameter when using the helper with a WP image ID, so that the default WP sizes attribute construction is overriden.

``` {@image id=image.id size="my_custom_wp_image_size" sizes=Settings.mainImage.sizes /} ```

#### An image from a custom src

When using the helper with a custom src url, you also have to provide the `srcset` and the `sizes` so that the helper is able to produce the responsive HTML markup.

``` {@image src=Settings.mainImage.src srcset=Settings.mainImage.srcset sizes=Settings.mainImage.sizes /} ```

#### The class and alt parameters

With any use case of the helper, you can always optionally provide the `class` and `alt` parameters and they will be added to the returned markup.

If you don't provide the alt parameter when using the helper with an image id, it tries to use the alt attribute given to the image when it was uploaded to WordPress. This attribute might be empty in most cases though, so remember to check the final markup for accessibility purposes.

``` {@image id=image.id size="medium_large" class="myclass" alt="An alternative text" /} ```

#### A settings model

We recommend using a separate settings model in your DustPress installation for easily defining custom settings for images. This model can be bound to chosen models or even to the middle model which is extended by page and post models.

The custom image settings for different images in your web site layout can then be easily acquired for use in the templates.

```
<?php

class Settings extends \DustPress\Model {

    public function imageSettings() {
        return [
            'mainImage' => [
                'sizes'  => [
                    '(min-width: 1280px) 100vw',
                    '(min-width: 768px) 50vw',
                    '100vw',
                ],
                'src' => 'https://image.com/example.jpg',
                'srcset' => [
                    'https://image.com/example-300.jpg 300w',
                    'https://image.com/example-768.jpg 768w',
                    'https://image.com/example-1024.jpg 1024w',
                ],
            ],
        ];
    }
}
