<?php
/**
 * Created by PhpStorm.
 * User: sirmention
 * Date: 6/20/2016
 * Time: 4:44 PM
 */

namespace cs\lib;

class csContainer extends csSingleton{
    protected $dependants = array();
    protected $resolveCount = 0;
    public $definedClasses = array();
    protected $defaultNamespace;
    protected $levelsDeepToSearchDefinedClasses = 3;
    protected $limitResolvedItemCount = 20;
    public $logReport;
    public function __construct($defaultObject = array(),$levelsDeepToSearchDefinedClasses = 3){
        $this->levelsDeepToSearchDefinedClasses = $levelsDeepToSearchDefinedClasses;
        if(count($defaultObject) > 0) {
            $this->definedClasses = $defaultObject;
        }
        $this->logReport  = new \stdClass();
    }

    public function NamespaceHelper($namespace,$interfaceNamespace = ""){//todo:delete
        //if($interfaceNamespace)
        //    $this->defaultInterfaceNamespace = $interfaceNamespace;
        $this->defaultNamespace = $namespace;
        return $this;
    }

    public function Creates($interface,$class,$constructorArgs = null){
        $interfaceClassObject = new \stdClass();
        $interfaceClassObject->interfaceName = $interface;//$this->FindInterface($interface);
        $interfaceClassObject->className = $class;//$this->FindClass($class);
        $interfaceClassObject->classConstructorParams = $constructorArgs;
        if($this->defaultNamespace){
            $this->dependants[$this->defaultNamespace][$interface] = $interfaceClassObject;
        } else {
            $this->dependants["root"][$interface] = $interfaceClassObject;
        }

        return $this;
    }
    public function Resolve($interfaceToResolve){
        $result = null;

        $this->resolveCount++;
        if($interfaceToResolve == "stdClass"){
            //support for anonymous class/objects
            $result =  $this->SearchDefinedClassesInContainer("stdClass");
        } else {
            $classInfo = $this->LocateClassFromInterface($interfaceToResolve);
            //must make recursive,
            if($classInfo != null){
                if(!in_array($classInfo->fullyQualifiedClassName,get_declared_classes())){
                    $result = $this->EvaluateAndInitializeDefinedClass($classInfo->fullyQualifiedClassName,$classInfo->classArgs);
                } else {
                    $result =  $this->EvaluateAndInitializeClass($classInfo->fullyQualifiedClassName);
                }
            } else {
                $result = $this->EvaluateAndInitializeClass($interfaceToResolve);
            }
        }
        if(!array_key_exists($this->ParseNamespace(get_class($result)),$this->definedClasses)){
            $this->definedClasses[$this->ParseNamespace(get_class($result))] = $result;
        }

        return $result;
    }
    public function ClearNamespace(){
        unset($this->defaultNamespace);
    }
    private function ParseNamespace($name){
        $cnArray = explode("\\",$name);
        return strtolower($cnArray[count($cnArray)-1]);
    }
    private function LocateClassFromInterface($interfaceName){
        //$interfaceNameWithoutNamespace = $this->ParseNamespace($interfaceName);
        //$namespace = ($this->defaultNamespace)?$this->defaultNamespace:'root';
        $returnType = new \stdClass();
        if($this->defaultNamespace){//todo:delete
            $returnType->fullyQualifiedClassName = $this->defaultNamespace.'\\';
            $namespace = $this->defaultNamespace;
        } else{
            $namespace = 'root';
        }

        if(count($this->dependants[$namespace])){
            if(array_key_exists($interfaceName,$this->dependants[$namespace])){
                $returnType->fullyQualifiedClassName .= $this->dependants[$namespace][$interfaceName]->className;
                $returnType->classArgs = $this->dependants[$namespace][$interfaceName]->classConstructorParams;
                return $returnType;
            }
        }
        return null;
    }
    private function SearchDefinedClassesInContainer($className = null){
        $className = $this->ParseNamespace($className);
        $defined = null;
        switch($className){
            case 'stdclass':
                $defined = $this->definedClasses[$className];
                if($defined == null){
                    $defined = new \stdClass();
                }
                break;
            default:

                $defined = $this->definedClasses[$className];
                if($defined == null && $this->levelsDeepToSearchDefinedClasses > 1){
                    $defined = $this->FindDefinedClass($className);

                }


                if($defined == null){
                    $this->InitializeLogReport();//reset resolve report
                    //look up by interface
                    $interface = "i".$className;
                    $isObject = $this->dependants[$this->defaultNamespace][$interface];
                    if($isObject != null){

                        $defined = $this->definedClasses[$isObject->className];
                        if($defined == null){
                            $defined = null;//must be init..can't fine
                        }
                    }
                }
                break;

        }
        return $defined;
    }
    private $counter = 0;


    /*    $this->logReport->lookingFor = "";
        $this->logReport->lookedInList = array();
        $this->logReport->resolveCount = 0;
        $this->logReport->levelCount = 0;*/
    private function InitializeLogReport(){
        $this->logReport = new \stdClass();
        $this->logReport->lookingFor = "";
        $this->logReport->lookedInList = array('cs\lib\csContainer','logReport');
        $this->logReport->resolveCount = 0;
        $this->logReport->levelCount = 0;

    }
    private function FindDefinedClass($className,$list = null,$level = 0){

        $found = null;
        $cnt = 0;//how deeep to go
        $this->logReport->lookingFor = $className;
        $this->logReport->levelCount = $level;
        if($list == null){//search the properties of all first level objects
            foreach($this->definedClasses as $definedClassName => $definedClassInstance){
                // $find = $this->definedClasses[$definedClassName]->{$className};
                if(is_object($definedClassInstance)){

                    if(property_exists($definedClassInstance,$className)){
                        $found = $definedClassInstance->{$className};
                        $this->InitializeLogReport();
                        break;
                    } elseif($cnt <= $this->levelsDeepToSearchDefinedClasses) {
                        $searchedName = $this->GetClass($definedClassInstance, $definedClassName);
                        if(is_array($this->logReport->lookedInList)){
                            if(!in_array($searchedName,$this->logReport->lookedInList) ) {// || $this->logReport->resolveCount < $this->limitResolvedItemCount
                                $cnt++;
                                $this->logReport->lookedInList[] = $searchedName;
                                $this->logReport->resolveCount++;
                                $found = $this->FindDefinedClass($className, $definedClassInstance, 1);
                            }else {
                                //not found ?? we don't want circular ref infinite loop so we stop here...
                            }
                        }
                    }
                }
            }
        } else {
            foreach($list as $prop => $value){
                if(is_object($value)){
                    //$find = $list->{$className};

                    if(property_exists($value,$className)){
                        $found = $value->{$className};
                        $this->InitializeLogReport();
                        break;
                    } elseif($cnt <= $this->levelsDeepToSearchDefinedClasses) {
                        $searchedName = $this->GetClass($value,$prop);
                        if(!in_array($searchedName,$this->logReport->lookedInList)) {// || $this->logReport->resolveCount < $this->limitResolvedItemCount//we can also put an resolve count limit here..20 seems like a good limit
                            $cnt++;

                            $this->logReport->lookedInList[] = $searchedName;
                            $this->logReport->resolveCount++;
                            $found = $this->FindDefinedClass($className, $value, $cnt);
                        } else {
                            //not found ?? we don't want circular ref infinite loop so we stop here...
                        }
                    }
                }
            }
        }

        $this->counter++;
        return $found;
    }
    private function GetClass($classObject,$default = "stdClass"){
        $name = get_class($classObject);
        if($name == "stdClass"){
            return $default;
        }
        return $name;
    }
    private function ResolveArguments(array $methodArguments){
        $argumentsFromResolve = array();
        foreach($methodArguments as $argIndex => $argValue){
            if($argValue->getClass() != null){
                $argumentsFromResolve[] = $this->Resolve($argValue->getClass()->name);
            }
        }
        return $argumentsFromResolve;
    }
    private function SetProperty($className){
        if(count($this->dependants['root'])) {
            foreach ($this->dependants['root'] as $fullInterfaceName => $classObject) {
                if ($classObject->className == $className) {
                    return substr($this->ParseNamespace($classObject->interfaceName), 1);
                }
            }
        }
        return $this->ParseNamespace($className);

    }
    private function SetResolvedObjectsToParent(&$parent,$listOfObjects){
        foreach($listOfObjects as $aIndex => $objectToAddToParent){//add only objects to parent
            if(is_object($objectToAddToParent)){
                $cName =  $this->SetProperty(get_class($objectToAddToParent));
                $parent->$cName = $objectToAddToParent;
            }
        }
    }
    private function EvaluateAndInitializeDefinedClass($className,$args = null){

        $r = new \ReflectionClass($className);
        $hasConstructor = $r->getConstructor();
        $object = null;
        $argumentsFromResolve = array();

        if($hasConstructor != null){
            $methodArguments = $hasConstructor->getParameters();
            if(is_array($methodArguments) && count($methodArguments) > 0){
                if($args != null){
                    $classCached = $this->SearchDefinedClassesInContainer($className);
                    $object = ($classCached != null)?$classCached: $r->newInstanceArgs($args);
                    // $objectName = $this->ParseNamespace($className);
                    //  $object->$objectName = $object;
                } else {
                    $argumentsFromResolve = $this->ResolveArguments($methodArguments);
                    $classCached = $this->SearchDefinedClassesInContainer($className);
                    $object = ($classCached != null)?$classCached: $r->newInstanceArgs($argumentsFromResolve);
                    $this->SetResolvedObjectsToParent($object,$argumentsFromResolve);

                    //$objectName = $this->ParseNamespace($className);
                    //$this->$objectName = $object;
                }
            } else {
                ///no params
                $classCached = $this->SearchDefinedClassesInContainer($className);
                $object = ($classCached != null)?$classCached: $r->newInstance();
                // $objectName = $this->ParseNamespace($className);
                // $object->$objectName = $object;
            }
        } else {
            //no constructor
            $classCached = $this->SearchDefinedClassesInContainer($className);
            $object = ($classCached != null)?$classCached: $r->newInstance();
            // $objectName = $this->ParseNamespace($className);
            // $object->$objectName = $object;
        }

        //$this->$objectName = $object;
        return $object;

    }
    private function EvaluateAndInitializeClass($className){
        $result = null;
        if(class_exists($className)){//allow empty constructors to be called if not defined in Creates
            $r = new \ReflectionClass($className);
            $hasConstructor = $r->getConstructor();
            if(!$hasConstructor){
                $classCached = $this->SearchDefinedClassesInContainer($className);
                $result = ($classCached != null)?$classCached:$r->newInstance();
            } else {
                $prop = $hasConstructor->getParameters();
                if(is_array($prop) && count($prop) == 0){
                    $classCached = $this->SearchDefinedClassesInContainer($className);
                    $result = ($classCached != null)?$classCached:$r->newInstance();
                } else if(is_array($prop)) {
                    //resolve args
                    $argumentsFromResolve = $this->ResolveArguments($prop);
                    //if(preg_match("/webconfig/i",$className)){
                    /* echo '<div class="well">STARTer ---- '.$className."\n\n\n\n\n";
                     //\Tools::DebugArray($classCached);
                     \Tools::DebugArray($r);
                     \Tools::DebugArray($argumentsFromResolve);
                     \Tools::DebugArray($this);
                     echo "</div>ENDer\n\n\n\n\n";*/
                    //}
                    if(is_array($argumentsFromResolve)){
                        $classCached = $this->SearchDefinedClassesInContainer($className);


                        $result = ($classCached != null)?$classCached: $r->newInstanceArgs($argumentsFromResolve);

                        $this->SetResolvedObjectsToParent($result,$argumentsFromResolve);
                    } else {
                        //new exception, if we don't stop the app here it will eventually break because the instantiation will pass a null instead of an instance of this class.
                        Throw new ContainerException("Cannot resolve class: ".$className.". Add class definition to list of dependants using the Create Method.");
                    }
                }
            }

        }
        return $result;
    }
}
class ContainerException extends \Exception{
    public function __construct($message,$code = 0, \Exception $previous = null){

        parent::__construct($message, $code, $previous);
    }
}
