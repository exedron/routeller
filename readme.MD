# \Exedron\Routeller
A Minimal annotation and reflection based anemic routeful controller for Exedra. 

In a simple word, a routeable-action-controller component in steroid.

## Introduction
Have you ever feel, about how ambigous our http controller usually is, especially on designing a restful api webservice. The http controller as we 
know today, is often nothing more than a bag of methods with ambiguous responsibility, which rarely say something about the domain problems, 
except by the controller class name, and what it inheritently contains, aside from being mapped independantly from the routing component. And I find it tedious to constantly need to work on both the route mapping, and the controller codes.

**The ambiguity of responsibility**. This is what this package is about to tackle, 
by totally removing the responsibility of preparing services, flatten the controller design, 
and make it as anemic as possible. In a simple word, the controller knows nothing about the runtime construction logic, 
but knows very well about the routing design.

The annotation design is fairly simple, just a @property-value mapping. Nothing much!

## Requirement
This package works only on Exedra. It cannot be used independantly, because.. Of course, 
it's part of Exedra routing component.

## Installation and usage
Install the package
```
composer require exedron\routeller dev-master
```
Setup the service provider
```php
// your application instance
$app->provider->add(\Exedron\Routeller\Provider::class);
```
On your preferred route, add use the controller using the *group()* method.
```php
$app->map['web']->group(new \App\Controller\Web);
```
On the \App\Controller\Web class, extends the base class.
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

class Web extends \Exedron\Routeller\Controller\Controller
{
    /**
     * @path /
     */
    public function executeIndex(Exe $exe)
    {
        return 'This is index page';
    }
    
    /**
     * @name about-us
     * @path /about-us
     * @method GET|POST
     */
    public function executeAboutUs(Exe $exe)
    {
        return 'This is about page';
    }
}
```
Doing above is similar to doing :
```php
$app->map['web']->group(function(Router $router) {
    $router['index']->any('/')->execute(function(Exe $exe) {
        return 'This is index page
    });
    
    $router['about-us']->any('/about-us')->execute(function(Exe $exe) {
        return 'This is about page';
    });
});
```

### Middleware
Add a middleware for the current group's route, by prefixing the method name with *middleware*.
 This method expects no annotation.
```php
public function middlewareAuth(Exe $exe)
{
    if(!$exe->session->has('user_id'))
        return $exe->redirect->route('@web.index');

    return $exe->next($exe);
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
    return new \App\Controller\Products;
}
```

### Normal routing
You can also do a usual routing by prefixing the method name with *route*. This method expects no annotation.
```php
public function routeGeneral(Group $router)
{
    $router->get('/')->execute(function() {
    });
}
```

## Restful verbs
This package also support a simple restful mapping.

#### Extending the restful abstract class
```php
namespace App\Controller;

use Exedron\Routeller\Controller\Restful;

class Products extends Restful
{
}
```
#### GET, POST, PUT, PATCH, DELETE verb
Prefix each method with the http verb as you like.
```php
/**
 * Get all products
 * @path /
 */
public function getProducts(Exe $exe)
{
}
```
```php
/**
 * Create a new product
 * @path /
 */
pubic function postProducts(Exe $exe)
{
}
```
```php
/**
 * GET the product
 * @path /[:id]
 */
pubic function getProduct(Exe $exe)
{
}
```
```php
/**
 * DELETE the product
 * @path /[:id]
 */
pubic function getProduct(Exe $exe)
{
}
```

#### Verb only method name
And you can have a route with only the verb.
```php
public function get(Exe $exe)
{
}

public function post(Exe $exe)
{
}
```
Of course, this is just a sample. Best way to do a resourceful design in Exedra, is through a subrouting.

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