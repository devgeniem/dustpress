# DustPress

A WordPress plugin for writing template files with Dust templating engine and separate data models.

# Installation

- Install DustPress plugin to your WordPress plugin folder as usual, and activate it from the admin panel.
- Copy exampletheme from plugin folder to your themes folder as a base for your own theme. You can of course rename it however you want.
- That's it! You are ready to go.

# Usage

The basics of using DustPress are very simple. Unlike traditional WordPress theme development, DustPress relies on MVVM,
or Model View ViewModel architecture, where fetching data and displaying it to the user are separated in different modules.

## File naming and locations

### Data models

Even though implementing almost completely new development pattern to WordPress theme developers, DustPress still uses
some of the WordPress core functions. The naming of the data models and view partials follow the naming conventions of
traditional WordPress themes. The model for a single post should be named `single.php` etc.

In WordPress, your custom page templates could be named pretty much anything as long as you declare the name of the
template in the comment section in the beginning of the file. This is the case in DustPress too, but the class name that you write for the model should follow certain pattern. For example if you have a `Frontpage` template with a filename `page-frontpage.php`, your class should be named PageFrontpage. The class names are case sensitive. The same goes with custom content type singles, where a single `person` file should be named `single-person.php` and the class accordingly `SinglePerson`.

You still have to declare a name for the templates in the starting comment as you would have done in a traditional
WordPress theme as well. This allows user to choose the template file to use with the page and points the controller
to load correct model when loading the page.

The models are located in the models/ directory, so that DustPress knows where to find them. They could, however, be arranged in any kind of subdirectory tree, so feel free to keep them in whatever structure you like.

### Dust templates

The Dust templates are the view part of the design pattern. They get data from the DustPress controller (gathered in
the model) and create the html from that.

DustPress looks for the Dust templates in partials/ directory under the theme's root. Like models, they could be
arranged in any kind of subdirectory hierarchy, so feel free to use whatever suits your needs.

By default the Dust templatefiles follow the naming of the models. `single.php` should be paired with `single.dust`.
That can be changed by the developer, of course. If for some unimaginable reason you would like to use `page-frontpage.dust` template with your `single.php` model, you could make use of DustPress' `set_partial()` function.

Just write anywhere in the model `$this->set_partial("partial_name")` and it will be used instead of the default template.

## Data models

The data models of DustPress consist of one class named the same as the file but in CamelCase instead of hyphens.
`page-frontpage.php` should have a class named PageFrontpage that extends the DustPress class:

```
<?php
/*
Template name: Frontpage
*/

class PageFrontpage extends DustPress {
  //
}
?>
```

### Method naming

As you probably know, classes consist of variables and methods. In DustPress, there are _no_ mandatory variables
or methods the models should have. You can design them completely as you wish. There are however some methods
you probably want in your model.

If you write a method that has a name starting with the word `bind`, DustPress executes them automatically when
the data is being gathered. All other functions are considered as helper functions and should be executed in the
code.

Within the `bind` methods there are two special cases, `bind_Content()` and `bind_sub()`. We'll explain them later
in detail. `bind_data()` is also a reserved name that can't be used as a method.

### Binding the data

DustPress has its own global data object that is passed to the view when everything is done and it's time to
render the page. Binding data to the object is done via the `bind` methods.

DustPress data object is an object named after the class it is defined in. Inside the object is an array, that
holds a variety of objects that are user defined models. For example, if you have frontpage with a header,
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

#### Submodels

Recurring elements like headers or footers should be created as submodels that can be included in any page.
Submodels have their own models and are located in their own files inside the models/ directory. They are
attached to the main model with the aforementioned `bind_sub()` method. The frontpage model could look like
this:

```
<?php
/*
Template name: PageFrontpage
*/

class PageFrontpage extends DustPress {

  public function bind_Submodels() {
    $this->bind_sub("Header");
    $this->bind_sub("Sidebar");
    $this->bind_sub("Footer");
  }
}
?>
```

This code fetches all three models and binds their data to the global data hierarchy under corresponding
object. `bind_Submodels` is just an example name, the `bind_sub` function calls can be anywhere in the model
and run inside an if block etc if needed.

`bind_sub()` can also take a second parameter, an array of arguments to pass to the submodel. It is then
accessible in the submodel globally by calling `get_args()`.

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

There is two restrictions in the model naming in addition to all the reserved class names by the WordPress itself.

##### WP

WP is reserved for the essential WordPress data that is accessible in any template all the time. It is stored in
the root of the data object with the key `WP` and it contains all the fields that WordPress native `get_bloginfo()`
would return.

It also contains information about the current user in WP->user and a true/false boolean if the user is logged in
in WP->loggedin.

`wp_head()` and `wp_footer()` functions' contents are available for use in helpers {@wphead /} and {@wpfooter /} respectively. They should be inserted in the corresponding places in your template file.

```
{@wphead /}
```

## Dust templates

Dust templates are 100% compatible with Dust.js templates. DustPress is based on Dust-PHP library.

All templates should start with a context block with the name of the current model, so that the variables are usable
in the template. As for our previous example model, very simplified template could look like this:

```
{#PageFrontpage}
  {">shared/header" /}

  {#Content}
    <h1>{WP.name}</h1>
    <h2>{WP.description}</h2>

    <p>{SomeData}</p>
  {/Content}

  {">shared/sidebar" /}

  {">shared/footer" /}
{/PageFrontpage}
```

This template includes header.dust, sidebar.dust and footer.dust templates from partials/shared/ subdirectory.

## Other functionality

### DoNotRender

If you do not want the DustPress to render the page automatically but would rather do it yourself, you can call
`$this->do_not_render()` anywhere in your model or submodels. In that case DustPress populates the data object, but leaves the
rendering for the developer.

DustPress render function is declared public and is thus usable anywhere. It takes an array of arguments as its parameter. Only mandatory argument is `partial` that contains the name, filename or path to the wanted partial.

With only the partial defined, DustPress passes its global data object to the template. That can be changed by giving it another parameter `data` that would then be passed to the template.

There is also a parameter `type` that define the format the data would be rendered in. By default it's `html`, but `json` is also a possibility. You can write your own render format functions as well. That feature will be documented later, sorry for that.

The last but not the least of the parameters is `echo` that takes a boolean value. By default it's set to true, so the render function echos it's output straight to browser. If it's false, it's returned as a string.