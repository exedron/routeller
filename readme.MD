# \Exedron\Routeller
A Minimal annotation and reflection based anemic routeful controller for Exedra.

In a simple word, a routeable-action-controller component in steroid.

## Introduction
Have you ever feel, about how ambigous our http controller usually is, especially on designing a restful api webservice. The http controller as we 
know today, is often nothing more than a bag of methods/actions with ambiguous behaviour, which rarely say something about the domain problems, 
except by the controller class name, and what it inheritently contains, aside from being mapped independantly from the routing component. And I find it tedious to constantly need to work on both the route mapping, and the controller codes.

**The ambiguity of responsibility**. This is what this package is about to tackle, 
by totally removing the controller instantiability, flatten the controller design, 
and make it as anemic as possible. In a simple word, the controller is incapable of construction, 
but knows very well about the routing design.

The annotation design is fairly simple, just a @property-value mapping. Nothing much!

## Requirement
This package works only for Exedra. It cannot be used independantly, because.. Of course, 
it's part of Exedra routing component.

## Installation and usage
### Installation
Install through composer
```
composer require exedron\routeller dev-master
```
#### Add via service provider
Setup the service provider
```php
// your application instance
$app->provider->add(\Exedron\Routeller\Provider::class);
```

#### Add via routing factory
Alternatively, you may manually add the handler, and set up additional things.
```php
$app->routingFactory->addGroupHandler(new \Exedron\Routeller\Handler);

$app->map->addExecuteHandler('routeller_execute', ExecuteHandler::class);
```

#### Enable caching
Enable a file based cache
```php
$options = array(
    'auto_reload' => true
);

$cache = new \Exedron\Routeller\Cache\FileCache(__DIR__ .'/routing_caches')

$app->provider->add(new \Exedron\Routeller\Provider($cache, $options));
```
The ```auto_reload``` option lets it reload the cache each time there's some change to the controllers.

### Usage
On your preferred route, add use the controller using the *group()* method.
```php
$app->map['web']->group(\App\Controller\Web::class);
```
The controller **MUST** by type of *\Exedron\Routeller\Controller\Controller*.
```php
<?php
namespace App\Controller;

use Exedron\Routeller\Controller\Controller;

class Web extends Controller {

}
```
### Adding executable routes
Annotate the method with the route properties. The method name has to be prefixed with *execute*.
```php
<?php
namespace App\Controller;

use Exedra\Runtime\Exe;

class Web extends \Exedron\Routeller\Controller\Controller
{
    /**
     * @path /
     */
    public function executeIndex(Context $context)
    {
        return 'This is index page';
    }
    
    /**
     * @name about-us
     * @path /about-us
     * @method GET|POST
     */
    public function executeAboutUs(Context $context)
    {
        return 'This is about page';
    }
}
```
Doing above is similar to doing :
```php
use Exedra\Routing\Router;
use Exedra\Runtime\Exe;

$app->map['web']->group(function(Router $router) {
    $router['index']->any('/')->execute(function(Context $context) {
        return 'This is index page';
    });
    
    $router['about-us']->any('/about-us')->execute(function(Context $context) {
        return 'This is about page';
    });
});
```

### Middleware
Add a middleware for the current group's route, by prefixing the method name with *middleware*.
 This method expects no annotation.
```php
public function middlewareAuth(Context $context)
{
    if(!$context->session->has('user_id'))
        return $context->redirect->route('@web.login');

    return $context->next($context);
}
```
The middleware name is optional though, so, you can still set it.
```
/**
 * @name csrf
 */
public function middlewareCsrf()
{
    return $context->next($context);
}
```

### Subrouting / Nest-Routing
Add a subgroup route. The method name must be prefixed with *group*.
The method must return the routing group pattern.
```php
/**
 * @path /products
 */
public function groupProducts()
{
    return \App\Controller\Products::class;
}
```

#### Pre-assigned middleware
Assign a middleware on this routing group defining level
```php
/**
 * @path /admin
 */
public function groupAdmin()
{
    return \App\Controller\Admin::instance()->addMiddleware(function(Context $context) {
        if(!$context->session->has('user-id'))
            throw new \Exception('User is not logged in');
    
        return $context->next($context);
    });
}
```

### Normal routing
You can also do a usual routing by prefixing the method name with *setUp*. This method expects no annotation.
```php
public function setUp(Group $router)
{
    $router->get('/')->execute(function() {
    });
}

public function setUpCustom(Group $router)
{
    // do another thing
}
```


## Restful verbs
This package also support a simple restful mapping.

#### GET, POST, PUT, PATCH, DELETE verb
Prefix each method with the http verb as you like.
```php
/**
 * Get all products
 * @path /
 */
public function getProducts(Context $context)
{
}
```
```php
/**
 * Create a new product
 * @path /
 */
pubic function postProducts(Context $context)
{
}
```
```php
/**
 * GET the product
 * @path /[:id]
 */
pubic function getProduct(Context $context)
{
}
```
```php
/**
 * DELETE the product
 * @path /[:id]
 */
pubic function getProduct(Context $context)
{
}
```

#### Verb only method name
And you can have a route with only the verb.
```php
public function get(Context $context)
{
}

public function post(Context $context)
{
}
```
Of course, this is just a sample. Best way to do a resourceful design in Exedra, is through a subrouting.

#### Dependency injection
Inject with known service(s)
```php
use Exedra\Url\UrlGenerator;

/**
 * @inject context.url
 * @path /
 */
public function get(UrlGenerator $url)
{
    echo $url->current();
}
```
Multiple services
```php
use Exedra\Runtime\Context;
use Exedra\Application;
use Exedra\Routing\Group;

/**
 * @inject context, url, self.response, app, app.map
 * @path /
 */
 public function post(Context $context, $url, $response, Application $app, Group $map)
 {
 }
```
##### Notes
- ```self``` and ```context``` is the same thing that is a type of \Exedra\Runtime\Context, the context of the current runtime.
- the services prefixed with ```app.``` will instead look inside the ```Exedra\Application``` container 
- without a prefix, ```context.```, ```self.``` or ```app.```, the resolve will only look for the service registered the Context object.

## Other route properties
#### Tagging and ajax
```
/**
 * @tag users
 * @ajax true
 * @middleware \App\Middleware\Auth
 */
pubic function executeUsers()
{
}
```
#### Attributes (and sample middleware)
```
public function middlewareAuth(\Exedra\Runtime\Context $context)
{
    if($context->attr('need_auth', false) && !$context->session->has('user_id'))
        throw new NotLoggedInException;
        
    return $context->next($context);
}

/**
 * @attr.need_auth true
 * @path /admin
 * @method any
 */
public function groupAdmin()
{
    return Admin::class;
}
```

#### All possible properties
```
/**
 * @name admin_default
 * @method GET|POST
 * @path /admin/:controller/:action
 * @ajax false
 * @middleware \App\Middleware\Auth
 * @middleware \App\Middleware\Csrf
 * @middleware \App\Middleware\Limiter
 * @tag admin_default
 * @attr.session_timeout 36000
 * @config.request_limit 15
 * 
public function executeAdminDefault($context)
{
    // nah, just a sample.
    $controller = $context->param('controller');
    $action = $context->param('action');
    
    return (new $controller)->{$action}($context);
}
```

## Exceptions
The non route property tags like **return**, **param**, and **throws** tags and will not be parsed.

## Notes
### Routing name
For some type of usage, like **executable** and **grouped** kind of route, the route name will be taken from the
 case-lowered remaining method name, IF no **@name** property is annotated.
 
##### Route name for the restful controller
It takes a combination of verb and the method name. For example,
```php
public function postProducts()
{
}

public function get()
{
}
```
Above routing will have a method name like **.post-products**. (**@web.products.post-products**)

For verb only method name, it'll just be the verb as the name. And an absolute name for it would look something like :
**@web.products.get**


### Ordering
Routing order is being read from top to above. So, it matters how you code the routing.