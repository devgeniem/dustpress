### Custom wp-activate.php

Upon user creation an email is sent to the user's email address containing an activation link. Opening this link leads to /wp-activate.php page where user can activate their account.

The view is printed out from wp-activate.php and cant' be modified easily. But in DustPress we do this for you. In the core we stop the wp-activate.php execution and run our own model and view instead.

These can be found in `models/user-activate.php` and `partials/user-activate.dust`. They mimic the original file but you can easily override the partial or even the model in your theme. 

Be careful when editing the user-activate.php that nothing breaks! 

If you want to extend the original without overriding it, you can specify the model with this filter. Then you can just extend the original UserActivate class.
```
add_filter(‘dustpress/template/useractivate’, function( $template ) { return ‘CustomUserActivate’; } );
```

### Files
```
models/user-activate.php
partials/user-activate.dust
```

### Model 
The default user activate model has two functions `Print` and `State`. 
#### State
State method runs through the same code found in wp-activate. It collects all strings that need to be outputted and passes them to the Print function. Also it sets an arbitrary state based on the code. This state is returned to the view.

State comes in handy when modifying the view. For example we check for `no-key` state to define if the didn't contain an activation key and show a form to fill that if that is the case.
```
{@eq key=State value="no-key" }`
    ... form code ..
{/eq}

```

#### States
 - **no-key** 
    - No activation key is set. Normally outputs form for user to give the key.
 - **site-active-mail**
    - Site has been activated and mail sent to user.
 - **account-active-mail**
    - Account has been activated and mail sent to user.
 - **account-active-no-mail**
    - Account has been activated but no mail is sent. Normally outputs username and password.
 - **error**                    
    - Error occurred during activation. Sets error message to print['error'].

#### Print

Print returns to the view all the translated strings that wp-activate.php would output. They can be used in the partial with `{Print.string_name}`. 

#### Strings
- **title** 
    - Site header
- **wp-activate-link** 
    - Activation link
- **message** 
    - Translated message
- **error** 
    - Possible error
- **username** 
    - User's loginname
- **password** 
    - Translated string of "Your chosen password"

### Multisite
This is also tested in multisite. Actually the idea to create this arose from problems with wp-activate.php on multisite.
