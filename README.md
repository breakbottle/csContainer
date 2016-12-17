# CS Container

Dependency Injection Container for PHP
## Getting Started

Run bower install to get dependencies.

### Prerequisites

Include this class in your autoload folder or include in your project with dependencies

### Example

Include this class in your autoload folder or include in your project


```PHP
csContainer::Instance(array("configs"=>$this));//instance with default object

var $container = csContainer::Instance()
    ->Create('inamespace\toInterface', 'namespace\toClass')//register to the interface and class to be used
    ->Create('namespace\toInterface', 'namespace\toClass')//Chain register another
    ->definedClasses['key'] = new stdClass();//add already instantiated classes to this array
    //After all regeistred, you can resolve an instance from the containter
    $container->Resolve('inamespace\toInterface');//If the instance is already init, container will return that value not init a new instance.

    $container->ClearNamespace();//Clear default namespace -> [protected]$container->defaultNamespace

//in your Class
    function __construct(Interface $instance,Interface2 $instance2,Class $instance){
       ...
    }


```


## Authors

* **Clint Cain (Small)** - *Initial work* - [Breakbottle](https://github.com/breakbottle)

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details

## Acknowledgments

* From my c# works ;)
