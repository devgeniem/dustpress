# DustPress
Contributors: devgeniem
Tags: dustpress, wordpress, dustjs, dust.js
Requires at least: 4.2.0
Tested up to: 4.4.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

## Description

A WordPress theme framework for writing template files with Dust.js templating engine and separate data models.

## Installation

### Composer
Install with composer by running:

```
$ composer require devgeniem/dustpress
```

OR add it into your `composer.json`:

```json
{
  "require": {
    "devgeniem/dustpress": "*"
  }
}
```

### Manually
- Clone DustPress into you WordPress project into a directory which is not publicly accessible.

### Activate dustpress
You have two options for starting a DustPress project:
1. Copy exampletheme from DustPress folder into your themes folder as a base for your own theme. You can of course rename it however you want.
2.  Include the DustPress core file `dustpress.php` in your `functions.php` using [include](http://php.net/manual/en/function.include.php) for example.

That's it! You are ready to go. DustPress will autoload all the resources needed for you to start developing.

## Usage

The basics of using DustPress are very simple. Unlike traditional WordPress theme development, DustPress relies on MVVM, or Model View ViewModel architecture in which fetching data and displaying it to the user are separated into different modules.

## File naming and locations

### Data models

Even though implementing an almost completely new development pattern to WordPress theme developers, DustPress still uses some of the WordPress core functions. The naming of the data models and view partials follow the naming conventions of traditional WordPress themes. The model for a single post should be named `single.php` etc.

In WordPress, your custom page templates could be named pretty much anything as long as you declare the name of the template in the comment section in the beginning of the file. This is the case in DustPress too, but the class name that you write for the model should follow a certain pattern. For example if you have a `Frontpage` template with a filename `page-frontpage.php`, your class should be named PageFrontpage. The class names are case sensitive. The same goes with custom content type singles, where a single `person` file should be named `single-person.php` and the class accordingly `SinglePerson`.

You still have to declare a name for the templates in the starting comment as you would have done in a traditional WordPress theme as well. This allows user to choose the template file to use with the page and points the DustPress core
to load the correct model when loading the page.

The models must be located in the `models/` directory. They could, however, be arranged in any kind of subdirectory tree, so feel free to keep them in whatever structure you like. Note that WordPress also needs to find your template file in order it to work.

### Views

The Dust templates are the views of our design pattern. DustPress uses a fork of [DustPHP](https://github.com/devgeniem/dust-php) library for parsing the Dust templates.

All the data gathered and returned by the public functions of your models are automatically passed to the view. DustPress looks for the Dust templates in the `partials/` directory under the root of the theme. Like models, they could be arranged in any kind of subdirectory hierarchy, so feel free to use whatever suits your needs.

By default the Dust templatefiles follow the naming of the models. `single.php` should be paired with `single.dust`. This naming convention can be overwritten in your model by calling the `set_template()` function. In any of the public functions of the model write `$this->set_template("partial_name")` and it will be used instead of the default template. The `.dust` file extension is not needed.

## Data models

The data models of DustPress consist of a class named the same as the file but in CamelCase instead of hyphens. `page-frontpage.php` should have a class named `PageFrontpage` that extends the `DustPressModel` class:

```
<?php
/*
Template name: Frontpage
*/

class PageFrontpage extends DustPressModel {
  //
}
?>
```

### Autoconstructing and modular usage

As described above DustPress automatically locates your main model following the WordPress theme naming conventions and structure. The main model is loaded and constructed automatically. Lots of good stuff happen behind the scenes in the `__construct` method of the `DustPressModel` base class. _Do not overwrite it without calling `parent::__construct();` in the beginning of your own constructor._

Alongside the autoloading you can use DustPress models in any modular use case you can come up with. One example would be building a custom API in a file called `api.php` containing a `DustPressModel` extending class called `API` _(no need to follow the naming convention since the class is not autoconstructed)_ running all kinds of public functions rendering multiple custom templates. Yes, with DustPress you can do Dust rendering anywhere within your WordPress project! [(see the example)]() [(power up your API with DustPressJS)]()

### Binding the data

DustPress has its own global data object that is passed to the view when everything is done and it's time to render the page. Binding data to the object is done via the `return` statements in publicly accessible functions. While autoloading the main model and its submodels, all the public functions will automatically be run. If you have data you want to load inside a function and do not want to include it into the global data object, set the visibility of a function to `private`or `protected`.

```
public function last_posts() {
    $args = [ 'posts_per_page' => 3 ];
    return get_posts( $args );
}
```

DustPress data object is an object named after the class it is defined in. Inside the object is an array, that holds a variety of objects that are user defined models. For example, if you have frontpage with a header,
a content block, a sidebar and a footer, the data object would look like this:

```
object(stdClass)#1 (1) {
  ["PageFrontpage"]=>
  array(4) {
    ["Header"]=>
    object(stdClass)#2 (0) {
    }
    ["Content"]=>
    object(stdClass)#2 (0) {
    }
    ["Sidebar"]=>
    object(stdClass)#2 (0) {
    }
    ["Footer"]=>
    object(stdClass)#2 (0) {
    }
  }
}
```

Note that the `Content` block is included by default inside the data block of all models. This is for template scoping purposes.

#### Submodels

Recurring elements like headers or footers should be created as submodels that can be included in any page. Submodels have their own models which are located in their own files inside the `models/` directory. They are attached to the main model with the aforementioned `bind_sub()` method. The frontpage model could look like this:

```
<?php
/*
Template name: PageFrontpage
*/

class PageFrontpage extends DustPressModel {

  public function init() {
    $this->bind_sub("Header");
    $this->bind_sub("Sidebar");
    $this->bind_sub("Footer");
  }
}
?>
```

This code fetches all three models and binds their data to the global data hierarchy under corresponding object. Notice that we have created a public function `init` which is automatically run by DustPress and therefore the submodels will be included. No `init` block will be created under the global data object since we do not return anything in our function.

Submodel bindings  can be run anywhere in the model for example inside an `if` statement. Submodels work recursively, hence submodels can bind more submodels.

`bind_sub()` can also take a second parameter, an array of arguments to be passed to the submodel. It is then accessible in the submodel globally by calling `get_args()`.

#### bind_data()

The actual passing of the data to inside the methods happens via `bind_data()` method. It takes the data as a
parameter and pushes it to the global data object under current model's branch of the tree. It goes under
`Content` object and in a container named after the method.

```
public function bind_SomeData() {
  $data = "This is data.";

  $this->bind_data($data);
}
```

If this code is located in our PageFrontpage class, the result's in the data object would be as follows:

```
object(stdClass)#1 (1) {
  ["PageFrontpage"]=>
  array(4) {
    ["Header"]=>
    object(stdClass)#2 (0) {
    }
    ["Content"]=>
    object(stdClass)#3 (1) {
      ["SomeData"]=>
      string(13) "This is data."
    }
    ["Sidebar"]=>
    object(stdClass)#2 (0) {
    }
    ["Footer"]=>
    object(stdClass)#2 (0) {
    }
  }
}
```

If you for some reason want to bind the data with another name than the method's name, you can pass the name to
the `bind_data()` method as the second parameter. This way you can also have data named 'Sub' or 'Data' which are
otherwise reserved names for plugin's methods.

You can also bind data straight to the root of the Content-object with `bind_content()` method. It doesn't create
a Content->Content structure but rather merges the data straight inside the Content block.

It's also possible to give the function a third parameter, that is the model name. So you can bind data to your Header submodel inside your main model or even another submodel! Because the rendering will be done after all data has been gathered, you have 100 % control of what data the view template gets and can even interfere with that after you have already defined a submodel.

#### Reserved model names

##### WP

WP is reserved for the essential WordPress data that is accessible in any template all the time. It is stored in
the root of the data object with the key `WP` and it contains all the fields that WordPress native `get_bloginfo()`
would return.

It also contains information about the current user in WP->user and a true/false boolean if the user is logged in
in WP->loggedin.

Contents of the `wp_head()` and `wp_footer()` functions are available for use in helpers {@wphead /} and {@wpfooter /} respectively. They should be inserted in the corresponding places in your template file.

```
{@wphead /}
```

## Dust templates

DustPHP templates are 100% compatible with Dust.js templates. See the official [Dust.js website](http://www.dustjs.com/) for documentation or the [LinkedIn Dust Tutorial](https://github.com/linkedin/dustjs/wiki/Dust-Tutorial).

All templates should start with a context block with the name of the current model, so that the variables are usable
in the template. As for our previous example model, very simplified template could look like this:

```
{#PageFrontpage}
  {">shared/header" /}

  {#Content}
    <h1>{WP.name}</h1>
    <h2>{WP.description}</h2>

    <p>{SomeString}</p>

    {SomeHTML|s}
  {/Content}

  {">shared/sidebar" /}

  {">shared/footer" /}
{/PageFrontpage}
```

This template includes header.dust, sidebar.dust and footer.dust templates from `partials/shared/` subdirectory. At the end of the `Content` block we echo HTML from the `SomeHTML` variable and use the `s` filter to get it _unescaped_.  Note the `PageFrontpage` data is accessible inside the `Content` scope. See the [Dust Tutorial](https://github.com/linkedin/dustjs/wiki/Dust-Tutorial#Sections_and_Context) for more information about sections and contexts.

## DustPress Helpers

Helpers extend the Dust.js templating language with more complex functionality than just data inserting (see: [Context Helpers](http://www.dustjs.com/guides/context-helpers/), [Dust Helpers](http://www.dustjs.com/guides/dust-helpers/)). With DustPress you can use all of the Dust.js Helpers within your Dust templates. We have also taken it a bit further and included some nice bits for you to use. As mentioned above there are helpers for echoing header and footer data into your templates but here is a complete list of helpers included with DustPress:

### Comments Helper

We made commenting super easy for your theme! Just use the Comments Helper with your desired parameters and DustPress builds an AJAX powered commenting for your posts and pages. Example usage:

_Bind the data in your model._

```
public function some_comments() {
  $data->form_args  = [
    'title_reply' => __( 'Add a comment', 'text-domain' ),
    'label_submit'  => __( 'Send', 'text-domain' ),
    'class_submit'  => 'button',
    'remove_input'  => array( 'url' ),
    'comment_notes_before' => false,
    'comment_notes_after' => false
  ];

    $after_comments = '<div class="comments__pagination-container"><ul class="pagination comments__pagination"></ul></div>';

  $data->comments_args  = [
    'reply'       => false,
    'after_comments'  => $after_comments,
  ];

  $data->section_title = __('Comments', 'text-domain');

  return $data;
}
```

_Use the helper in your template._
```
{#some_comments}
    {@comments form_args=form_args comments_args=comments_args section_title=section_title /}
{/some_comments}
```

### List will continue...

## Other functionality

### DoNotRender

If you do not want the DustPress to render the page automatically but would rather do it yourself, you can call
`$this->do_not_render()` anywhere in your model or submodels. In that case DustPress populates the data object, but leaves the
rendering for the developer.

DustPress render function is declared public and is thus usable anywhere. It takes an array of arguments as its parameter. Only mandatory argument is `partial` that contains the name, filename or path to the wanted partial.

With only the partial defined, DustPress passes its global data object to the template. That can be changed by giving it another parameter `data` that would then be passed to the template.

There is also a parameter `type` that define the format the data would be rendered in. By default it's `html`, but `json` is also a possibility. You can write your own render format functions as well. That feature will be documented later, sorry for that.

The last but not the least of the parameters is `echo` that takes a boolean value. By default it's set to true, so the render function echoes the output straight to browser. If it's false, it's returned as a string. Here is an example usage of the render function:

_in some function_
```
$output = dustpress()->render( [
  "partial"   => 'my_custom_template',
  "data"    => [
      'some_number' => 1,
      'some_string' => 'ABC',
  ],
  "type"    => "html",
  "echo"    => false
]);
echo $output;
```

_my_custom_template.dust_
```
<ul>
    <li>My number: {some_number}</li>
    <li>My string: {some_string}</li>
</ul>
```
_the echoed output_
```
<ul>
    <li>My number: 1</li>
    <li>My string: ABC</li>
<ul>
```

# Additional Classes

## DustPressHelper

DustPressHelper is a class which combines helper functions for common WordPress tasks. The main functionality of this class is customized post quering with the ability to bind basic WordPress metadata to the queried post objects. With a single function call you can get the meta needed in your Dust-template. It also supports customized data fetching for __Advanced Custom Fields__ field group data in your post objects.

In this class you will also find a menu builder tailored for Dust-based menus which is described later in detail.

### Quering single posts

#### get_post()

With DustPressHelper you can query single WordPress posts with two different functions. The `get_post()` function accepts the following parameters:
* id: The id of the post.
* args: Arguments in an array.

The argument key `'meta_keys'` accepts meta key values in an array as strings. Passing a string instead with the value `'all'` will fetch all the meta fields in an associative array. The additional argument keys are `'single'` and `'meta_type'` with the same functionality as described in WordPress documentation for `get_metadata()`. Found meta data is appended under the queried post object array with the key `meta`. If no matching post with the passed id is found, `false`is returned.

#### get_acf_post()

This function extends the `get_post()` function with automatic loading of __Advanced Custom Fields__ (ACF) field group data. Fields are loaded with the ACF function `get_fields` and are returned into the the post object under the key `fields`. This function accepts the same arguments as the `get_post() function and also the argument key `whole_fields`. With this argument set to `true` this function returns the field group data as seen in the field group edit screen.

This function has a recursive operation. If the argument with the key `recursive` is set to `true`, ACF fields with relational post object data are loaded recursively with full meta and field group data. This recursion also works within the first level of an ACF repeater field.

### Quering multiple posts

#### get_posts()

This function will query multiple posts based on given arguments with the option to get post metadata binded with the post objects. Post objects are queried with the WordPress `get_post` function and the data is extended with metadata. Thus, this function accepts the same arguments as the basic `get_post` function. If found, posts are returned as an associative array. If no matching posts are found, `false`is returned. This function accepts arguments in an array with the following keys:
* all the arguments described in the WordPress codex for the `get_posts`function: https://codex.wordpress.org/Function_Reference/get_posts
* meta_keys: Function described in the `get_post()` function. Found meta values are returned automatically for all posts if this argument is set.
* meta_type: Function described in the `get_post()`function

#### get_acf_posts()

This function extends the get_posts function with the ability to load __Advanced Custom Fields__ (ACF) field group data with the post objects. Accepts the same arguments as the `get_posts` function with the addition of the key `whole_fields` which functions similarly as described in the `get_acf_post` function. This function does not have a recursive functionality. ACF fields with relational post object data need to be loaded separately.

# Debugger

DustPress comes with a debugger which displays the data loaded by your current model in a json viewer. To enable the debugger go to WordPress dashboard. Under the Settings there are DustPress' settings. Tick the box 'Show debug information' and save changes.

In the debugger view you can:
* open and close data sets recursively by holding down the 'Shift' key while clicking an item.
* close the debugger by pressing the 'Esc' key.

Get the debugger from [Geniem GitHub](https://github.com/devgeniem/dustpress-debugger) or install it with Composer:
```
composer require devgeniem/dustpress-debugger
```
